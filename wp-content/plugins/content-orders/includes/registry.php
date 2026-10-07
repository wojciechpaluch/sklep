<?php
if (!defined('ABSPATH')) exit;

/**
 * Zwraca wszystkie zarejestrowane generatory (id => obiekt).
 * Rozszerzenia dodają własne przez:
 *   add_action('content_orders_register', function ($register) { $register(new Moj_Generator()); });
 */
function co_generators() {
    static $list = null;
    if ($list === null) {
        $list = [];
        $register = function (CO_Generator $g) use (&$list) {
            $list[$g->id()] = $g;
        };
        do_action('content_orders_register', $register);
    }
    return $list;
}

function co_generator($id) {
    $all = co_generators();
    return isset($all[$id]) ? $all[$id] : null;
}
