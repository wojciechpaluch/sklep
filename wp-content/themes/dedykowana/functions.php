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
