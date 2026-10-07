<?php
if (!defined('ABSPATH')) exit;

/** Wkleja zdjęcie (JPEG) w prostokąt, kadrując "cover". $sepia: efekt starej fotografii. */
function co_photo_cover($im, $path, $x, $y, $w, $h, $sepia = false) {
    if (!$path || !is_file($path)) return false;
    $src = @imagecreatefromjpeg($path);
    if (!$src) return false;
    if ($sepia) {
        imagefilter($src, IMG_FILTER_GRAYSCALE);
        imagefilter($src, IMG_FILTER_COLORIZE, 90, 60, 30);
    }
    $sw = imagesx($src); $sh = imagesy($src);
    $ratio = $w / $h;
    if ($sw / $sh > $ratio) { $cw = (int) ($sh * $ratio); $ch = $sh; $cx = (int) (($sw - $cw) / 2); $cy = 0; }
    else { $cw = $sw; $ch = (int) ($sw / $ratio); $cx = 0; $cy = (int) (($sh - $ch) / 2); }
    imagecopyresampled($im, $src, $x, $y, $cx, $cy, $w, $h, $cw, $ch);
    imagedestroy($src);
    return true;
}

/** Wkleja zdjęcie w kole o środku ($cx,$cy) i promieniu $r. */
function co_photo_circle($im, $path, $cx, $cy, $r) {
    $d = $r * 2;
    $tmp = imagecreatetruecolor($d, $d);
    if (!co_photo_cover($tmp, $path, 0, 0, $d, $d)) { imagedestroy($tmp); return false; }
    for ($yy = 0; $yy < $d; $yy++) {
        for ($xx = 0; $xx < $d; $xx++) {
            if (($xx - $r) * ($xx - $r) + ($yy - $r) * ($yy - $r) <= $r * $r) {
                imagesetpixel($im, $cx - $r + $xx, $cy - $r + $yy, imagecolorat($tmp, $xx, $yy));
            }
        }
    }
    imagedestroy($tmp);
    return true;
}

function co_clean_name($val, $max = 30) {
    return mb_substr(trim(preg_replace("/[^\p{L}\p{N} \-\.']/u", '', $val)), 0, $max);
}

/* ---------------- List gończy ---------------- */

function co_wanted_crimes() {
    return ['Kradzież kanapek', 'Zrzucanie przedmiotów ze stołu', 'Nocne podjadanie', 'Uporczywe wyłudzanie głaskania', 'Zakłócanie ciszy nocnej', 'Niszczenie poduszek'];
}
function co_wanted_rewards() {
    return ['Garść smaczków', 'Dużo głaskania', 'Spacer dookoła bloku', 'Kawałek szynki'];
}

function co_render_wanted($photo_path, $name, $crime, $reward, $width = 1200, $preview = false) {
    $bw = 1200; $bh = 1800;
    $s = $width / $bw;
    $w = (int) round($width); $h = (int) round($bh * $s);
    $u = function ($v) use ($s) { return (int) round($v * $s); };
    $bold = CO_DIR . 'fonts/DejaVuSerif-Bold.ttf';
    $reg = CO_DIR . 'fonts/DejaVuSerif.ttf';

    $im = imagecreatetruecolor($w, $h);
    imageantialias($im, true);
    $paper = imagecolorallocate($im, 236, 219, 178);
    $brown = imagecolorallocate($im, 92, 60, 30);
    $dark = imagecolorallocate($im, 58, 38, 20);
    imagefilledrectangle($im, 0, 0, $w, $h, $paper);
    $t = max(2, $u(10));
    imagefilledrectangle($im, $u(40), $u(40), $w - $u(40), $h - $u(40), $brown);
    imagefilledrectangle($im, $u(40) + $t, $u(40) + $t, $w - $u(40) - $t, $h - $u(40) - $t, $paper);
    imagerectangle($im, $u(70), $u(70), $w - $u(70), $h - $u(70), $brown);

    $cx = (int) ($w / 2);
    co_draw_centered($im, $cx, $u(260), $u(130), $bold, $dark, 'POSZUKIWANY', $u(1000), $u(6));
    co_draw_centered($im, $cx, $u(325), $u(36), $reg, $brown, 'żywy, najlepiej przytulony');

    $px = $u(220); $py = $u(370); $pw = $u(760); $ph = $u(760);
    imagefilledrectangle($im, $px - $u(12), $py - $u(12), $px + $pw + $u(12), $py + $ph + $u(12), $brown);
    if (!co_photo_cover($im, $photo_path, $px, $py, $pw, $ph, true)) {
        $sep = imagecolorallocate($im, 205, 180, 130);
        imagefilledrectangle($im, $px, $py, $px + $pw, $py + $ph, $sep);
        imagefilledellipse($im, $cx, $py + (int) ($ph * 0.7), $u(380), $u(340), $brown);
        imagefilledellipse($im, $cx, $py + (int) ($ph * 0.32), $u(240), $u(240), $brown);
    }

    co_draw_centered($im, $cx, $u(1260), $u(100), $bold, $dark, $name, $u(1000));
    co_draw_centered($im, $cx, $u(1340), $u(30), $reg, $brown, 'POSZUKIWANY ZA', 0, $u(6));
    $y = $u(1405);
    foreach (array_slice(co_wrap_text($crime, $u(54), $bold, $u(980)), 0, 2) as $line) {
        co_draw_centered($im, $cx, $y, $u(54), $bold, $dark, $line);
        $y += $u(68);
    }
    co_draw_centered($im, $cx, $u(1560), $u(30), $reg, $brown, 'NAGRODA', 0, $u(6));
    co_draw_centered($im, $cx, $u(1625), $u(58), $bold, $dark, $reward, $u(980));
    co_draw_centered($im, $cx, $u(1695), $u(24), $reg, $brown, 'FIKCYJNY DOKUMENT · ŻART · NIE JEST PISMEM URZĘDOWYM', $u(960), $u(2));

    if ($preview) {
        $wm = imagecolorallocatealpha($im, 90, 60, 30, 100);
        imagettftext($im, $u(200), 55, $u(120), $u(1350), $wm, $bold, 'PODGLĄD');
    }
    ob_start();
    imagejpeg($im, null, 90);
    $jpeg = ob_get_clean();
    imagedestroy($im);
    return $jpeg;
}

