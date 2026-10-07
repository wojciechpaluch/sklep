<?php
if (!defined('ABSPATH')) exit;

/* Płatność przelewem: czytelny blok na stronie "Dziękujemy" z przyciskami "kopiuj" i kodem QR do skanowania w aplikacji banku.
 * Format kodu QR: rekomendacja ZBP (pola rozdzielone znakiem "|"): NIP | kod kraju | NRB | kwota w groszach (6 cyfr) | odbiorca (do 20 zn.) | tytuł (do 32 zn.) | identyfikator | rezerwa | rezerwa.
 */

function co_bank_data() {
    $o = get_option('co_setup', []);
    $acc = get_option('woocommerce_bacs_accounts', []);
    $a = $acc[0] ?? [];
    $nrb = preg_replace('/\D/', '', preg_replace('/^PL/i', '', (string) ($a['iban'] ?? ($o['iban'] ?? ''))));
    return ['nrb' => $nrb, 'name' => (string) ($a['account_name'] ?? ($o['account_name'] ?? '')), 'bank' => (string) ($a['bank_name'] ?? ($o['bank_name'] ?? ''))];
}

function co_format_nrb($nrb) {
    return strlen($nrb) === 26 ? substr($nrb, 0, 2) . ' ' . trim(chunk_split(substr($nrb, 2), 4, ' ')) : $nrb;
}

function co_qr_payload($nrb, $amount, $name, $title) {
    $cut = function ($s, $n) { return mb_substr(str_replace('|', ' ', trim($s)), 0, $n); };
    $grosze = str_pad((string) (int) round($amount * 100), 6, '0', STR_PAD_LEFT);
    return implode('|', ['', '', $nrb, $grosze, $cut($name, 20), $cut($title, 32), '', '', '']);
}

function co_qr_svg($data) {
    $autoload = CO_DIR . 'lib/vendor/autoload.php';
    if (!is_file($autoload)) return '';
    require_once $autoload;
    try {
        $options = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\Output\QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
            'addQuietzone' => true,
            'svgViewBoxSize' => 400,
        ]);
        return (new \chillerlan\QRCode\QRCode($options))->render($data);
    } catch (\Throwable $e) {
        return '';
    }
}

/* Zastępuje domyślny blok danych z WooCommerce własnym */
add_action('template_redirect', function () {
    if (!function_exists('WC') || !WC()->payment_gateways()) return;
    $gws = WC()->payment_gateways()->payment_gateways();
    if (!empty($gws['bacs'])) remove_action('woocommerce_thankyou_bacs', [$gws['bacs'], 'thankyou_page']);
});

add_action('woocommerce_thankyou_bacs', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order || $order->get_payment_method() !== 'bacs') return;
    $b = co_bank_data();
    if (strlen($b['nrb']) !== 26) { echo '<p>Dane do przelewu: skontaktuj się ze sprzedawcą.</p>'; return; }
    $total = (float) $order->get_total();
    $title = 'Zamówienie ' . $order->get_order_number();
    $amount_txt = number_format($total, 2, ',', '');
    $svg = co_qr_svg(co_qr_payload($b['nrb'], $total, $b['name'], $title));
    $rows = [
        ['Odbiorca', $b['name'], $b['name']],
        ['Numer konta', co_format_nrb($b['nrb']), $b['nrb']],
        ['Kwota', $amount_txt . ' zł', $amount_txt],
        ['Tytuł przelewu', $title, $title],
    ];
    echo '<section class="co-pay"><h2>Dokończ płatność</h2>';
    echo '<p class="co-pay-lead">Zamówienie jest zapisane. Zrób przelew na poniższe dane. Realizacja ruszy po zaksięgowaniu wpłaty' . ($b['bank'] ? ' (' . esc_html($b['bank']) . ')' : '') . '.</p>';
    echo '<div class="co-pay-grid"><div class="co-pay-rows">';
    foreach ($rows as [$label, $shown, $raw]) {
        echo '<div class="co-pay-row"><div><span class="co-pay-label">' . esc_html($label) . '</span><strong>' . esc_html($shown) . '</strong></div>'
            . '<button type="button" class="co-copy" data-copy="' . esc_attr($raw) . '">Kopiuj</button></div>';
    }
    echo '</div>';
    if ($svg) {
        echo '<div class="co-pay-qr"><div class="co-qr-img">' . $svg . '</div><p>Zeskanuj w aplikacji banku<br><small>Płatności → Zapłać kodem QR</small></p></div>';
    }
    echo '</div><p class="co-pay-note">Wpisz dokładnie ten tytuł, żebym wiedział, której wpłaty dotyczy. Kod QR uzupełnia wszystkie dane za Ciebie.</p></section>';
}, 5);

add_action('wp_footer', function () {
    if (!function_exists('is_order_received_page') || !is_order_received_page()) return;
    ?>
<script>
document.querySelectorAll('.co-copy').forEach(function (b) {
  b.addEventListener('click', function () {
    var t = b.dataset.copy, done = function () { var o = b.textContent; b.textContent = 'Skopiowano'; b.classList.add('ok'); setTimeout(function () { b.textContent = o; b.classList.remove('ok'); }, 1600); };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t).then(done); return; }
    var a = document.createElement('textarea'); a.value = t; a.style.position = 'fixed'; a.style.opacity = '0'; document.body.appendChild(a); a.select();
    try { document.execCommand('copy'); done(); } catch (e) {} document.body.removeChild(a);
  });
});
</script>
<?php
});
