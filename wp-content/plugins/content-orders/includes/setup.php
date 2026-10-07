<?php
if (!defined('ABSPATH')) exit;

/* Powiadomienie po aktywacji o konfiguratorze */
add_action('admin_notices', function () {
    if (get_option('co_setup_done') || !current_user_can('manage_options')) return;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->id === 'co_job_page_co-setup') return;
    echo '<div class="notice notice-info"><p><strong>Content Orders:</strong> uruchom <a href="' . esc_url(admin_url('edit.php?post_type=co_job&page=co-setup')) . '">Konfigurator sklepu</a>, aby utworzyć strony, produkty i menu jednym kliknięciem.</p></div>';
});

add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=co_job', 'Konfigurator sklepu', 'Konfigurator sklepu', 'manage_options', 'co-setup', function () {
        $o = get_option('co_setup', []);
        $v = function ($k) use ($o) { return esc_attr($o[$k] ?? ''); };
        echo '<div class="wrap"><h1>Konfigurator sklepu</h1>';
        if (!empty($_GET['done'])) echo '<div class="notice notice-success"><p>Gotowe. Strony, produkty, menu i płatność zostały utworzone. Sprawdź sklep na froncie.</p></div>';
        echo '<p>Wypełnij dane i kliknij przycisk. Konfigurator ustawia PLN, przelew/BLIK, tworzy strony prawne, menu i przykładowe produkty. Możesz go uruchomić ponownie, aby zaktualizować dane (nie dubluje stron ani produktów).</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="co_setup">';
        wp_nonce_field('co_setup');
        echo '<table class="form-table">';
        $row = function ($label, $name, $type = 'text', $desc = '') use ($v) {
            echo '<tr><th><label>' . esc_html($label) . '</label></th><td><input type="' . $type . '" name="' . $name . '" value="' . $v($name) . '" class="regular-text">';
            if ($desc) echo '<p class="description">' . esc_html($desc) . '</p>';
            echo '</td></tr>';
        };
        $row('Imię i nazwisko sprzedawcy', 'seller', 'text', 'Wymagane w regulaminie i stopce.');
        $row('Adres', 'address', 'text', 'Np. ul. Przykładowa 1, 00-000 Miasto.');
        $row('E-mail kontaktowy', 'email', 'email');
        $row('Właściciel rachunku', 'account_name');
        $row('Numer konta (IBAN lub NRB)', 'iban');
        $row('Nazwa banku', 'bank_name');
        $row('Numer telefonu do BLIK (opcjonalnie)', 'blik', 'text', 'Klient zobaczy go w instrukcji płatności.');
        echo '</table>';
        submit_button('Utwórz / zaktualizuj sklep');
        echo '</form></div>';
    });
});

function co_upsert_page($key, $title, $content) {
    $pages = get_option('co_pages', []);
    $id = $pages[$key] ?? 0;
    if ($id && get_post($id)) {
        wp_update_post(['ID' => $id, 'post_content' => $content]);
    } else {
        $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $content]);
        $pages[$key] = $id;
        update_option('co_pages', $pages);
    }
    return $id;
}

function co_sample_image($product_id, $filename, $jpeg, $title) {
    $up = wp_upload_bits($filename, null, $jpeg);
    if (!empty($up['error'])) return 0;
    $att = wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_title' => $title, 'post_status' => 'inherit'], $up['file'], $product_id);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $up['file']));
    return $att;
}

