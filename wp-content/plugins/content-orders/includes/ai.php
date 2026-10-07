<?php
if (!defined('ABSPATH')) exit;

/**
 * Proste wywołanie API Claude (Anthropic). Klucz: stała CO_ANTHROPIC_KEY w wp-config.php albo ustawienie wtyczki.
 */
function co_ai_complete($system, $user, $max_tokens = 800) {
    $key = defined('CO_ANTHROPIC_KEY') ? CO_ANTHROPIC_KEY : get_option('co_anthropic_key');
    if (!$key) {
        return new WP_Error('co_nokey', 'Brak klucza API (Zlecenia → Ustawienia).');
    }
    $res = wp_remote_post('https://api.anthropic.com/v1/messages', [
        'timeout' => 60,
        'headers' => [
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ],
        'body' => wp_json_encode([
            'model' => get_option('co_model', 'claude-sonnet-5-5'),
            'max_tokens' => $max_tokens,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $user]],
        ]),
    ]);
    if (is_wp_error($res)) return $res;
    $code = wp_remote_retrieve_response_code($res);
    $body = json_decode(wp_remote_retrieve_body($res), true);
    if ($code !== 200) {
        $msg = isset($body['error']['message']) ? $body['error']['message'] : 'Błąd API (' . $code . ')';
        return new WP_Error('co_api', $msg);
    }
    $text = '';
    foreach ((array) ($body['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') $text .= $block['text'];
    }
    return trim($text) !== '' ? trim($text) : new WP_Error('co_empty', 'API zwróciło pustą odpowiedź.');
}
