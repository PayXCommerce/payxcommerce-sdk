<?php
/**
 * Plugin Name: PayXCommerce Gateway for WooCommerce
 * Plugin URI:  https://payxcommerce.com/docs
 * Description: Hosted checkout payments for WooCommerce using PayXCommerce payment requests, signed webhooks, refunds, and secure API authentication.
 * Version:     0.3.2
 * Author:      PayXCommerce
 * Author URI:  https://payxcommerce.com/
 * License:     MIT
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * Text Domain: payxcommerce-gateway
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('PAYXCOMMERCE_WC_VERSION', '0.3.2');
define('PAYXCOMMERCE_WC_FILE', __FILE__);
define('PAYXCOMMERCE_WC_PATH', plugin_dir_path(__FILE__));
define('PAYXCOMMERCE_WC_URL', plugin_dir_url(__FILE__));

require_once PAYXCOMMERCE_WC_PATH . 'includes/Autoloader.php';
require_once PAYXCOMMERCE_WC_PATH . 'api/order-functions.php';
\PayXCommerce\WooCommerce\Autoloader::register(PAYXCOMMERCE_WC_PATH);

add_action('before_woocommerce_init', static function (): void {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    if (!class_exists('WooCommerce') || !class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>' . esc_html__('PayXCommerce Gateway requires WooCommerce to be installed and active.', 'payxcommerce-gateway') . '</p></div>';
        });
        return;
    }

    if (version_compare(PHP_VERSION, '8.1', '<')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>' . esc_html__('PayXCommerce Gateway requires PHP 8.1 or newer.', 'payxcommerce-gateway') . '</p></div>';
        });
        return;
    }

    \PayXCommerce\WooCommerce\Plugin::init();
});

register_activation_hook(__FILE__, static function (): void {
    update_option('payxcommerce_wc_version', PAYXCOMMERCE_WC_VERSION);
});
