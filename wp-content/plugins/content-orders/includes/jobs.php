<?php
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    register_post_type('co_job', [
        'labels' => ['name' => 'Zlecenia', 'singular_name' => 'Zlecenie', 'menu_name' => 'Zlecenia', 'edit_item' => 'Zlecenie'],
        'public' => false,
        'show_ui' => true,
        'menu_position' => 56,
        'menu_icon' => 'dashicons-edit-page',
        'supports' => ['title'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'capabilities' => ['create_posts' => 'do_not_allow'],
    ]);
});

/* Zlecenia powstają po opłaceniu zamówienia (status "w trakcie realizacji") */
function co_create_jobs($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    foreach ($order->get_items() as $item) {
        $gen_id = $item->get_meta('_co_generator');
        $gen = co_generator($gen_id);
        if (!$gen || $item->get_meta('_co_job_ids')) continue;
        $ids = [];
        for ($i = 0; $i < max(1, (int) $item->get_quantity()); $i++) {
            $job_id = wp_insert_post([
                'post_type' => 'co_job',
                'post_status' => 'publish',
                'post_title' => sprintf('#%s – %s', $order->get_order_number(), $item->get_name()),
            ]);
            if (is_wp_error($job_id)) continue;
            update_post_meta($job_id, '_co_order_id', $order->get_id());
            update_post_meta($job_id, '_co_generator', $gen_id);
            update_post_meta($job_id, '_co_input', $item->get_meta('_co_input'));
            update_post_meta($job_id, '_co_status', 'new');
            $ids[] = $job_id;
            if ($gen->is_automatic() && $gen->auto_deliver()) {
                wp_schedule_single_event(time(), 'co_auto_job', [$job_id]);
            }
        }
        $item->update_meta_data('_co_job_ids', $ids);
        $item->save();
        if ($ids && !($gen->is_automatic() && $gen->auto_deliver())) {
            wp_mail(get_option('admin_email'), 'Nowe zlecenie: ' . $item->get_name(), 'Przejdź do: ' . admin_url('edit.php?post_type=co_job'));
        }
    }
    if (wp_next_scheduled('co_auto_job') !== false) spawn_cron();
}
add_action('woocommerce_order_status_processing', 'co_create_jobs');
add_action('woocommerce_order_status_completed', 'co_create_jobs');

/* Zapis wyniku generatora: tekst i/lub plik (do prywatnego katalogu) */
function co_store_result($job_id, $res) {
    if (is_string($res)) $res = ['text' => $res];
    if (!empty($res['text'])) {
        update_post_meta($job_id, '_co_result_text', sanitize_textarea_field($res['text']));
    }
    if (!empty($res['file']['data'])) {
        $ext = preg_replace('/[^a-z0-9]/i', '', pathinfo($res['file']['name'], PATHINFO_EXTENSION)) ?: 'bin';
        $stored = wp_generate_password(12, false) . '.' . $ext;
        file_put_contents(co_private_dir() . '/' . $stored, $res['file']['data']);
        update_post_meta($job_id, '_co_file', $stored);
        update_post_meta($job_id, '_co_file_name', sanitize_file_name($res['file']['name']));
    }
    update_post_meta($job_id, '_co_status', 'draft');
    delete_post_meta($job_id, '_co_error');
}

/* Zlecenia w pełni automatyczne (np. dyplom): generuj i od razu dostarcz */
function co_run_auto_job($job_id) {
    if (get_post_meta($job_id, '_co_status', true) !== 'new') return;
    $gen = co_generator(get_post_meta($job_id, '_co_generator', true));
    if (!$gen || !$gen->is_automatic()) return;
    $res = $gen->generate((array) get_post_meta($job_id, '_co_input', true), $job_id);
    if (is_wp_error($res)) {
        update_post_meta($job_id, '_co_error', $res->get_error_message());
        update_post_meta($job_id, '_co_status', 'failed');
        wp_mail(get_option('admin_email'), 'Błąd zlecenia #' . $job_id, $res->get_error_message() . "\n" . admin_url('post.php?post=' . $job_id . '&action=edit'));
        return;
    }
    co_store_result($job_id, $res);
    if ($gen->auto_deliver()) {
        $r = co_deliver_job($job_id);
        if (is_wp_error($r)) {
            wp_mail(get_option('admin_email'), 'Nie udało się dostarczyć zlecenia #' . $job_id, $r->get_error_message() . "\n" . admin_url('post.php?post=' . $job_id . '&action=edit'));
        }
    }
}
add_action('co_auto_job', 'co_run_auto_job');

