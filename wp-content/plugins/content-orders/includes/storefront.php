<?php
if (!defined('ABSPATH')) exit;

function co_product_generator($product_id) {
    return co_generator(get_post_meta($product_id, '_co_generator', true));
}

/* Ustawienie generatora w panelu produktu */
add_action('woocommerce_product_options_general_product_data', function () {
    $opts = ['' => '— zwykły produkt —'];
    foreach (co_generators() as $id => $g) $opts[$id] = $g->label();
    woocommerce_wp_select([
        'id' => '_co_generator',
        'label' => 'Generator treści',
        'options' => $opts,
        'desc_tip' => true,
        'description' => 'Po wyborze na stronie produktu pojawi się formularz, a po opłaceniu powstanie zlecenie.',
    ]);
});
add_action('woocommerce_process_product_meta', function ($post_id) {
    $v = isset($_POST['_co_generator']) ? sanitize_key(wp_unslash($_POST['_co_generator'])) : '';
    update_post_meta($post_id, '_co_generator', co_generator($v) ? $v : '');
});

/* Prosta blokada wulgaryzmów (rdzenie słów). Rozszerzysz ją filtrem co_blocked_words. */
function co_is_clean($text) {
    $words = apply_filters('co_blocked_words', ['chuj', 'kurw', 'pierdol', 'jeban', 'pizd', 'spierdal', 'jebac', 'jebać']);
    $t = mb_strtolower($text);
    foreach ($words as $w) {
        if ($w !== '' && mb_strpos($t, $w) !== false) return false;
    }
    return true;
}

/* Odczyt i walidacja pól: zwraca [dane, komunikaty błędów] */
function co_read_input(CO_Generator $gen) {
    $input = [];
    $errors = [];
    foreach ($gen->fields() as $key => $f) {
        $raw = isset($_POST['co_' . $key]) ? wp_unslash($_POST['co_' . $key]) : '';
        $raw = is_string($raw) ? $raw : '';
        if (($f['type'] ?? 'text') === 'photo') {
            $val = co_photo_exists($raw) ? $raw : '';
            $input[$key] = $val;
            if ($val === '' && (!isset($f['required']) || $f['required'])) $errors[] = 'Dodaj zdjęcie (pole: ' . $f['label'] . ')';
            continue;
        }
        if (($f['type'] ?? 'text') === 'select') {
            $val = in_array($raw, $f['options'], true) ? $raw : '';
        } elseif ($f['type'] === 'textarea') {
            $val = mb_substr(sanitize_textarea_field($raw), 0, 1000);
        } else {
            $val = mb_substr(sanitize_text_field($raw), 0, 100);
        }
        $val = $gen->clean($key, $val);
        $input[$key] = $val;
        $required = !isset($f['required']) || $f['required'];
        if ($required && trim($val) === '') {
            $errors[] = 'Uzupełnij pole: ' . $f['label'];
        } elseif (!co_is_clean($val)) {
            $errors[] = 'Pole „' . $f['label'] . '” zawiera niedozwolone słowa.';
        }
    }
    return [$input, $errors];
}

/* Formularz na stronie produktu */
add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;
    $gen = $product ? co_product_generator($product->get_id()) : null;
    if (!$gen) return;
    wp_nonce_field('co_order', 'co_nonce');
    echo '<div class="co-fields"><h4>Dane do zamówienia</h4>';
    foreach ($gen->fields() as $key => $f) {
        $name = 'co_' . $key;
        $val = isset($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
        $val = is_string($val) ? $val : '';
        echo '<label>' . esc_html($f['label']);
        if ($f['type'] === 'photo') {
            echo '<input type="file" class="co-photo-file" accept="image/jpeg,image/png,image/webp" data-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-nonce="' . esc_attr(wp_create_nonce('co_upload')) . '">';
            echo '<input type="hidden" name="' . esc_attr($name) . '" class="co-photo-token" value="' . esc_attr(co_photo_exists($val) ? $val : '') . '">';
            echo '<span class="co-photo-status"></span><img class="co-photo-thumb" alt="" style="display:none;max-width:160px;border-radius:8px">';
        } elseif ($f['type'] === 'select') {
            echo '<select name="' . esc_attr($name) . '" required>';
            foreach ($f['options'] as $o) echo '<option' . selected($val, $o, false) . '>' . esc_html($o) . '</option>';
            echo '</select>';
        } elseif ($f['type'] === 'textarea') {
            echo '<textarea name="' . esc_attr($name) . '" rows="4" maxlength="1000" required>' . esc_textarea($val) . '</textarea>';
        } else {
            echo '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '" maxlength="100" required>';
        }
        echo '</label>';
    }
    if ($gen->has_preview()) {
        echo '<button type="button" class="button co-preview-btn" data-gen="' . esc_attr($gen->id()) . '" data-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-nonce="' . esc_attr(wp_create_nonce('co_preview')) . '">Pokaż podgląd</button><div class="co-preview"></div>';
    }
    echo '</div>';
});