/** Tworzy ustawienia, strony, menu, płatność i produkty. Wywoływana z konfiguratora albo przez WP-CLI. */
function co_run_setup(array $d) {
    if (!class_exists('WooCommerce')) return new WP_Error('co_nowc', 'Najpierw aktywuj WooCommerce.');
    $d += ['seller' => '', 'address' => '', 'email' => '', 'account_name' => '', 'iban' => '', 'bank_name' => '', 'blik' => ''];
    update_option('co_setup', $d);

    // WooCommerce: PLN, Polska, bez podatków, zakup bez konta
    update_option('woocommerce_currency', 'PLN');
    update_option('woocommerce_default_country', 'PL');
    update_option('woocommerce_calc_taxes', 'no');
    update_option('woocommerce_enable_guest_checkout', 'yes');
    update_option('woocommerce_enable_signup_and_login_from_checkout', 'no');
    update_option('woocommerce_price_decimal_sep', ',');
    update_option('woocommerce_price_thousand_sep', ' ');
    update_option('woocommerce_currency_pos', 'right_space');

    // Płatność: przelew / BLIK na telefon
    $instr = 'Po złożeniu zamówienia wykonaj przelew na podane konto';
    $instr .= $d['blik'] ? ' lub BLIK-iem na telefon: ' . $d['blik'] : '';
    $instr .= '. W tytule wpisz numer zamówienia. Realizacja rozpoczyna się po zaksięgowaniu płatności.';
    update_option('woocommerce_bacs_settings', [
        'enabled' => 'yes',
        'title' => $d['blik'] ? 'Przelew / BLIK na telefon' : 'Przelew bankowy',
        'description' => 'Płatność tradycyjnym przelewem' . ($d['blik'] ? ' lub BLIK-iem na telefon.' : '.'),
        'instructions' => $instr,
        'account_details' => '',
    ]);
    update_option('woocommerce_bacs_accounts', [[
        'account_name' => $d['account_name'], 'account_number' => $d['iban'], 'sort_code' => '',
        'bank_name' => $d['bank_name'], 'iban' => $d['iban'], 'bic' => '',
    ]]);

    // Strony prawne i kontakt
    $terms = co_upsert_page('terms', 'Regulamin', co_legal_fill(co_legal_terms(), $d));
    $priv = co_upsert_page('privacy', 'Polityka prywatności', co_legal_fill(co_legal_privacy(), $d));
    $contact = co_upsert_page('contact', 'Kontakt', '<p>Napisz do nas: <a href="mailto:' . esc_attr($d['email']) . '">' . esc_html($d['email']) . '</a></p><p>' . esc_html($d['seller']) . ', ' . esc_html($d['address']) . '</p>');
    $home = co_upsert_page('home', 'Start', '');
    // Klasyczny koszyk i kasa: blokowa kasa nie uruchamia hooków wtyczki (zgoda na utratę prawa odstąpienia, ukrywanie pól)
    foreach (['cart' => '[woocommerce_cart]', 'checkout' => '[woocommerce_checkout]'] as $pg => $sc) {
        $pid = wc_get_page_id($pg);
        if ($pid > 0) wp_update_post(['ID' => $pid, 'post_content' => $sc]);
    }
    update_option('woocommerce_terms_page_id', $terms);
    update_option('wp_page_for_privacy_policy', $priv);
    update_option('show_on_front', 'page');
    update_option('page_on_front', $home);

    // Menu
    $menus = [];
    foreach (['Główne' => [wc_get_page_id('shop'), $contact], 'Stopka' => [$terms, $priv, $contact]] as $name => $page_ids) {
        $menu = wp_get_nav_menu_object($name);
        $menu_id = $menu ? $menu->term_id : wp_create_nav_menu($name);
        if ($menu_id && !is_wp_error($menu_id) && !$menu) {
            foreach ($page_ids as $pid) {
                if ($pid <= 0) continue;
                wp_update_nav_menu_item($menu_id, 0, [
                    'menu-item-title' => get_the_title($pid),
                    'menu-item-object' => 'page', 'menu-item-object-id' => $pid,
                    'menu-item-type' => 'post_type', 'menu-item-status' => 'publish',
                ]);
            }
        }
        $menus[$name] = $menu_id;
    }
    set_theme_mod('nav_menu_locations', ['primary' => $menus['Główne'], 'footer' => $menus['Stopka']]);
    set_theme_mod('dedykowana_seller', trim($d['seller'] . "\n" . $d['address'] . "\n" . $d['email']));

    // Produkty przykładowe (raz)
    $products = get_option('co_products', []);
    $defs = [
        'diploma' => ['Dyplom z Twoim imieniem (PDF)', '9', 'publish', 'diploma',
            'Absurdalny dyplom w formacie PDF z imieniem, które podasz. Wybierz tytuł, dodaj własne uzasadnienie albo pozwól je wymyślić. Gotowy plik dostaniesz na e-mail po zaksięgowaniu płatności, zwykle w kilka minut. Idealny prezent na żart.',
            'Dyplom PDF do wydruku, dostawa na e-mail.'],
        'poem' => ['Wierszyk okolicznościowy', '15', 'publish', 'ai_poem',
            'Krótki wierszyk na urodziny, imieniny lub bez okazji. Podajesz imię, ton i kilka szczegółów, a ja przygotowuję tekst. Dostawa na e-mail do 48 godzin.',
            'Spersonalizowany wierszyk, dostawa do 48 h.'],
        'pet' => ['Legitymacja Twojego zwierzaka (JPG)', '19', 'publish', 'pet_card',
            'Wgraj zdjęcie swojego pupila, podaj imię, gatunek i stanowisko, a dostaniesz absurdalną legitymację ze zdjęciem. Gotowy plik JPG wysyłamy na e-mail po zaksięgowaniu płatności, zwykle w kilka minut. Idealny prezent dla właściciela zwierzaka i świetny materiał na media społecznościowe.',
            'Legitymacja zwierzaka ze zdjęciem, plik JPG na e-mail.'],
        'wanted' => ['List gończy za Twoim zwierzakiem (JPG)', '19', 'publish', 'wanted',
            'Zabawny list gończy w stylu western ze zdjęciem Twojego pupila. Wybierz, za co jest poszukiwany i jaka jest nagroda. Gotowy plik JPG dostaniesz na e-mail po zaksięgowaniu płatności, zwykle w kilka minut.',
            'List gończy ze zdjęciem zwierzaka, plik JPG na e-mail.'],
        'card' => ['Karta kolekcjonerska Twojego zwierzaka (JPG)', '19', 'publish', 'trading_card',
            'Karta kolekcjonerska ze zdjęciem pupila, żywiołem, atakiem specjalnym i statystykami. Gotowy plik JPG na e-mail po zaksięgowaniu płatności, zwykle w kilka minut. Świetna do pochwalenia się w mediach społecznościowych.',
            'Karta kolekcjonerska ze zdjęciem zwierzaka, plik JPG na e-mail.'],
        'pixel' => ['Twój zwierzak w grze 2D (PNG)', '19', 'publish', 'pixel_pet',
            'Wgraj zdjęcie pupila, wybierz klasę i umiejętność, a dostaniesz ekran postaci jak z gry 2D w stylu retro: zwierzak w pikselach, poziom, paski HP i siły, ekwipunek. Gotowy plik PNG wysyłamy na e-mail po zaksięgowaniu płatności, zwykle w kilka minut. Najlepiej wychodzą zdjęcia z jednolitym tłem, na których zwierzak jest na środku.',
            'Zwierzak jako postać z gry 2D, plik PNG na e-mail.'],
        'song' => ['Piosenka na zamówienie', '39', 'draft', 'song',
            'Spersonalizowana piosenka z Twoim tekstem i wybranym stylem muzycznym. Plik MP3 dostaniesz na e-mail do 48 godzin. Opublikuj produkt, gdy będziesz gotowy na zamówienia.',
            'Piosenka z imieniem, MP3 na e-mail.'],
    ];
    foreach ($defs as $key => [$name, $price, $status, $gen, $desc, $short]) {
        if (!empty($products[$key]) && get_post($products[$key])) continue;
        $p = new WC_Product_Simple();
        $p->set_name($name);
        $p->set_regular_price($price);
        $p->set_virtual(true);
        $p->set_status($status);
        $p->set_description($desc);
        $p->set_short_description($short);
        $p->set_catalog_visibility('visible');
        $id = $p->save();
        update_post_meta($id, '_co_generator', $gen);
        if ($key === 'diploma' && function_exists('imagettftext')) {
            list($jpeg) = co_render_diploma('Anna Kowalska', 'Królowa Kanapy', co_diploma_titles()['Królowa Kanapy'], 1200, false);
            $att = co_sample_image($id, 'dyplom-przyklad.jpg', $jpeg, 'Przykładowy dyplom');
            if ($att) { $p->set_image_id($att); $p->save(); }
        }
        if ($key === 'wanted' && function_exists('imagettftext')) {
            $att = co_sample_image($id, 'list-goncz-przyklad.jpg', co_render_wanted(null, 'Burek', co_wanted_crimes()[0], co_wanted_rewards()[0], 900, false), 'Przykładowy list gończy');
            if ($att) { $p->set_image_id($att); $p->save(); }
        }
        if ($key === 'card' && function_exists('imagettftext')) {
            $att = co_sample_image($id, 'karta-przyklad.jpg', co_render_card(null, 'Burek', 'Ogień', co_card_attacks()[0], 800, false), 'Przykładowa karta kolekcjonerska');
            if ($att) { $p->set_image_id($att); $p->save(); }
        }
        if ($key === 'pet' && function_exists('imagettftext')) {
            $jpeg = co_render_pet_card(null, 'Burek', 'Pies', co_pet_roles()[0], co_pet_default_trait('Pies'), 1200, false);
            $att = co_sample_image($id, 'legitymacja-przyklad.jpg', $jpeg, 'Przykładowa legitymacja zwierzaka');
            if ($att) { $p->set_image_id($att); $p->save(); }
        }
        $products[$key] = $id;
    }
    update_option('co_products', $products);

    update_option('co_setup_done', 1);
    flush_rewrite_rules();
    return true;
}

add_action('admin_post_co_setup', function () {
    if (!current_user_can('manage_options')) wp_die('Brak uprawnień.');
    check_admin_referer('co_setup');
    $d = [];
    foreach (['seller', 'address', 'email', 'account_name', 'iban', 'bank_name', 'blik'] as $k) {
        $d[$k] = sanitize_text_field(wp_unslash($_POST[$k] ?? ''));
    }
    $r = co_run_setup($d);
    if (is_wp_error($r)) wp_die(esc_html($r->get_error_message()));
    wp_safe_redirect(admin_url('edit.php?post_type=co_job&page=co-setup&done=1'));
    exit;
});