class CO_Generator_Wanted extends CO_Generator {
    public function id() { return 'wanted'; }
    public function label() { return 'List gończy ze zdjęciem (automatyczny)'; }
    public function is_automatic() { return true; }
    public function auto_deliver() { return true; }
    public function has_preview() { return function_exists('imagettftext'); }
    public function fields() {
        return [
            'photo'  => ['label' => 'Zdjęcie poszukiwanego zwierzaka (bez ludzi na zdjęciu)', 'type' => 'photo'],
            'name'   => ['label' => 'Imię', 'type' => 'text'],
            'crime'  => ['label' => 'Poszukiwany za', 'type' => 'select', 'options' => co_wanted_crimes()],
            'reward' => ['label' => 'Nagroda', 'type' => 'select', 'options' => co_wanted_rewards()],
        ];
    }
    public function clean($key, $val) { return $key === 'name' ? co_clean_name($val) : $val; }
    public function generate(array $input, $job_id) {
        if (!$this->has_preview()) return new WP_Error('co_gd', 'Na serwerze brakuje biblioteki GD z FreeType.');
        $path = co_photo_path($input['photo'] ?? '');
        if (!$path || !is_file($path)) return new WP_Error('co_nophoto', 'Zdjęcie wygasło lub nie istnieje. Poproś klienta o nowe.');
        return ['file' => ['name' => 'list-goncz.jpg', 'data' => co_render_wanted($path, $input['name'], $input['crime'], $input['reward'], 1200, false)]];
    }
    public function preview(array $input) {
        return co_render_wanted(co_photo_path($input['photo'] ?? ''), ($input['name'] ?? '') ?: 'Burek', $input['crime'] ?? co_wanted_crimes()[0], $input['reward'] ?? co_wanted_rewards()[0], 600, true);
    }
}

/* ---------------- Karta kolekcjonerska ---------------- */

function co_card_types() {
    return ['Ogień' => [200, 70, 40], 'Woda' => [40, 110, 190], 'Natura' => [60, 150, 70], 'Cień' => [90, 70, 140]];
}
function co_card_attacks() {
    return ['Spojrzenie Psich Oczu', 'Zrzucenie Kubka ze Stołu', 'Mruczenie Dominacji', 'Szarża Przed Śniadaniem', 'Sen Wielkiej Drzemki'];
}

