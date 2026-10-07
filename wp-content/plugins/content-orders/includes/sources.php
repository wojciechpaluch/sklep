<?php
if (!defined('ABSPATH')) exit;

/* Zapamiętuje źródło ruchu z linku (?src=tiktok / ?utm_source=reddit) na 30 dni */
add_action('init', function () {
    if (is_admin() || headers_sent()) return;
    foreach (['src', 'utm_source'] as $k) {
        if (!empty($_GET[$k])) {
            $src = sanitize_key(wp_unslash($_GET[$k]));
            if ($src) setcookie('co_src', $src, time() + 30 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
            break;
        }
    }
});

add_action('woocommerce_checkout_create_order', function ($order) {
    $s = isset($_COOKIE['co_src']) ? sanitize_key($_COOKIE['co_src']) : '';
    $order->update_meta_data('_co_source', $s ?: 'bezposrednio');
});

add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    echo '<p><strong>Źródło:</strong> ' . esc_html($order->get_meta('_co_source') ?: '–') . '</p>';
});

/* Limit działalności nierejestrowanej od 2026 r.: 225% minimalnego wynagrodzenia, liczony KWARTALNIE.
 * Kwotę minimalnego wynagrodzenia ustaw w opcji co_min_wage (domyślnie 4806 zł na 2026 r.; sprawdzaj co roku na biznes.gov.pl). */
function co_quarter_limit() {
    return round(2.25 * (float) get_option('co_min_wage', 4806), 2);
}

/* Zakres bieżącego kwartału kalendarzowego: [od, do, etykieta] */
function co_quarter_range($ts = null) {
    $ts = $ts ?: current_time('timestamp');
    $m = (int) wp_date('n', $ts); $y = (int) wp_date('Y', $ts);
    $q = (int) ceil($m / 3);
    $start = sprintf('%04d-%02d-01 00:00:00', $y, ($q - 1) * 3 + 1);
    $end = wp_date('Y-m-t 23:59:59', strtotime(sprintf('%04d-%02d-01', $y, $q * 3)));
    return [$start, $end, $q . ' kwartał ' . $y];
}

/* Suma opłaconych zamówień w bieżącym kwartale */
function co_quarter_revenue() {
    list($from, $to, $label) = co_quarter_range();
    $sum = 0; $n = 0;
    foreach (wc_get_orders(['limit' => -1, 'status' => ['processing', 'completed'], 'date_created' => $from . '...' . $to]) as $o) {
        $sum += (float) $o->get_total(); $n++;
    }
    return [$sum, $n, $label];
}

/* Progi własne (zarządzanie) i próg ustawowy. Alert wysyłany raz na kwartał dla każdego progu. */
function co_limit_levels() {
    $limit = co_quarter_limit();
    return [
        '1000' => ['kw' => 1000, 'msg' => 'Pierwsze 1000 zł w tym kwartale. To tylko informacja: zacznij zapisywać sprzedaż i sprawdź zasady rejestracji.'],
        '50p' => ['kw' => round($limit * 0.5, 2), 'msg' => 'Połowa kwartalnego limitu działalności nierejestrowanej. Przygotuj rejestrację JDG (PKD, księgowość, bramka płatności).'],
        '80p' => ['kw' => round($limit * 0.8, 2), 'msg' => '80% kwartalnego limitu. Zarejestruj działalność z wyprzedzeniem.'],
        '100p' => ['kw' => $limit, 'msg' => 'UWAGA: przekroczono kwartalny limit działalności nierejestrowanej (' . number_format($limit, 2, ',', ' ') . ' zł). Sprawdź na biznes.gov.pl termin rejestracji (liczony od dnia przekroczenia).'],
    ];
}

add_action('woocommerce_order_status_processing', 'co_check_limit_alerts', 20);
add_action('woocommerce_order_status_completed', 'co_check_limit_alerts', 20);
function co_check_limit_alerts() {
    list($sum, , $label) = co_quarter_revenue();
    $sent = get_option('co_limit_alerts', []);
    foreach (co_limit_levels() as $id => $lv) {
        $key = $label . ':' . $id;
        if ($sum >= $lv['kw'] && empty($sent[$key])) {
            wp_mail(get_option('admin_email'), 'Limit przychodu (' . $label . '): ' . number_format($sum, 2, ',', ' ') . ' zł', $lv['msg'] . "\n\nWażne: to narzędzie jest pomocnicze. Aktualne zasady sprawdź na biznes.gov.pl albo u księgowego.");
            $sent[$key] = 1;
        }
    }
    update_option('co_limit_alerts', array_slice($sent, -24, null, true));
}