add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id) {
    $gen = co_product_generator($product_id);
    if (!$gen) return $passed;
    if (!isset($_POST['co_nonce']) || !wp_verify_nonce($_POST['co_nonce'], 'co_order')) {
        wc_add_notice('Nie udało się zweryfikować formularza. Odśwież stronę i spróbuj ponownie.', 'error');
        return false;
    }
    list(, $errors) = co_read_input($gen);
    if ($errors) {
        wc_add_notice($errors[0], 'error');
        return false;
    }
    return $passed;
}, 10, 2);

add_filter('woocommerce_add_cart_item_data', function ($data, $product_id) {
    $gen = co_product_generator($product_id);
    if (!$gen) return $data;
    list($input) = co_read_input($gen);
    $data['co_generator'] = $gen->id();
    $data['co_input'] = $input;
    $data['co_key'] = md5(wp_json_encode($input) . microtime()); // każde zamówienie to osobna pozycja
    return $data;
}, 10, 2);

add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    $gen = !empty($cart_item['co_generator']) ? co_generator($cart_item['co_generator']) : null;
    if (!$gen) return $item_data;
    $fields = $gen->fields();
    foreach ($cart_item['co_input'] as $key => $v) {
        if (!isset($fields[$key]) || trim((string) $v) === '') continue;
        $label = trim(preg_replace('/\s*\(.*?\)\s*/u', ' ', $fields[$key]['label']));
        $item_data[] = ['key' => $label, 'value' => $fields[$key]['type'] === 'photo' ? 'dodane' : $v];
    }
    return $item_data;
}, 10, 2);

add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values) {
    $gen = !empty($values['co_generator']) ? co_generator($values['co_generator']) : null;
    if (!$gen) return;
    $fields = $gen->fields();
    $item->add_meta_data('_co_generator', $gen->id(), true);
    $item->add_meta_data('_co_input', $values['co_input'], true);
    foreach ($values['co_input'] as $key => $v) {
        if (isset($fields[$key]) && trim((string) $v) !== '') {
            $label = trim(preg_replace('/\s*\(.*?\)\s*/u', ' ', $fields[$key]['label']));
            $item->add_meta_data($label, $fields[$key]['type'] === 'photo' ? 'dodane' : $v, true);
        }
    }
}, 10, 3);

/* Zgoda na treści cyfrowe (utrata prawa odstąpienia) */
function co_cart_has_digital() {
    if (!function_exists('WC') || !WC()->cart) return false;
    foreach (WC()->cart->get_cart() as $item) {
        if (!empty($item['co_generator']) || $item['data']->is_virtual() || $item['data']->is_downloadable()) return true;
    }
    return false;
}
add_action('woocommerce_review_order_before_submit', function () {
    if (!co_cart_has_digital()) return;
    echo '<div class="co-waiver"><label><input type="checkbox" name="co_waiver" value="1"> ';
    echo 'Żądam rozpoczęcia realizacji od razu i przyjmuję do wiadomości, że po jej rozpoczęciu tracę prawo do odstąpienia od umowy w ciągu 14 dni.';
    echo '</label></div>';
});
add_action('woocommerce_checkout_process', function () {
    if (co_cart_has_digital() && empty($_POST['co_waiver'])) {
        wc_add_notice('Potwierdź zgodę na rozpoczęcie realizacji i utratę prawa odstąpienia.', 'error');
    }
});
add_action('woocommerce_checkout_create_order', function ($order) {
    if (!empty($_POST['co_waiver'])) $order->update_meta_data('_co_waiver', current_time('mysql'));
});

