<?php
/**
 * Plugin Name: Content Orders
 * Description: Sprzedaż treści tworzonych na zamówienie (WooCommerce): generatory (dyplomy PDF, wierszyki AI, piosenki), kolejka zleceń, automatyczna dostawa, konfigurator sklepu.
 * Version: 1.2.0
 * Requires Plugins: woocommerce
 * Text Domain: content-orders
 */
if (!defined('ABSPATH')) exit;

define('CO_DIR', plugin_dir_path(__FILE__));
define('CO_VERSION', '1.2.0');

require_once CO_DIR . 'includes/class-generator.php';
require_once CO_DIR . 'includes/registry.php';
require_once CO_DIR . 'includes/ai.php';
require_once CO_DIR . 'includes/pdf.php';
require_once CO_DIR . 'includes/diploma.php';
require_once CO_DIR . 'includes/photos.php';
require_once CO_DIR . 'includes/pet.php';
require_once CO_DIR . 'includes/photo-templates.php';
require_once CO_DIR . 'includes/mockups.php';
require_once CO_DIR . 'includes/pixel.php';
require_once CO_DIR . 'includes/generators.php';
require_once CO_DIR . 'includes/storefront.php';
require_once CO_DIR . 'includes/jobs.php';
require_once CO_DIR . 'includes/delivery.php';
require_once CO_DIR . 'includes/sources.php';
require_once CO_DIR . 'includes/legal.php';
require_once CO_DIR . 'includes/setup.php';
require_once CO_DIR . 'includes/payment.php';
require_once CO_DIR . 'includes/settings.php';
