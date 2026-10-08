<?php get_header();
$shop   = function_exists('wc_get_page_id') ? get_permalink(wc_get_page_id('shop')) : '#';
$before = (int) get_theme_mod('dedykowana_hero_before');
$after  = (int) get_theme_mod('dedykowana_hero_after');
$title  = get_theme_mod('dedykowana_hero_title', 'Twój zwierzak. Tylko dziwniejszy.');
$text   = get_theme_mod('dedykowana_hero_text', 'Wyślij zdjęcie, a zamienimy je w legitymację, kartę, list gończy albo postać z gry 2D. Gotowy plik dostajesz na e-mail.');
$price  = function_exists('wc_get_products') ? wc_get_products(['status' => 'publish', 'limit' => 20, 'orderby' => 'price', 'order' => 'ASC', 'return' => 'objects']) : [];
$from   = $price ? wc_price($price[0]->get_price()) : '';
?>
<section class="hero"><div class="wrap">
  <div>
    <h1><?php echo esc_html($title); ?></h1>
    <p><?php echo esc_html($text); ?></p>
    <a class="btn" href="<?php echo esc_url($shop); ?>">Wybierz produkt</a>
    <?php if ($from) : ?><div class="note">Od <?php echo wp_kses_post($from); ?> · gotowe po zaksięgowaniu wpłaty</div><?php endif; ?>
  </div>
  <div class="hero-tiles" aria-hidden="true">
    <div class="tile before"><?php if ($before) echo wp_get_attachment_image($before, 'large'); ?><span><?php echo esc_html(get_theme_mod('dedykowana_cap_before', 'Zdjęcie')); ?></span></div>
    <div class="tile after"><?php if ($after) echo wp_get_attachment_image($after, 'large'); ?><span><?php echo esc_html(get_theme_mod('dedykowana_cap_after', 'Postać z gry 2D')); ?></span></div>
  </div>
</div></section>

<?php if (function_exists('WC')) : ?>
<section class="section" id="oferta"><div class="wrap">
  <h2>Co możesz zamówić</h2>
  <?php echo do_shortcode('[products limit="6" columns="3" orderby="menu_order" order="ASC"]'); ?>
</div></section>
<?php endif; ?>

<section class="section alt"><div class="wrap">
  <h2>Jak to działa</h2>
  <div class="steps">
    <div><h3>Wyślij zdjęcie</h3><p>Wybierasz produkt, kadrujesz zdjęcie i wpisujesz kilka szczegółów.</p></div>
    <div><h3>Zapłać przelewem</h3><p>Dostajesz dane, przyciski „kopiuj" i kod QR do aplikacji banku.</p></div>
    <div><h3>Odbierz na e-mail</h3><p>Dyplomy i grafiki zwykle w kilka minut od zaksięgowania wpłaty, teksty do 48 godzin.</p></div>
  </div>
</div></section>

<?php
$outs = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 4, 'orderby' => 'menu_order', 'order' => 'ASC',
    'meta_query' => [['key' => '_co_generator', 'value' => ['pet_card', 'trading_card', 'wanted', 'pixel_pet'], 'compare' => 'IN']]]);
if ($before && $outs) : ?>
<section class="section"><div class="wrap">
  <h2>Z jednego zdjęcia kilka rzeczy</h2>
  <div class="flow">
    <figure class="flow-src"><?php echo wp_get_attachment_image($before, 'large'); ?><figcaption>Twoje zdjęcie</figcaption></figure>
    <div class="flow-arrow" aria-hidden="true">→</div>
    <div class="flow-outs">
      <?php foreach ($outs as $o) : $pp = wc_get_product($o->ID); ?>
        <figure><a href="<?php echo esc_url(get_permalink($o->ID)); ?>"><?php echo $pp->get_image('large'); ?></a><figcaption><?php echo esc_html($pp->get_name()); ?> · <?php echo wp_kses_post($pp->get_price_html()); ?></figcaption></figure>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="fine">Przykłady są zrobione na tym samym zdjęciu. Dyplom, legitymacja, karta, list gończy i postać z gry 2D powstają automatycznie, bez AI, a tekst wierszyka możemy przygotować z pomocą AI i zawsze sprawdzamy go przed wysyłką.</p>
</div></section>
<?php endif; ?>

<section class="section alt"><div class="wrap">
  <h2>Pytania</h2>
  <div class="faq">
    <details><summary>Czy mogę wysłać zdjęcie z człowiekiem?</summary><p>Nie, przyjmujemy tylko zdjęcia zwierząt. Najlepiej takie, na którym widać całą głowę, a zwierzak jest na środku kadru.</p></details>
    <details><summary>Co ze zdjęciem po realizacji?</summary><p>Usuwamy je automatycznie po 30 dniach.</p></details>
    <details><summary>Ile trwa realizacja?</summary><p>Dyplomy i grafiki zwykle w kilka minut od zaksięgowania wpłaty, teksty do 48 godzin.</p></details>
    <details><summary>Czy mogę zwrócić produkt?</summary><p>To produkt cyfrowy robiony na zamówienie, więc po rozpoczęciu realizacji prawo odstąpienia wygasa. Błędy poprawiamy w ramach reklamacji.</p></details>
    <details><summary>Czy to oficjalne dokumenty?</summary><p>Nie. Wszystkie produkty to humor i zabawa i są oznaczone jako fikcyjne.</p></details>
    <details><summary>Czy treści tworzy AI?</summary><p>Część tekstów powstaje z użyciem narzędzi AI, a przed wysyłką je sprawdzam.</p></details>
  </div>
</div></section>
<?php get_footer();