/* Minimalne style niezależne od motywu */
add_action('wp_head', function () {
    echo '<style>.co-fields{background:#f1f0fa;border-radius:12px;padding:16px;margin:0 0 16px;display:grid;gap:10px}'
        . '.co-fields h4{margin:0}.co-fields label{display:grid;gap:4px;font-size:.9em}'
        . '.co-fields input,.co-fields select,.co-fields textarea{width:100%;padding:8px}'
        . '.co-waiver{background:#f1f0fa;padding:12px;border-radius:10px;margin:12px 0;font-size:.9em}</style>';
});


/* Podgląd (AJAX) */
function co_ajax_preview() {
    check_ajax_referer('co_preview', 'nonce');
    $gen = co_generator(sanitize_key($_POST['generator'] ?? ''));
    if (!$gen || !$gen->has_preview()) wp_send_json_error('Podgląd niedostępny.');
    $k = 'co_pv_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
    $n = (int) get_transient($k);
    if ($n >= 30) wp_send_json_error('Za dużo podglądów. Spróbuj za kilka minut.');
    set_transient($k, $n + 1, 10 * MINUTE_IN_SECONDS);
    list($input) = co_read_input($gen);
    if (!co_is_clean(implode(' ', $input))) wp_send_json_error('Treść zawiera niedozwolone słowa.');
    $jpeg = $gen->preview($input);
    if (!$jpeg) wp_send_json_error('Nie udało się wygenerować podglądu.');
    wp_send_json_success(['img' => 'data:image/jpeg;base64,' . base64_encode($jpeg)]);
}
add_action('wp_ajax_co_preview', 'co_ajax_preview');
add_action('wp_ajax_nopriv_co_preview', 'co_ajax_preview');

add_action('wp_footer', function () {
    if (!function_exists('is_product') || !is_product()) return;
    ?>
<script>
document.addEventListener('click', function (e) {
  var b = e.target.closest('.co-preview-btn');
  if (!b) return;
  e.preventDefault();
  var form = b.closest('form'), box = form.querySelector('.co-preview'), fd = new FormData();
  fd.append('action', 'co_preview'); fd.append('nonce', b.dataset.nonce); fd.append('generator', b.dataset.gen);
  form.querySelectorAll('[name^="co_"]').forEach(function (el) { if (el.name !== 'co_nonce') fd.append(el.name, el.value); });
  box.textContent = 'Generuję podgląd...';
  fetch(b.dataset.url, {method: 'POST', body: fd}).then(function (r) { return r.json(); }).then(function (j) {
    box.textContent = '';
    if (j.success) { var i = new Image(); i.src = j.data.img; i.style.maxWidth = '100%'; box.appendChild(i); }
    else { box.textContent = j.data || 'Nie udało się.'; }
  }).catch(function () { box.textContent = 'Nie udało się.'; });
});
</script>
    <?php
});

/* Minimalizacja danych: dla produktów cyfrowych kasa pyta tylko o imię, nazwisko i e-mail */
add_filter('woocommerce_checkout_fields', function ($fields) {
    if (!function_exists('WC') || !WC()->cart || WC()->cart->needs_shipping()) return $fields;
    foreach (['billing_company', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_postcode', 'billing_state', 'billing_phone'] as $f) {
        unset($fields['billing'][$f]);
    }
    return $fields;
});

/* Każde zamówienie z formularza to osobna pozycja: bez pola ilości */
add_filter('woocommerce_is_sold_individually', function ($v, $product) {
    return co_product_generator($product->get_id()) ? true : $v;
}, 10, 2);
