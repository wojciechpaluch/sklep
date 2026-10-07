<?php get_header();
$title = get_theme_mod('dedykowana_hero_title', 'Spersonalizowane dyplomy, wierszyki i karty zwierzaków');
$text  = get_theme_mod('dedykowana_hero_text', 'Podajesz imię i kilka szczegółów. Gotowy plik dostajesz na e-mail.');
$shop  = function_exists('wc_get_page_id') ? get_permalink(wc_get_page_id('shop')) : '#';
?>
<section class="hero"><div class="wrap">
  <h1><?php echo esc_html($title); ?></h1>
  <p><?php echo esc_html($text); ?></p>
  <a class="btn" href="<?php echo esc_url($shop); ?>">Zobacz ofertę</a>
</div></section>

<div class="wrap">
<?php if (function_exists('WC')) : ?>
<section class="section">
  <h2>Oferta</h2>
  <?php echo do_shortcode('[products limit="8" columns="4" orderby="menu_order" order="ASC"]'); ?>
</section>
<?php endif; ?>

<section class="section">
  <h2>Jak to działa</h2>
  <div class="steps">
    <div><h3>Wybierasz produkt</h3><p>I wypełniasz krótki formularz.</p></div>
    <div><h3>Opłacasz zamówienie</h3><p>Przelewem, zgodnie z instrukcją po złożeniu zamówienia.</p></div>
    <div><h3>Realizacja</h3><p>Dyplomy i grafiki zwykle w kilka minut od zaksięgowania wpłaty, teksty do 48 godzin.</p></div>
    <div><h3>Dostawa</h3><p>Plik lub link do pobrania przychodzi na e-mail.</p></div>
  </div>
</section>

<section class="section faq">
  <h2>Pytania</h2>
  <details><summary>Ile trwa realizacja?</summary><p>Dyplomy i grafiki zwykle w kilka minut od zaksięgowania wpłaty, teksty do 48 godzin.</p></details>
  <details><summary>Czy treści tworzy AI?</summary><p>Część tekstów powstaje z użyciem narzędzi AI, a ja sprawdzam je przed wysyłką.</p></details>
  <details><summary>Co, jeśli coś mi nie pasuje?</summary><p>Masz jedną bezpłatną poprawkę w ciągu 7 dni od dostawy.</p></details>
  <details><summary>Do czego mogę użyć gotowego pliku?</summary><p>Do użytku prywatnego, np. jako prezent lub żart. Publikacja komercyjna wymaga zgody.</p></details>
</section>
</div>
<?php get_footer();
