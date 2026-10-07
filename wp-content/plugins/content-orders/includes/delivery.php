<?php
if (!defined('ABSPATH')) exit;

/** Wysyła wynik zlecenia do klienta e-mailem. Zwraca true albo WP_Error. */
function co_deliver_job($job) {
    $text = get_post_meta($job, '_co_result_text', true);
    $file = get_post_meta($job, '_co_file', true);
    if (!$text && !$file) return new WP_Error('co_empty', 'Brak wyniku. Wpisz tekst lub wgraj plik i zapisz zlecenie.');

    $order = wc_get_order((int) get_post_meta($job, '_co_order_id', true));
    if (!$order) return new WP_Error('co_noorder', 'Nie znaleziono zamówienia.');

    $token = get_post_meta($job, '_co_token', true);
    if (!$token) {
        $token = wp_generate_password(32, false);
        update_post_meta($job, '_co_token', $token);
    }

    $body = "Dzień dobry,\n\nTwoje zamówienie #" . $order->get_order_number() . " jest gotowe.\n\n";
    if ($text) $body .= $text . "\n\n";
    if ($file) $body .= 'Pobierz plik: ' . add_query_arg('co_download', $token, home_url('/')) . "\n\n";
    $body .= "Pozdrawiam,\n" . get_bloginfo('name');

    if (!wp_mail($order->get_billing_email(), 'Twoje zamówienie jest gotowe – ' . get_bloginfo('name'), $body)) {
        return new WP_Error('co_mail', 'Nie udało się wysłać e-maila. Sprawdź konfigurację poczty serwera.');
    }

    update_post_meta($job, '_co_status', 'delivered');
    $order->add_order_note('Zlecenie #' . $job . ' dostarczone klientowi.');

    // Jeśli wszystkie zlecenia z zamówienia są dostarczone, zamknij zamówienie
    $all = true;
    foreach ($order->get_items() as $item) {
        foreach ((array) $item->get_meta('_co_job_ids') as $id) {
            if (get_post_meta($id, '_co_status', true) !== 'delivered') $all = false;
        }
    }
    if ($all && $order->get_status() !== 'completed') $order->update_status('completed');
    return true;
}

add_action('admin_post_co_deliver', function () {
    $job = (int) ($_GET['job'] ?? 0);
    check_admin_referer('co_deliver_' . $job);
    if (!current_user_can('edit_post', $job)) wp_die('Brak uprawnień.');
    $r = co_deliver_job($job);
    co_back($job, is_wp_error($r) ? $r->get_error_message() : 'Dostarczono. E-mail wysłany do klienta.');
});

/* Pobieranie pliku przez token (pliki nie leżą pod publicznym adresem) */
add_action('template_redirect', function () {
    if (empty($_GET['co_download'])) return;
    $token = sanitize_text_field(wp_unslash($_GET['co_download']));
    $ids = get_posts([
        'post_type' => 'co_job', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
        'meta_query' => [['key' => '_co_token', 'value' => $token]],
    ]);
    if (!$ids || get_post_meta($ids[0], '_co_status', true) !== 'delivered') wp_die('Link jest nieprawidłowy.', '', 404);
    $path = co_private_dir() . '/' . basename(get_post_meta($ids[0], '_co_file', true));
    if (!is_file($path)) wp_die('Plik nie istnieje.', '', 404);
    $name = get_post_meta($ids[0], '_co_file_name', true) ?: basename($path);
    $type = wp_check_filetype($name)['type'] ?: 'application/octet-stream';
    nocache_headers();
    header('Content-Type: ' . $type);
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($name) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
});
