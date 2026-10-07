<?php
if (!defined('ABSPATH')) exit;

/* Przykładowe "zdjęcie" zwierzaka: narysowany pies (do podglądów produktów, gdy klient nie dodał zdjęcia). */
function co_sample_pet_photo_path() {
    $path = trailingslashit(sys_get_temp_dir()) . 'co-sample-dog.jpg';
    if (is_file($path)) return $path;
    $S = 2; $W = 800 * $S;
    $im = imagecreatetruecolor($W, $W);
    imageantialias($im, true);
    $c = function ($r, $g, $b) use ($im) { return imagecolorallocate($im, $r, $g, $b); };
    for ($y = 0; $y < $W; $y++) { // tło: delikatny gradient
        $t = $y / $W;
        imageline($im, 0, $y, $W, $y, $c((int) (214 - 24 * $t), (int) (236 - 18 * $t), (int) (244 - 10 * $t)));
    }
    $fur = $c(201, 143, 85); $fur_d = $c(150, 98, 55); $cream = $c(246, 228, 200); $ink = $c(40, 28, 24);
    $e = function ($x, $y, $w, $h, $col) use ($im, $S) { imagefilledellipse($im, (int) ($x * $S), (int) ($y * $S), (int) ($w * $S), (int) ($h * $S), $col); };
    $e(400, 800, 560, 360, $fur);                 // ramiona
    $e(400, 690, 300, 150, $cream);               // kołnierz
    $e(205, 330, 150, 290, $fur_d);               // uszy
    $e(595, 330, 150, 290, $fur_d);
    $e(400, 360, 400, 380, $fur);                 // głowa
    $e(400, 290, 120, 90, $c(214, 160, 104));     // łata na czole
    $e(400, 470, 250, 190, $cream);               // pysk
    $e(400, 556, 70, 56, $c(236, 120, 130));      // język
    $e(400, 535, 150, 46, $cream);
    $e(400, 425, 78, 54, $ink);                   // nos
    $e(388, 414, 22, 12, $c(120, 100, 96));
    imagesetthickness($im, 5 * $S);
    imagearc($im, (int) (370 * $S), (int) (490 * $S), 60 * $S, 50 * $S, 0, 180, $ink);
    imagearc($im, (int) (430 * $S), (int) (490 * $S), 60 * $S, 50 * $S, 0, 180, $ink);
    imageline($im, (int) (400 * $S), (int) (452 * $S), (int) (400 * $S), (int) (485 * $S), $ink);
    foreach ([-1, 1] as $d) {                     // oczy
        $e(400 + $d * 82, 345, 58, 70, $ink);
        $e(400 + $d * 82 + 8, 330, 20, 24, $c(255, 255, 255));
        $e(400 + $d * 82 - 8, 358, 9, 9, $c(255, 255, 255));
        $e(400 + $d * 82, 292, 70, 18, $fur_d);   // brwi
    }
    $out = imagecreatetruecolor(800, 800);
    imagecopyresampled($out, $im, 0, 0, 0, 0, 800, 800, $W, $W);
    imagejpeg($out, $path, 92);
    imagedestroy($im); imagedestroy($out);
    return $path;
}

/* Prezentacja produktu: grafika na jasnym tle z miękkim cieniem. Zwraca JPEG. */
function co_mockup($jpeg, $size = 1200, $fill = 0.8) {
    $src = imagecreatefromstring($jpeg);
    if (!$src) return $jpeg;
    $sw = imagesx($src); $sh = imagesy($src);
    $k = min($size * $fill / $sw, $size * $fill / $sh);
    $tw = (int) round($sw * $k); $th = (int) round($sh * $k);
    $x0 = (int) (($size - $tw) / 2); $y0 = (int) (($size - $th) / 2) - (int) ($size * 0.01);

    $im = imagecreatetruecolor($size, $size);
    imageantialias($im, true);
    for ($y = 0; $y < $size; $y++) {
        $t = $y / $size;
        imageline($im, 0, $y, $size, $y, imagecolorallocate($im, (int) (255 - 12 * $t), (int) (248 - 15 * $t), (int) (227 - 20 * $t)));
    }
    // cień liczony analitycznie na małej warstwie, potem skalowany
    $q = 4; $sz = (int) ($size / $q);
    $sh_l = imagecreatetruecolor($sz, $sz);
    imagealphablending($sh_l, false); imagesavealpha($sh_l, true);
    $cx = ($x0 + $tw / 2) / $q; $cy = ($y0 + $th / 2 + $size * 0.025) / $q;
    $hx = $tw / 2 / $q; $hy = $th / 2 / $q; $sg = $size * 0.012 / $q * 2.2;
    for ($y = 0; $y < $sz; $y++) {
        for ($x = 0; $x < $sz; $x++) {
            $fx = 1 / (1 + exp((abs($x - $cx) - $hx) / $sg));
            $fy = 1 / (1 + exp((abs($y - $cy) - $hy) / $sg));
            $a = (int) round(127 - 127 * 0.38 * $fx * $fy);
            imagesetpixel($sh_l, $x, $y, imagecolorallocatealpha($sh_l, 60, 45, 20, max(0, min(127, $a))));
        }
    }
    imagealphablending($im, true);
    imagecopyresampled($im, $sh_l, 0, 0, 0, 0, $size, $size, $sz, $sz);
    imagecopyresampled($im, $src, $x0, $y0, 0, 0, $tw, $th, $sw, $sh);
    ob_start(); imagejpeg($im, null, 92); $out = ob_get_clean();
    imagedestroy($im); imagedestroy($src); imagedestroy($sh_l);
    return $out;
}