add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=co_job', 'Źródła sprzedaży', 'Źródła sprzedaży', 'manage_woocommerce', 'co-sources', function () {
        list($month_sum, $month_n, $qlabel) = co_quarter_revenue();
        $limit = co_quarter_limit();
        $pct = $limit > 0 ? min(100, $month_sum / $limit * 100) : 0;
        $color = $month_sum >= $limit * 0.8 ? '#d63638' : ($month_sum >= $limit * 0.5 ? '#dba617' : '#00a32a');
        echo '<div class="wrap"><h1>Źródła sprzedaży</h1>';
        echo '<h2>Limit działalności nierejestrowanej: ' . esc_html($qlabel) . '</h2>';
        echo '<div style="max-width:520px;background:#e0e0e0;border-radius:6px;overflow:hidden;height:22px"><div style="width:' . esc_attr(round($pct, 1)) . '%;background:' . esc_attr($color) . ';height:100%"></div></div>';
        echo '<p><strong>' . esc_html(number_format($month_sum, 2, ',', ' ')) . ' zł</strong> z ' . esc_html(number_format($limit, 2, ',', ' ')) . ' zł (' . (int) $month_n . ' zamówień). Limit jest kwartalny (225% minimalnego wynagrodzenia). Alerty mailowe: 1000 zł, 50%, 80% i 100% limitu. Kwotę minimalnego wynagrodzenia (opcja <code>co_min_wage</code>) i termin rejestracji po przekroczeniu sprawdzaj na biznes.gov.pl.</p>';

        $orders = wc_get_orders(['limit' => 1000, 'status' => ['processing', 'completed']]);
        $stats = []; $total = 0;
        foreach ($orders as $o) {
            $s = $o->get_meta('_co_source') ?: 'bezposrednio';
            $stats[$s] = $stats[$s] ?? ['n' => 0, 'sum' => 0];
            $stats[$s]['n']++;
            $stats[$s]['sum'] += (float) $o->get_total();
            $total += (float) $o->get_total();
        }
        uasort($stats, function ($a, $b) { return $b['sum'] <=> $a['sum']; });
        echo '<h2>Źródła (wszystkie zamówienia, ostatnie 1000)</h2><p>Dodawaj do linków <code>?src=tiktok</code>, <code>?src=youtube</code> itd. albo używaj <code>/go/tiktok/dyplom</code>.</p>';
        echo '<table class="widefat striped" style="max-width:520px"><thead><tr><th>Źródło</th><th>Zamówienia</th><th>Przychód</th></tr></thead><tbody>';
        foreach ($stats as $s => $v) {
            echo '<tr><td>' . esc_html($s) . '</td><td>' . (int) $v['n'] . '</td><td>' . esc_html(number_format($v['sum'], 2, ',', ' ')) . ' zł</td></tr>';
        }
        echo '<tr><th>Razem</th><th>' . count($orders) . '</th><th>' . esc_html(number_format($total, 2, ',', ' ')) . ' zł</th></tr>';
        echo '</tbody></table></div>';
    });
});

/*
 * Krótkie linki do reklam: /go/tiktok, /go/youtube, /go/tiktok/dyplom (adres produktu).
 * Przekierowują na sklep z ustawionym źródłem (?src=...), więc w statystykach widać, co sprzedaje.
 */
add_action('template_redirect', function () {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $base = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');
    if ($base !== '' && strpos($path, $base) === 0) $path = trim(substr($path, strlen($base)), '/');
    if (!preg_match('#^go/([a-z0-9_-]+)(?:/([a-z0-9_-]+))?/?$#i', $path, $m)) return;
    $url = home_url('/');
    if (!empty($m[2])) {
        $slug = sanitize_title($m[2]);
        $product = get_page_by_path($slug, OBJECT, 'product');
        if (!$product) {
            // krótki alias, np. /go/tiktok/dyplom albo /go/tiktok/pet (klucz z konfiguratora)
            $known = get_option('co_products', []);
            $alias = ['dyplom' => 'diploma', 'wierszyk' => 'poem', 'zwierzak' => 'pet', 'legitymacja' => 'pet', 'list' => 'wanted', 'karta' => 'card', 'piosenka' => 'song', 'piksel' => 'pixel', 'gra' => 'pixel'];
            if (isset($alias[$slug])) $slug = $alias[$slug];
            if (!empty($known[$slug])) $product = get_post($known[$slug]);
        }
        if ($product && $product->post_status === 'publish') $url = get_permalink($product);
    }
    wp_safe_redirect(add_query_arg('src', sanitize_key($m[1]), $url), 302);
    exit;
}, 1);
