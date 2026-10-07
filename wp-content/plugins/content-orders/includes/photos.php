<?php
if (!defined('ABSPATH')) exit;

/* Zdjęcia od klientów: wgrywane AJAX-em przed dodaniem do koszyka, przechowywane prywatnie. */

function co_uploads_dir() {
    $dir = co_private_dir() . '/uploads';
    if (!file_exists($dir)) wp_mkdir_p($dir);
    return $dir;
}

function co_photo_path($token) {
    return preg_match('/^[a-f0-9]{32}$/', (string) $token) ? co_uploads_dir() . '/' . $token . '.jpg' : '';
}

function co_photo_exists($token) {
    $p = co_photo_path($token);
    return $p !== '' && is_file($p);
}

add_action('wp_ajax_co_upload', 'co_ajax_upload');
add_action('wp_ajax_nopriv_co_upload', 'co_ajax_upload');
function co_ajax_upload() {
    check_ajax_referer('co_upload', 'nonce');
    $k = 'co_up_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
    $n = (int) get_transient($k);
    if ($n >= 10) wp_send_json_error('Za dużo zdjęć w krótkim czasie. Spróbuj za kilka minut.');
    set_transient($k, $n + 1, 10 * MINUTE_IN_SECONDS);

    $f = $_FILES['photo'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) wp_send_json_error('Nie udało się wgrać pliku.');
    if ($f['size'] > 8 * 1024 * 1024) wp_send_json_error('Zdjęcie jest za duże (maksymalnie 8 MB).');
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) wp_send_json_error('Dozwolone są pliki JPG, PNG i WebP.');
    if ($info[0] * $info[1] > 40000000) wp_send_json_error('Zdjęcie ma zbyt dużą rozdzielczość.');
    if ($info[0] < 300 || $info[1] < 300) wp_send_json_error('Zdjęcie jest za małe (minimum 300 px).');

    $src = $info[2] === IMAGETYPE_JPEG ? @imagecreatefromjpeg($f['tmp_name'])
        : ($info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($f['tmp_name']) : @imagecreatefromwebp($f['tmp_name']));
    if (!$src) wp_send_json_error('Nie udało się odczytać zdjęcia.');

    // Obrót według EXIF (zdjęcia z telefonu)
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($f['tmp_name']);
        $o = $exif['Orientation'] ?? 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($angle) $src = imagerotate($src, $angle, 0);
    }
    // Przeskalowanie (maks. 1600 px) i zapis od nowa jako JPEG. To usuwa metadane (np. lokalizację GPS).
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, 1600 / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $token = bin2hex(random_bytes(16));
    imagejpeg($dst, co_photo_path($token), 88);

    // miniatura do podglądu
    $tw = 240;
    $th = (int) round($nh * $tw / $nw);
    $thumb = imagecreatetruecolor($tw, $th);
    imagecopyresampled($thumb, $dst, 0, 0, 0, 0, $tw, $th, $nw, $nh);
    ob_start();
    imagejpeg($thumb, null, 80);
    $jpeg = ob_get_clean();
    wp_send_json_success(['token' => $token, 'thumb' => 'data:image/jpeg;base64,' . base64_encode($jpeg)]);
}

/* Kod JS dla pól typu "photo" */
add_action('wp_footer', function () {
    if (!function_exists('is_product') || !is_product()) return;
    ?>
<script>
document.addEventListener('change', function (e) {
  var inp = e.target.closest('.co-photo-file');
  if (!inp || !inp.files.length) return;
  var wrap = inp.closest('label'), status = wrap.querySelector('.co-photo-status'),
      tok = wrap.querySelector('.co-photo-token'), img = wrap.querySelector('.co-photo-thumb'), fd = new FormData();
  fd.append('action', 'co_upload'); fd.append('nonce', inp.dataset.nonce); fd.append('photo', inp.files[0]);
  status.textContent = 'Wgrywam zdjęcie...'; tok.value = '';
  fetch(inp.dataset.url, {method: 'POST', body: fd}).then(function (r) { return r.json(); }).then(function (j) {
    if (j.success) { tok.value = j.data.token; img.src = j.data.thumb; img.style.display = 'block'; status.textContent = 'Zdjęcie dodane.'; }
    else { status.textContent = j.data || 'Nie udało się.'; img.style.display = 'none'; }
  }).catch(function () { status.textContent = 'Nie udało się wgrać zdjęcia.'; });
});
</script>
    <?php
});

/* Podgląd zdjęcia w panelu zlecenia (tylko dla zalogowanych z uprawnieniami) */
add_action('admin_post_co_photo', function () {
    $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
    check_admin_referer('co_photo_' . $token);
    if (!current_user_can('edit_posts') || !co_photo_exists($token)) wp_die('Brak dostępu.', '', 403);
    nocache_headers();
    header('Content-Type: image/jpeg');
    readfile(co_photo_path($token));
    exit;
});

/* Zdjęcia kasujemy po 30 dniach */
add_action('init', function () {
    if (!wp_next_scheduled('co_cleanup_uploads')) wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'co_cleanup_uploads');
});
add_action('co_cleanup_uploads', function () {
    foreach (glob(co_uploads_dir() . '/*.jpg') ?: [] as $file) {
        if (filemtime($file) < time() - 30 * DAY_IN_SECONDS) @unlink($file);
    }
});
