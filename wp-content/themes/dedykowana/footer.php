<?php if (!defined('ABSPATH')) exit; ?>
<footer class="site-footer"><div class="wrap">
  <?php if (has_nav_menu('footer')) wp_nav_menu(['theme_location' => 'footer', 'container' => false]); ?>
  <div>&copy; <?php echo esc_html(date('Y')); ?> <?php bloginfo('name'); ?></div>
  <?php $seller = get_theme_mod('dedykowana_seller'); if ($seller) : ?>
    <div class="seller"><?php echo esc_html($seller); ?></div>
  <?php endif; ?>
</div></footer>
<?php wp_footer(); ?>
</body>
</html>
