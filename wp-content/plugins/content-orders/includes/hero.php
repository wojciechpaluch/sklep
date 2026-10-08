<?php
if (!defined('ABSPATH')) exit;

/* Panel: przykład "przed / po" na stronie głównej. Wgrywasz własne zdjęcie, a wersję "po" można wgrać
 * własną (np. z innego narzędzia) albo pozwolić wtyczce zrobić postać z gry 2D. */

function co_hero_load_image($tmp) {
    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return null;
    $src = $info[2] === IMAGETYPE_JPEG ? @imagecreatefromjpeg($tmp) : ($info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($tmp) : @imagecreatefromwebp($tmp));
    if (!$src) return null;
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $angle = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($angle) $src = imagerotate($src, $angle, 0);
    }
    return $src;
}

/* Wycina kwadrat ze środka i zapisuje jako JPEG (nowy plik bez metadanych, w tym lokalizacji) */
function co_hero_square_jpeg($im, $size = 1000) {
    $w = imagesx($im); $h = imagesy($im); $s = min($w, $h);
    $out = imagecreatetruecolor($size, $size);
    imagecopyresampled($out, $im, 0, 0, (int) (($w - $s) / 2), (int) (($h - $s) / 2), $size, $size, $s, $s);
    ob_start(); imagejpeg($out, null, 90); $j = ob_get_clean();
    imagedestroy($out);
    return $j;
}

add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=co_job', 'Przykład na stronie głównej', 'Przykład "przed/po"', 'manage_options', 'co-hero', function () {
        $b = (int) get_theme_mod('dedykowana_hero_before'); $a = (int) get_theme_mod('dedykowana_hero_after');
        echo '<div class="wrap"><h1>Przykład „przed / po" na stronie głównej</h1>';
        if (!empty($_GET['saved'])) echo '<div class="notice notice-success"><p>Zapisano. Sprawdź stronę główną.</p></div>';
        if (!empty($_GET['err'])) echo '<div class="notice notice-error"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['err']))) . '</p></div>';
        echo '<p>Wgraj zdjęcie własnego zwierzaka (najlepiej kwadratowe, z głową na środku). Nie wgrywaj zdjęć osób ani cudzych zwierząt bez zgody właściciela. Wersję „po" możesz wgrać własną (np. z innego narzędzia) albo zostawić puste, a wtyczka zrobi z tego zdjęcia postać z gry 2D.</p>';
        echo '<p><strong>Teraz na stronie:</strong> ' . ($b ? wp_get_attachment_image($b, [90, 90]) : '—') . ' → ' . ($a ? wp_get_attachment_image($a, [90, 90]) : '—') . '</p>';
        echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="co_hero">';
        wp_nonce_field('co_hero');
        echo '<table class="form-table"><tr><th>Zdjęcie „przed" (wymagane)</th><td><input type="file" name="before" accept="image/jpeg,image/png,image/webp"></td></tr>';
        echo '<tr><th>Wersja „po" (opcjonalnie)</th><td><input type="file" name="after" accept="image/jpeg,image/png,image/webp"><p class="description">Puste = wtyczka wygeneruje postać z gry 2D ze zdjęcia „przed".</p></td></tr>';
        echo '<tr><th>Podpis „przed"</th><td><input type="text" name="cap_before" class="regular-text" value="' . esc_attr(get_theme_mod('dedykowana_cap_before', 'Zdjęcie')) . '"></td></tr>';
        echo '<tr><th>Podpis „po"</th><td><input type="text" name="cap_after" class="regular-text" value="' . esc_attr(get_theme_mod('dedykowana_cap_after', 'Postać z gry 2D')) . '"></td></tr></table>';
        submit_button('Zapisz przykład');
        echo '</form></div>';
    });
});

add_action('admin_post_co_hero', function () {
    if (!current_user_can('manage_options')) wp_die('Brak uprawnień.');
    check_admin_referer('co_hero');
    $back = function ($args) { wp_safe_redirect(add_query_arg($args, admin_url('edit.php?post_type=co_job&page=co-hero'))); exit; };
    $bf = $_FILES['before'] ?? null;
    if (!$bf || $bf['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($bf['tmp_name'])) $back(['err' => 'Wgraj zdjęcie „przed".']);
    if ($bf['size'] > 12 * 1024 * 1024) $back(['err' => 'Zdjęcie jest za duże (maksymalnie 12 MB).']);
    $before = co_hero_load_image($bf['tmp_name']);
    if (!$before) $back(['err' => 'Dozwolone są pliki JPG, PNG i WebP.']);
    $before_jpeg = co_hero_square_jpeg($before);

    $af = $_FILES['after'] ?? null;
    if ($af && $af['error'] === UPLOAD_ERR_OK && is_uploaded_file($af['tmp_name'])) {
        $im = co_hero_load_image($af['tmp_name']);
        if (!$im) $back(['err' => 'Plik „po" ma nieobsługiwany format.']);
        $after_jpeg = co_hero_square_jpeg($im);
    } else {
        $tmp = wp_tempnam('co-hero.jpg'); file_put_contents($tmp, $before_jpeg);
        $sp = co_pixel_sprite($tmp, 40, 12, 12); @unlink($tmp);
        if (!$sp) $back(['err' => 'Nie udało się zrobić wersji „po".']);
        ob_start(); imagejpeg($sp, null, 92); $after_jpeg = ob_get_clean();
    }
    $keep = co_private_dir() . '/example-photo.jpg'; // źródło dla przykładów produktów
    file_put_contents($keep, $before_jpeg);
    update_option('co_example_photo_file', $keep);
    $id1 = co_sample_image(0, 'hero-before.jpg', $before_jpeg, 'Przykład: przed');
    $id2 = co_sample_image(0, 'hero-after.jpg', $after_jpeg, 'Przykład: po');
    if (!$id1 || !$id2) $back(['err' => 'Nie udało się zapisać obrazów w mediach.']);
    set_theme_mod('dedykowana_hero_before', $id1); set_theme_mod('dedykowana_hero_after', $id2);
    set_theme_mod('dedykowana_cap_before', sanitize_text_field(wp_unslash($_POST['cap_before'] ?? 'Zdjęcie')));
    set_theme_mod('dedykowana_cap_after', sanitize_text_field(wp_unslash($_POST['cap_after'] ?? 'Postać z gry 2D')));
    co_refresh_samples(); // wszystkie produkty dostają przykład z tego samego zdjęcia
    $back(['saved' => 1]);
});