function co_status_label($s) {
    return ['new' => 'Nowe', 'draft' => 'Gotowe do wysyłki', 'delivered' => 'Dostarczone', 'failed' => 'Błąd'][$s] ?? $s;
}

/* Lista zleceń */
add_filter('manage_co_job_posts_columns', function ($cols) {
    return ['cb' => $cols['cb'], 'title' => 'Zlecenie', 'co_gen' => 'Generator', 'co_status' => 'Status', 'date' => 'Data'];
});
add_action('manage_co_job_posts_custom_column', function ($col, $id) {
    if ($col === 'co_gen') {
        $g = co_generator(get_post_meta($id, '_co_generator', true));
        echo esc_html($g ? $g->label() : '?');
    }
    if ($col === 'co_status') echo esc_html(co_status_label(get_post_meta($id, '_co_status', true)));
}, 10, 2);

/* Formularz wysyłki plików w edycji zlecenia */
add_action('post_edit_form_tag', function () {
    if (get_post_type() === 'co_job') echo ' enctype="multipart/form-data"';
});

add_action('add_meta_boxes', function () {
    add_meta_box('co_job_box', 'Zlecenie', 'co_render_job_box', 'co_job', 'normal', 'high');
});

function co_render_job_box($post) {
    $gen = co_generator(get_post_meta($post->ID, '_co_generator', true));
    $input = (array) get_post_meta($post->ID, '_co_input', true);
    $status = get_post_meta($post->ID, '_co_status', true);
    $order = wc_get_order((int) get_post_meta($post->ID, '_co_order_id', true));
    wp_nonce_field('co_save_job', 'co_job_nonce');

    if (!empty($_GET['co_msg'])) {
        echo '<div class="notice notice-info inline"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['co_msg']))) . '</p></div>';
    }
    echo '<p><strong>Status:</strong> ' . esc_html(co_status_label($status)) . '</p>';
    if ($order) {
        echo '<p><strong>Zamówienie:</strong> <a href="' . esc_url($order->get_edit_order_url()) . '">#' . esc_html($order->get_order_number()) . '</a> · '
            . esc_html($order->get_billing_email()) . '</p>';
    }
    echo '<h3>Dane od klienta</h3><table class="widefat striped"><tbody>';
    $fields = $gen ? $gen->fields() : [];
    foreach ($input as $key => $v) {
        echo '<tr><th style="width:30%">' . esc_html($fields[$key]['label'] ?? $key) . '</th><td>';
        if (($fields[$key]['type'] ?? '') === 'photo') {
            if (co_photo_exists($v)) {
                $u = wp_nonce_url(admin_url('admin-post.php?action=co_photo&token=' . $v), 'co_photo_' . $v);
                echo '<img src="' . esc_url($u) . '" alt="" style="max-width:240px;border-radius:8px">';
            } else {
                echo '<em>Zdjęcie wygasło (kasowane po 30 dniach).</em>';
            }
        } else {
            echo nl2br(esc_html($v));
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h3>Wynik</h3>';
    echo '<p><label>Tekst (opcjonalnie):<br><textarea name="co_result_text" rows="8" style="width:100%">'
        . esc_textarea(get_post_meta($post->ID, '_co_result_text', true)) . '</textarea></label></p>';
    $fname = get_post_meta($post->ID, '_co_file_name', true);
    echo '<p><label>Plik (opcjonalnie): <input type="file" name="co_result_file"></label>';
    if ($fname) echo ' <em>Aktualny: ' . esc_html($fname) . '</em>';
    echo '</p>';

    $err = get_post_meta($post->ID, '_co_error', true);
    if ($err) echo '<p style="color:#b32d2e">Ostatni błąd generowania: ' . esc_html($err) . '</p>';

    echo '<p>';
    if ($gen && $gen->is_automatic()) {
        $u = wp_nonce_url(admin_url('admin-post.php?action=co_generate&job=' . $post->ID), 'co_generate_' . $post->ID);
        echo '<a class="button" href="' . esc_url($u) . '">Generuj ponownie</a> ';
    }
    if ($status !== 'delivered') {
        $u = wp_nonce_url(admin_url('admin-post.php?action=co_deliver&job=' . $post->ID), 'co_deliver_' . $post->ID);
        echo '<a class="button button-primary" href="' . esc_url($u) . '">Dostarcz klientowi</a> ';
    } else {
        $u = wp_nonce_url(admin_url('admin-post.php?action=co_deliver&job=' . $post->ID), 'co_deliver_' . $post->ID);
        echo '<a class="button" href="' . esc_url($u) . '">Wyślij ponownie e-mail</a> ';
    }
    echo '</p><p class="description">Po ręcznej zmianie tekstu lub pliku kliknij „Aktualizuj”, a dopiero potem „Dostarcz klientowi”.</p>';
}

function co_private_dir() {
    // Katalog POZA publicznym katalogiem WWW (nginx ignoruje .htaccess). Jeśli się nie da, fallback do uploads.
    $outside = dirname(untrailingslashit(ABSPATH)) . '/co-private-' . substr(md5(ABSPATH . DB_NAME), 0, 8);
    if (is_dir($outside) || (is_writable(dirname($outside)) && wp_mkdir_p($outside))) {
        $dir = $outside;
    } else {
        $u = wp_upload_dir();
        $dir = trailingslashit($u['basedir']) . 'co-private';
    }
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
        file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        file_put_contents($dir . '/index.php', "<?php // silence\n");
    }
    return $dir;
}

add_action('save_post_co_job', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['co_job_nonce']) || !wp_verify_nonce($_POST['co_job_nonce'], 'co_save_job')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['co_result_text'])) {
        update_post_meta($post_id, '_co_result_text', sanitize_textarea_field(wp_unslash($_POST['co_result_text'])));
    }
    if (!empty($_FILES['co_result_file']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $dir = co_private_dir();
        $f = function ($d) use ($dir) {
            return array_merge($d, ['path' => $dir, 'url' => '', 'subdir' => '', 'basedir' => $dir, 'baseurl' => '']);
        };
        add_filter('upload_dir', $f);
        $res = wp_handle_upload($_FILES['co_result_file'], [
            'test_form' => false,
            'unique_filename_callback' => function ($d, $name, $ext) { return wp_generate_password(12, false) . $ext; },
        ]);
        remove_filter('upload_dir', $f);
        if (!empty($res['file'])) {
            update_post_meta($post_id, '_co_file', basename($res['file']));
            update_post_meta($post_id, '_co_file_name', sanitize_file_name($_FILES['co_result_file']['name']));
        }
    }
    $status = get_post_meta($post_id, '_co_status', true);
    if (in_array($status, ['new', 'failed'], true) && (get_post_meta($post_id, '_co_result_text', true) || get_post_meta($post_id, '_co_file', true))) {
        update_post_meta($post_id, '_co_status', 'draft');
    }
});

function co_back($job_id, $msg) {
    wp_safe_redirect(add_query_arg('co_msg', rawurlencode($msg), get_edit_post_link($job_id, 'raw')));
    exit;
}

add_action('admin_post_co_generate', function () {
    $job = (int) ($_GET['job'] ?? 0);
    check_admin_referer('co_generate_' . $job);
    if (!current_user_can('edit_post', $job)) wp_die('Brak uprawnień.');
    $gen = co_generator(get_post_meta($job, '_co_generator', true));
    if (!$gen || !$gen->is_automatic()) co_back($job, 'Ten generator nie obsługuje automatycznego generowania.');
    $res = $gen->generate((array) get_post_meta($job, '_co_input', true), $job);
    if (is_wp_error($res)) {
        update_post_meta($job, '_co_error', $res->get_error_message());
        co_back($job, 'Generowanie nie powiodło się.');
    }
    co_store_result($job, $res);
    co_back($job, 'Wynik wygenerowany. Sprawdź go i dopiero dostarcz.');
});
