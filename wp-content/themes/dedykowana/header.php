<?php if (!defined('ABSPATH')) exit; ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header"><div class="wrap">
  <a class="site-title" href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
  <nav class="site-nav" aria-label="Menu główne">
    <?php if (has_nav_menu('primary')) wp_nav_menu(['theme_location' => 'primary', 'container' => false]); ?>
  </nav>
  <?php if (function_exists('wc_get_cart_url')) : ?>
    <a class="cart-link" href="<?php echo esc_url(wc_get_cart_url()); ?>">Koszyk (<?php echo (int) WC()->cart->get_cart_contents_count(); ?>)</a>
  <?php endif; ?>
</div></header>
