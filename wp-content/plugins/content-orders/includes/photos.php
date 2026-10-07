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
<style>
.co-crop{position:fixed;inset:0;background:rgba(16,14,36,.72);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px}
.co-crop-box{background:#fff;border-radius:16px;padding:18px;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,.4);font:15px/1.4 system-ui,sans-serif;color:#1d1b3a}
.co-crop-box h3{margin:0 0 4px;font-size:1.1rem}.co-crop-box p{margin:0 0 12px;color:#6b6985;font-size:.88rem}
.co-crop canvas{width:100%;aspect-ratio:1/1;border-radius:10px;background:#111;touch-action:none;cursor:grab;display:block}
.co-crop input[type=range]{width:100%;margin:12px 0 4px}
.co-crop-actions{display:flex;gap:10px;margin-top:10px}
.co-crop-actions button{flex:1;padding:12px;border-radius:10px;border:1px solid #d6d2ee;background:#fff;font:inherit;font-weight:700;cursor:pointer}
.co-crop-actions button.go{background:#ff6b5a;border-color:#ff6b5a;color:#fff}
</style>
<script>
(function () {
  function upload(inp, blob, name) {
    var wrap = inp.closest('label'), status = wrap.querySelector('.co-photo-status'),
        tok = wrap.querySelector('.co-photo-token'), img = wrap.querySelector('.co-photo-thumb'), fd = new FormData();
    fd.append('action', 'co_upload'); fd.append('nonce', inp.dataset.nonce); fd.append('photo', blob, name || 'foto.jpg');
    status.textContent = 'Wgrywam zdjęcie...'; tok.value = '';
    fetch(inp.dataset.url, {method: 'POST', body: fd}).then(function (r) { return r.json(); }).then(function (j) {
      if (j.success) { tok.value = j.data.token; img.src = j.data.thumb; img.style.display = 'block'; status.textContent = 'Zdjęcie dodane.'; }
      else { status.textContent = j.data || 'Nie udało się.'; img.style.display = 'none'; }
    }).catch(function () { status.textContent = 'Nie udało się wgrać zdjęcia.'; });
  }

  function openCropper(inp, file) {
    var url = URL.createObjectURL(file), im = new Image();
    im.onerror = function () { URL.revokeObjectURL(url); upload(inp, file, file.name); }; // np. format nieobsługiwany w przeglądarce: wysyłamy bez kadrowania
    im.onload = function () {
      var S = 640, z = 1, ox = 0, oy = 0, iw = im.naturalWidth, ih = im.naturalHeight, base = Math.max(1, 1) * Math.max(S / iw, S / ih);
      var o = document.createElement('div'); o.className = 'co-crop';
      o.innerHTML = '<div class="co-crop-box" role="dialog" aria-modal="true"><h3>Ustaw kadr</h3><p>Przesuń zdjęcie i przybliż je tak, żeby zwierzak był na środku, a cała głowa w kadrze.</p><canvas width="' + S + '" height="' + S + '"></canvas><input type="range" min="1" max="4" step="0.01" value="1" aria-label="Przybliżenie"><div class="co-crop-actions"><button type="button" class="cancel">Anuluj</button><button type="button" class="go">Użyj kadru</button></div></div>';
      document.body.appendChild(o);
      var cv = o.querySelector('canvas'), ctx = cv.getContext('2d'), rg = o.querySelector('input');
      function clamp() { var w = iw * base * z, h = ih * base * z; ox = Math.min(0, Math.max(S - w, ox)); oy = Math.min(0, Math.max(S - h, oy)); }
      function draw(c, k) { c.fillStyle = '#111'; c.fillRect(0, 0, S * k, S * k); c.drawImage(im, ox * k, oy * k, iw * base * z * k, ih * base * z * k); }
      function paint() {
        clamp(); draw(ctx, 1);
        ctx.strokeStyle = 'rgba(255,255,255,.35)'; ctx.lineWidth = 2; ctx.beginPath();
        [1, 2].forEach(function (i) { ctx.moveTo(S * i / 3, 0); ctx.lineTo(S * i / 3, S); ctx.moveTo(0, S * i / 3); ctx.lineTo(S, S * i / 3); }); ctx.stroke();
      }
      ox = (S - iw * base) / 2; oy = (S - ih * base) / 2; paint();
      function setZoom(nz) { var cx = (S / 2 - ox) / (iw * base * z), cy = (S / 2 - oy) / (ih * base * z); z = Math.min(4, Math.max(1, nz)); ox = S / 2 - cx * iw * base * z; oy = S / 2 - cy * ih * base * z; rg.value = z; paint(); }
      rg.addEventListener('input', function () { setZoom(parseFloat(rg.value)); });
      cv.addEventListener('wheel', function (e) { e.preventDefault(); setZoom(z * (e.deltaY < 0 ? 1.08 : 0.93)); }, {passive: false});
      var drag = null;
      cv.addEventListener('pointerdown', function (e) { drag = {x: e.clientX, y: e.clientY, ox: ox, oy: oy}; cv.setPointerCapture(e.pointerId); cv.style.cursor = 'grabbing'; });
      cv.addEventListener('pointermove', function (e) { if (!drag) return; var k = S / cv.getBoundingClientRect().width; ox = drag.ox + (e.clientX - drag.x) * k; oy = drag.oy + (e.clientY - drag.y) * k; paint(); });
      cv.addEventListener('pointerup', function () { drag = null; cv.style.cursor = 'grab'; });
      function close() { document.body.removeChild(o); URL.revokeObjectURL(url); inp.value = ''; }
      o.querySelector('.cancel').addEventListener('click', close);
      o.querySelector('.go').addEventListener('click', function () {
        var out = document.createElement('canvas'); out.width = out.height = 1000; var c2 = out.getContext('2d'), k = 1000 / S;
        c2.fillStyle = '#111'; c2.fillRect(0, 0, 1000, 1000); c2.drawImage(im, ox * k, oy * k, iw * base * z * k, ih * base * z * k);
        out.toBlob(function (b) { close(); upload(inp, b, 'kadr.jpg'); }, 'image/jpeg', 0.92);
      });
    };
    im.src = url;
  }

  document.addEventListener('change', function (e) {
    var inp = e.target.closest('.co-photo-file');
    if (!inp || !inp.files.length) return;
    openCropper(inp, inp.files[0]);
  });
})();
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
