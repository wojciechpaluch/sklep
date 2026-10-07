<?php
if (!defined('ABSPATH')) exit;

/* ---------------- Zwierzak w grze 2D (piksel-art) ----------------
 * Zdjęcie -> siatka pikseli -> własna paleta (k-means) -> ekran postaci jak z gry 2D.
 * Całość liczy się lokalnie na serwerze (GD), bez zewnętrznych usług.
 */

function co_pixel_classes() {
    return ['Strażnik Kanapy', 'Łowca Smaczków', 'Mag Drzemek', 'Zwiadowca Spacerów', 'Obrońca Miski', 'Łotrzyk Skarpetek'];
}
function co_pixel_skills() {
    return ['Spojrzenie Psich Oczu', 'Szarża o Świcie', 'Zaklęcie Głaskania', 'Atak Mokrym Nosem', 'Skok na Kanapę', 'Krzyk o Jedzenie'];
}

function co_pixel_sprite($path, $grid = 40, $colors = 12, $scale = 10) {
    $src = @imagecreatefromstring(file_get_contents($path));
    if (!$src) return null;
    $w = imagesx($src); $h = imagesy($src); $s = min($w, $h);
    $small = imagecreatetruecolor($grid, $grid);
    imagecopyresampled($small, $src, 0, 0, (int) (($w - $s) / 2), (int) (($h - $s) / 2), $grid, $grid, $s, $s);
    imagedestroy($src);
    $px = [];
    for ($y = 0; $y < $grid; $y++) for ($x = 0; $x < $grid; $x++) {
        $c = imagecolorat($small, $x, $y); $px[] = [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
    }
    imagedestroy($small);
    // k-means na kolorach pikseli
    $n = count($px); $cent = [];
    for ($i = 0; $i < $colors; $i++) $cent[] = $px[(int) ($i * $n / $colors + $n / (2 * $colors)) % $n];
    for ($it = 0; $it < 10; $it++) {
        $sum = array_fill(0, $colors, [0, 0, 0, 0]);
        foreach ($px as $p) {
            $b = co_pixel_nearest($p, $cent);
            $sum[$b][0] += $p[0]; $sum[$b][1] += $p[1]; $sum[$b][2] += $p[2]; $sum[$b][3]++;
        }
        foreach ($sum as $i => $s2) if ($s2[3]) $cent[$i] = [$s2[0] / $s2[3], $s2[1] / $s2[3], $s2[2] / $s2[3]];
    }
    foreach ($cent as $i => $c) { // żywsze kolory, jak w grach
        $g = ($c[0] + $c[1] + $c[2]) / 3;
        $cent[$i] = [max(0, min(255, $g + ($c[0] - $g) * 1.25)), max(0, min(255, $g + ($c[1] - $g) * 1.25)), max(0, min(255, $g + ($c[2] - $g) * 1.25))];
    }
    $im = imagecreatetruecolor($grid * $scale, $grid * $scale);
    $alloc = [];
    foreach ($px as $i => $p) {
        $ci = co_pixel_nearest($p, $cent);
        if (!isset($alloc[$ci])) $alloc[$ci] = imagecolorallocate($im, (int) $cent[$ci][0], (int) $cent[$ci][1], (int) $cent[$ci][2]);
        $x = $i % $grid; $y = intdiv($i, $grid);
        imagefilledrectangle($im, $x * $scale, $y * $scale, ($x + 1) * $scale - 1, ($y + 1) * $scale - 1, $alloc[$ci]);
    }
    return $im;
}

function co_pixel_nearest($p, $cent) {
    $best = 0; $bd = 1e12;
    foreach ($cent as $i => $c) {
        $d = ($p[0] - $c[0]) ** 2 + ($p[1] - $c[1]) ** 2 + ($p[2] - $c[2]) ** 2;
        if ($d < $bd) { $bd = $d; $best = $i; }
    }
    return $best;
}

/** Zwraca binarnie PNG (finalny plik) albo JPEG (podgląd z znakiem wodnym). */
function co_render_pixel($photo_path, $name, $class, $skill, $width = 900, $preview = false) {
    $bold = CO_DIR . 'fonts/DejaVuSans-Bold.ttf';
    $S = $width / 900; $u = function ($v) use ($S) { return (int) round($v * $S); };
    $im = imagecreatetruecolor($width, $width);
    imageantialias($im, true);
    $bg = imagecolorallocate($im, 24, 20, 48); $panel = imagecolorallocate($im, 40, 34, 78); $edge = imagecolorallocate($im, 255, 214, 64);
    $white = imagecolorallocate($im, 245, 245, 255); $mut = imagecolorallocate($im, 160, 156, 205); $dark = imagecolorallocate($im, 20, 16, 40);
    $chk = imagecolorallocate($im, 28, 24, 56);
    imagefill($im, 0, 0, $bg);
    $t = max(1, $u(30));
    for ($y = 0; $y < $width; $y += $t) for ($x = 0; $x < $width; $x += $t) if ((int) (($x + $y) / $t) % 2 === 0) imagefilledrectangle($im, $x, $y, $x + $t - 1, $y + $t - 1, $chk);

    $name = mb_strtoupper(trim($name) !== '' ? $name : 'BUREK');
    $seed = crc32($name . '|' . $class);
    $lvl = 5 + $seed % 38;
    $stat = function ($salt, $lo, $hi) use ($seed) { return $lo + (crc32($seed . $salt) % ($hi - $lo + 1)); };
    $bars = [['HP', $stat('hp', 70, 99), [255, 70, 90]], ['MP', $stat('mp', 40, 90), [70, 150, 255]], ['SIŁA', $stat('sila', 45, 95), [255, 160, 40]], ['SPRYT', $stat('spryt', 45, 95), [90, 220, 120]], ['SŁODYCZ', $stat('slod', 85, 100), [255, 120, 200]]];

    imagefilledrectangle($im, $u(40), $u(40), $u(860), $u(860), $edge);
    imagefilledrectangle($im, $u(52), $u(52), $u(848), $u(848), $panel);
    imagettftext($im, max(8, $u(22)), 0, $u(80), $u(100), $edge, $bold, 'WYBIERZ POSTAĆ');
    imagettftext($im, max(8, $u(22)), 0, $u(650), $u(100), $mut, $bold, 'LV ' . $lvl);

    // sprite: zdjęcie klienta lub przykład
    $path = ($photo_path && is_file($photo_path)) ? $photo_path : co_sample_pet_photo_path();
    $sp = co_pixel_sprite($path, 40, 12, 10);
    imagefilledrectangle($im, $u(76), $u(126), $u(484), $u(534), $edge);
    if ($sp) { imagecopyresampled($im, $sp, $u(80), $u(130), 0, 0, $u(400), $u(400), 400, 400); imagedestroy($sp); }

    // imię (dopasowane do szerokości)
    $size = $u(34); $maxw = $u(330);
    while ($size > 10 && co_text_width($size, $bold, $name) > $maxw) $size--;
    imagettftext($im, $size, 0, $u(510), $u(175), $white, $bold, $name);
    imagettftext($im, max(8, $u(16)), 0, $u(510), $u(205), $mut, $bold, mb_strtoupper($class));
    $y = $u(250);
    foreach ($bars as [$l, $v, $rgb]) {
        imagettftext($im, max(8, $u(15)), 0, $u(510), $y + $u(16), $white, $bold, $l);
        imagefilledrectangle($im, $u(640), $y, $u(830), $y + $u(20), $dark);
        imagefilledrectangle($im, $u(642), $y + $u(2), $u(642) + (int) ($u(186) * $v / 100), $y + $u(18), imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]));
        $y += $u(52);
    }
    imagettftext($im, max(8, $u(15)), 0, $u(510), $u(540), $edge, $bold, 'UMIEJĘTNOŚĆ SPECJALNA');
    $ss = $u(20); while ($ss > 9 && co_text_width($ss, $bold, $skill) > $u(330)) $ss--;
    imagettftext($im, $ss, 0, $u(510), $u(575), $white, $bold, $skill);

    imagefilledrectangle($im, $u(80), $u(620), $u(820), $u(810), $dark);
    imagettftext($im, max(8, $u(15)), 0, $u(100), $u(655), $edge, $bold, 'EKWIPUNEK');
    foreach (['KOŚĆ', 'PIŁKA', 'SMACZEK', 'MISKA'] as $i => $it) {
        $x = $u(108) + $i * $u(180);
        imagefilledrectangle($im, $x, $u(680), $x + $u(150), $u(790), $panel);
        imagettftext($im, max(8, $u(15)), 0, $x + $u(12), $u(742), $white, $bold, $it);
    }
    imagettftext($im, max(7, $u(12)), 0, $u(80), $u(842), $mut, $bold, 'POSTAĆ Z GRY, KTÓREJ NIE MA · ŻART, NIE PRAWDZIWA GRA');

    if ($preview) {
        $wm = imagecolorallocatealpha($im, 255, 255, 255, 96);
        imagettftext($im, $u(150), 40, $u(70), $u(760), $wm, $bold, 'PODGLĄD');
    }
    ob_start();
    if ($preview) imagejpeg($im, null, 88); else imagepng($im, null, 6);
    $out = ob_get_clean();
    imagedestroy($im);
    return $out;
}

