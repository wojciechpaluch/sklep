<?php
if (!defined('ABSPATH')) exit;

/**
 * Minimalny generator PDF: jedna strona A4 (poziomo) z obrazem JPEG na całą stronę.
 * Nie wymaga żadnych bibliotek zewnętrznych.
 */
function co_jpeg_to_pdf($jpeg, $px_w, $px_h) {
    $pw = 841.89; // A4 poziomo, w punktach
    $ph = 595.28;
    $objs = [];
    $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objs[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objs[3] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>', $pw, $ph);
    $objs[4] = sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n", $px_w, $px_h, strlen($jpeg)) . $jpeg . "\nendstream";
    $content = sprintf('q %.2F 0 0 %.2F 0 0 cm /Im0 Do Q', $pw, $ph);
    $objs[5] = sprintf("<< /Length %d >>\nstream\n", strlen($content)) . $content . "\nendstream";

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objs as $n => $body) {
        $offsets[$n] = strlen($pdf);
        $pdf .= $n . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $off) $pdf .= sprintf("%010d 00000 n \n", $off);
    $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    return $pdf;
}