/* Przykładowa kartka z wierszykiem (obrazek do sklepu) */
function co_render_sample_poem($w = 1200) {
    $bold = CO_DIR . 'fonts/DejaVuSerif-Bold.ttf'; $reg = CO_DIR . 'fonts/DejaVuSerif.ttf';
    $h = (int) ($w * 1.25);
    $im = imagecreatetruecolor($w, $h); imageantialias($im, true);
    $paper = imagecolorallocate($im, 252, 247, 236); $ink = imagecolorallocate($im, 52, 40, 88); $acc = imagecolorallocate($im, 214, 90, 70); $mut = imagecolorallocate($im, 110, 100, 130);
    imagefill($im, 0, 0, $paper);
    $t = max(3, (int) ($w * 0.008)); $m = (int) ($w * 0.05);
    imagefilledrectangle($im, $m, $m, $w - $m, $h - $m, $acc);
    imagefilledrectangle($im, $m + $t, $m + $t, $w - $m - $t, $h - $m - $t, $paper);
    $c = function ($txt, $y, $sz, $font, $col) use ($im, $w) { $b = imagettfbbox($sz, 0, $font, $txt); imagettftext($im, $sz, 0, (int) (($w - ($b[2] - $b[0])) / 2), $y, $col, $font, $txt); };
    $c('Wierszyk', (int) ($h * 0.22), (int) ($w * 0.07), $bold, $ink);
    $c('dla Marka', (int) ($h * 0.285), (int) ($w * 0.036), $reg, $mut);
    imagefilledrectangle($im, (int) ($w * 0.42), (int) ($h * 0.32), (int) ($w * 0.58), (int) ($h * 0.32) + $t, $acc);
    $y = (int) ($h * 0.42);
    foreach (['Marek dziś ma urodziny,', 'tort już czeka, nie ma zmiłuj,', 'wszyscy życzą mu zdrowia,', 'a on tylko: „Daj mi kawałek!”'] as $l) {
        $c($l, $y, (int) ($w * 0.034), $reg, $ink); $y += (int) ($h * 0.075);
    }
    $c('napisane specjalnie dla Ciebie', (int) ($h * 0.86), (int) ($w * 0.024), $reg, $mut);
    ob_start(); imagejpeg($im, null, 92); $j = ob_get_clean(); imagedestroy($im);
    return $j;
}

/* (Re)generuje zdjęcia przykładowe produktów ze sklepu. */
function co_refresh_samples() {
    if (!function_exists('imagettftext')) return 'brak GD/FreeType';
    $products = get_option('co_products', []);
    $dog = co_sample_pet_photo_path();
    $jobs = [
        'diploma' => function () { list($j) = co_render_diploma('Anna Kowalska', 'Królowa Kanapy', co_diploma_titles()['Królowa Kanapy'], 1600, false); return [$j, 'dyplom-przyklad.jpg', 'Przykładowy dyplom', 0.86]; },
        'pet'     => function () use ($dog) { return [co_render_pet_card($dog, 'Burek', 'Pies', co_pet_roles()[0], co_pet_default_trait('Pies'), 1400, false), 'legitymacja-przyklad.jpg', 'Przykładowa legitymacja zwierzaka', 0.86]; },
        'poem'    => function () { return [co_render_sample_poem(1200), 'wierszyk-przyklad.jpg', 'Przykładowy wierszyk', 0.82]; },
        'pixel'   => function () use ($dog) { return [co_render_pixel($dog, 'Burek', co_pixel_classes()[0], co_pixel_skills()[0], 1200, false), 'pixel-przyklad.jpg', 'Przykładowa postać z gry 2D', 0.9]; },
        'wanted'  => function () use ($dog) { return [co_render_wanted($dog, 'Burek', co_wanted_crimes()[0], co_wanted_rewards()[0], 1000, false), 'list-goncz-przyklad.jpg', 'Przykładowy list gończy', 0.9]; },
        'card'    => function () use ($dog) { return [co_render_card($dog, 'Burek', 'Ogień', co_card_attacks()[0], 1000, false), 'karta-przyklad.jpg', 'Przykładowa karta kolekcjonerska', 0.9]; },
    ];
    foreach ($jobs as $key => $fn) {
        if (empty($products[$key])) continue;
        list($jpeg, $file, $title, $fill) = $fn();
        $p = wc_get_product($products[$key]);
        if (!$p) continue;
        $old = $p->get_image_id();
        $att = co_sample_image($p->get_id(), $file, co_mockup($jpeg, 1400, $fill), $title);
        if ($att) { $p->set_image_id($att); $p->save(); if ($old) wp_delete_attachment($old, true); }
    }
    return true;
}