function co_render_card($photo_path, $name, $type, $attack, $width = 900, $preview = false) {
    $bw = 900; $bh = 1260;
    $s = $width / $bw;
    $w = (int) round($width); $h = (int) round($bh * $s);
    $u = function ($v) use ($s) { return (int) round($v * $s); };
    $bold = CO_DIR . 'fonts/DejaVuSans-Bold.ttf';
    $reg = CO_DIR . 'fonts/DejaVuSans.ttf';
    $types = co_card_types();
    $rgb = $types[$type] ?? $types['Ogień'];

    $im = imagecreatetruecolor($w, $h);
    imageantialias($im, true);
    $col = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    $cream = imagecolorallocate($im, 250, 246, 235);
    $ink = imagecolorallocate($im, 29, 27, 58);
    $muted = imagecolorallocate($im, 105, 110, 125);
    $grey = imagecolorallocate($im, 225, 222, 212);
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, $w, $h, $col);
    imagefilledrectangle($im, $u(28), $u(28), $w - $u(28), $h - $u(28), $cream);

    co_draw_left($im, $u(60), $u(112), $u(50), $bold, $ink, $name, $u(570));
    imagettftext($im, $u(26), 0, $w - $u(60) - co_text_width($u(26), $bold, mb_strtoupper($type)), $u(108), $col, $bold, mb_strtoupper($type));

    $px = $u(60); $py = $u(145); $pw = $u(780); $ph = $u(540);
    imagefilledrectangle($im, $px - $u(6), $py - $u(6), $px + $pw + $u(6), $py + $ph + $u(6), $col);
    if (!co_photo_cover($im, $photo_path, $px, $py, $pw, $ph)) {
        imagefilledrectangle($im, $px, $py, $px + $pw, $py + $ph, $grey);
        imagefilledellipse($im, (int) ($w / 2), $py + (int) ($ph * 0.65), $u(300), $u(260), $col);
        imagefilledellipse($im, (int) ($w / 2), $py + (int) ($ph * 0.3), $u(190), $u(190), $col);
    }
    imagettftext($im, $u(26), 0, $u(60), $u(735), $muted, $reg, 'LEGENDARNA  ★★★★★');

    imagettftext($im, $u(20), 0, $u(60), $u(800), $muted, $reg, 'ATAK SPECJALNY');
    co_draw_left($im, $u(60), $u(852), $u(40), $bold, $ink, $attack, $u(780));
    $hit = 80 + (abs(crc32($name . $attack)) % 20);
    imagettftext($im, $u(26), 0, $u(60), $u(895), $muted, $reg, 'Zadaje ' . $hit . ' punktów rozczulenia');

    $stats = ['SIŁA' => 's', 'SPRYT' => 'w', 'SŁODKOŚĆ' => 'u'];
    $y = $u(985);
    foreach ($stats as $label => $salt) {
        $val = 55 + (abs(crc32($name . $salt)) % 45);
        imagettftext($im, $u(26), 0, $u(60), $y, $ink, $bold, $label);
        $bx = $u(300); $bwid = $u(480);
        imagefilledrectangle($im, $bx, $y - $u(24), $bx + $bwid, $y + $u(6), $grey);
        imagefilledrectangle($im, $bx, $y - $u(24), $bx + (int) ($bwid * $val / 100), $y + $u(6), $col);
        imagettftext($im, $u(26), 0, $bx + $bwid + $u(14), $y, $ink, $bold, (string) $val);
        $y += $u(68);
    }
    imagettftext($im, $u(20), 0, $u(60), $h - $u(60), $muted, $reg, 'Karta nr 001/∞ · wydanie limitowane');

    if ($preview) {
        $wm = imagecolorallocatealpha($im, 80, 90, 110, 95);
        imagettftext($im, $u(150), 40, $u(100), $u(900), $wm, $bold, 'PODGLĄD');
    }
    ob_start();
    imagejpeg($im, null, 90);
    $jpeg = ob_get_clean();
    imagedestroy($im);
    return $jpeg;
}

class CO_Generator_TradingCard extends CO_Generator {
    public function id() { return 'trading_card'; }
    public function label() { return 'Karta kolekcjonerska ze zdjęciem (automatyczna)'; }
    public function is_automatic() { return true; }
    public function auto_deliver() { return true; }
    public function has_preview() { return function_exists('imagettftext'); }
    public function fields() {
        return [
            'photo'  => ['label' => 'Zdjęcie (zwierzak, bez ludzi na zdjęciu)', 'type' => 'photo'],
            'name'   => ['label' => 'Imię', 'type' => 'text'],
            'type'   => ['label' => 'Żywioł', 'type' => 'select', 'options' => array_keys(co_card_types())],
            'attack' => ['label' => 'Atak specjalny', 'type' => 'select', 'options' => co_card_attacks()],
        ];
    }
    public function clean($key, $val) { return $key === 'name' ? co_clean_name($val, 24) : $val; }
    public function generate(array $input, $job_id) {
        if (!$this->has_preview()) return new WP_Error('co_gd', 'Na serwerze brakuje biblioteki GD z FreeType.');
        $path = co_photo_path($input['photo'] ?? '');
        if (!$path || !is_file($path)) return new WP_Error('co_nophoto', 'Zdjęcie wygasło lub nie istnieje. Poproś klienta o nowe.');
        return ['file' => ['name' => 'karta-kolekcjonerska.jpg', 'data' => co_render_card($path, $input['name'], $input['type'], $input['attack'], 900, false)]];
    }
    public function preview(array $input) {
        return co_render_card(co_photo_path($input['photo'] ?? ''), ($input['name'] ?? '') ?: 'Burek', $input['type'] ?? 'Ogień', $input['attack'] ?? co_card_attacks()[0], 600, true);
    }
}

add_action('content_orders_register', function ($register) {
    $register(new CO_Generator_Wanted());
    $register(new CO_Generator_TradingCard());
});
