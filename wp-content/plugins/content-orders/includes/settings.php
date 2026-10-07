<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=co_job', 'Ustawienia', 'Ustawienia', 'manage_options', 'co-settings', function () {
        echo '<div class="wrap"><h1>Ustawienia zleceń</h1><form method="post" action="options.php">';
        settings_fields('co_settings');
        echo '<table class="form-table">';
        echo '<tr><th>Klucz API Claude</th><td>';
        if (defined('CO_ANTHROPIC_KEY')) {
            echo '<em>Ustawiony w wp-config.php (CO_ANTHROPIC_KEY).</em>';
        } else {
            echo '<input type="password" name="co_anthropic_key" value="' . esc_attr(get_option('co_anthropic_key')) . '" class="regular-text" autocomplete="off">';
            echo '<p class="description">Opcjonalny. Bez klucza dyplomy dostają gotowe uzasadnienia, a wierszyki AI nie działają. Zalecane: define(\'CO_ANTHROPIC_KEY\', \'...\'); w wp-config.php.</p>';
        }
        echo '</td></tr>';
        echo '<tr><th>Model</th><td><input type="text" name="co_model" value="' . esc_attr(get_option('co_model', 'claude-sonnet-5-5')) . '" class="regular-text"></td></tr>';
        echo '<tr><th>Wierszyki AI</th><td><label><input type="checkbox" name="co_auto_ai" value="1" ' . checked(get_option('co_auto_ai'), '1', false) . '> Wysyłaj klientowi automatycznie, bez mojego zatwierdzenia</label>';
        echo '<p class="description">Zalecane: wyłączone, przynajmniej na początku. Dyplomy są zawsze automatyczne.</p></td></tr>';
        echo '</table>';
        submit_button();
        echo '</form></div>';
    });
});

add_action('admin_init', function () {
    register_setting('co_settings', 'co_anthropic_key', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('co_settings', 'co_model', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('co_settings', 'co_auto_ai', ['sanitize_callback' => function ($v) { return $v ? '1' : ''; }]);
});
