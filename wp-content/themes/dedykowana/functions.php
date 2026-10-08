<?php
if (!defined('ABSPATH')) exit;

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    register_nav_menus(['primary' => 'Menu główne', 'footer' => 'Menu stopki']);
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('dedykowana', get_stylesheet_uri(), [], wp_get_theme()->get('Version'));
});

add_action('customize_register', function ($wp_customize) {
    $wp_customize->add_section('dedykowana', ['title' => 'Dedykowana', 'priority' => 30]);
    $fields = [
        'dedykowana_hero_title' => ['Nagłówek strony głównej', 'text'],
        'dedykowana_hero_text'  => ['Opis pod nagłówkiem', 'textarea'],
        'dedykowana_seller'     => ['Dane sprzedawcy w stopce (imię, nazwisko, adres, e-mail)', 'textarea'],
    ];
    foreach ($fields as $id => [$label, $type]) {
        $wp_customize->add_setting($id, ['sanitize_callback' => 'sanitize_textarea_field']);
        $wp_customize->add_control($id, ['label' => $label, 'section' => 'dedykowana', 'type' => $type]);
    }
});

/* Kasa: prostsze, w pełni polskie teksty */
add_filter('woocommerce_enable_order_comments', '__return_false');
add_filter('woocommerce_coupons_enabled', '__return_false');
add_filter('gettext', function ($t, $orig, $domain) {
    if ($domain !== 'woocommerce') return $t;
    $map = ['Dane rozliczeniowe' => 'Twoje dane', 'Podsumowanie koszyka' => 'Do zapłaty', 'Przelew bankowy' => 'Przelew tradycyjny'];
    return $map[$t] ?? $t;
}, 10, 3);
add_action('init', function () {
    update_option('woocommerce_checkout_privacy_policy_text', 'Twoje dane wykorzystamy wyłącznie do realizacji zamówienia, zgodnie z [privacy_policy].');
    update_option('woocommerce_checkout_terms_and_conditions_checkbox_text', 'Zapoznałem/am się z [terms] i akceptuję jego treść.');
    update_option('woocommerce_enable_coupons', 'no');
});

/* Strona produktu: "Co dostajesz" i informacje o humorze oraz zwrotach */
function dedykowana_product_includes($gen) {
    $m = [
        'diploma' => ['Dyplom w PDF do wydruku', 'Wybrany tytuł i uzasadnienie (własne albo wymyślone)', 'Opcjonalne zdjęcie na pieczęci', 'Jedna bezpłatna poprawka tekstu'],
        'ai_poem' => ['Wierszyk z imieniem, napisany pod Twoje szczegóły', 'Dostawa na e-mail do 48 godzin', 'Jedna bezpłatna poprawka tekstu'],
        'pet_card' => ['Legitymację zwierzaka ze zdjęciem w pliku JPG', 'Imię, gatunek i stanowisko ze śmiesznego zestawu', 'Jedna bezpłatna poprawka tekstu'],
        'wanted' => ['List gończy ze zdjęciem zwierzaka w pliku JPG', 'Wybrane przewinienie i nagroda', 'Wyraźny dopisek, że to żart'],
        'trading_card' => ['Kartę kolekcjonerską ze zdjęciem w pliku JPG', 'Żywioł, atak specjalny i statystyki', 'Jedna bezpłatna poprawka tekstu'],
        'pixel_pet' => ['Ekran postaci z gry 2D w pliku PNG (1200 × 1200)', 'Zwierzak w pikselach, poziom, paski HP i siły, ekwipunek', 'Klasa i umiejętność specjalna do wyboru', 'Najlepiej wychodzi zdjęcie z całą głową i spokojnym tłem'],
    ];
    return $m[$gen] ?? ['Plik cyfrowy na e-mail po zaksięgowaniu płatności', 'Jedna bezpłatna poprawka'];
}
add_action('woocommerce_after_single_product_summary', function () {
    global $product; if (!$product) return;
    $gen = get_post_meta($product->get_id(), '_co_generator', true);
    $terms = function_exists('wc_terms_and_conditions_page_id') ? get_permalink(wc_terms_and_conditions_page_id()) : '#';
    $before = (int) get_theme_mod('dedykowana_hero_before');
    echo '<div class="co-extra">';
    if ($before && in_array($gen, ['pet_card', 'trading_card', 'wanted', 'pixel_pet', 'diploma'], true) && $product->get_image_id()) {
        echo '<div class="box flow-box"><h3>Jak to powstaje</h3><div class="flow-mini"><figure>' . wp_get_attachment_image($before, 'medium') . '<figcaption>Zdjęcie</figcaption></figure><span aria-hidden="true">→</span><figure>' . wp_get_attachment_image($product->get_image_id(), 'medium') . '<figcaption>' . esc_html($product->get_name()) . '</figcaption></figure></div><p>' . ($gen === 'diploma' ? 'Zdjęcie jest opcjonalne i trafia na pieczęć dyplomu.' : 'Zdjęcie jest kadrowane przez Ciebie, a potem generowane automatycznie przez kod, bez AI.') . ' Przykład wykonany na zdjęciu poglądowym.</p></div>';
    }
    if ($gen === 'ai_poem' && function_exists('co_example_poem')) {
        echo '<div class="box flow-box"><h3>Przykład: wierszyk dla Bruna</h3><blockquote class="poem-ex">' . nl2br(esc_html(implode("\n", co_example_poem()))) . '</blockquote><p>Podajesz imię, okazję, ton i kilka szczegółów, a tekst powstaje pod Ciebie. Część wierszyków powstaje z pomocą narzędzi AI, ale zawsze sprawdzam je przed wysyłką.</p></div>';
    }
    echo '<div class="box"><h3>Co dostajesz</h3><ul>';
    foreach (dedykowana_product_includes($gen) as $li) echo '<li>' . esc_html($li) . '</li>';
    echo '</ul></div><div class="stack"><div class="box"><h3>Humor, nie dokument</h3><p>Treści i tytuły są wymyślone dla zabawy. Produkt nie udaje żadnego prawdziwego dokumentu ani instytucji.</p></div>';
    echo '<div class="box"><h3>Zwroty i reklamacje</h3><p>To produkt robiony specjalnie dla Ciebie, więc po rozpoczęciu realizacji nie można od umowy odstąpić. Jeśli jest literówka albo zły plik, poprawiamy to. <a href="' . esc_url($terms) . '">Pełne zasady</a></p></div></div></div>';
}, 5);
add_filter('gettext', function ($t, $orig, $domain) {
    if ($domain === 'woocommerce' && $t === 'Podobne produkty') return 'Inne produkty';
    return $t;
}, 11, 3);