class CO_Generator_Pixel extends CO_Generator {
    public function id() { return 'pixel_pet'; }
    public function label() { return 'Zwierzak w grze 2D (automatyczny)'; }
    public function is_automatic() { return true; }
    public function auto_deliver() { return true; }
    public function has_preview() { return function_exists('imagettftext'); }
    public function fields() {
        return [
            'photo' => ['label' => 'Zdjęcie zwierzaka (sam zwierzak na środku kadru, bez ludzi, najlepiej jednolite tło)', 'type' => 'photo'],
            'name'  => ['label' => 'Imię postaci', 'type' => 'text'],
            'class' => ['label' => 'Klasa postaci', 'type' => 'select', 'options' => co_pixel_classes()],
            'skill' => ['label' => 'Umiejętność specjalna', 'type' => 'select', 'options' => co_pixel_skills()],
        ];
    }
    public function clean($key, $val) { return $key === 'name' ? co_clean_name($val, 18) : $val; }
    public function generate(array $input, $job_id) {
        if (!$this->has_preview()) return new WP_Error('co_gd', 'Na serwerze brakuje biblioteki GD z FreeType.');
        $path = co_photo_path($input['photo'] ?? '');
        if (!$path || !is_file($path)) return new WP_Error('co_nophoto', 'Zdjęcie wygasło lub nie istnieje. Poproś klienta o nowe.');
        return ['file' => ['name' => 'zwierzak-w-grze-2d.png', 'data' => co_render_pixel($path, $input['name'], $input['class'], $input['skill'], 1200, false)]];
    }
    public function preview(array $input) {
        return co_render_pixel(co_photo_path($input['photo'] ?? ''), ($input['name'] ?? '') ?: 'Burek', $input['class'] ?? co_pixel_classes()[0], $input['skill'] ?? co_pixel_skills()[0], 600, true);
    }
}

add_action('content_orders_register', function ($register) {
    $register(new CO_Generator_Pixel());
});
