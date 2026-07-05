<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce;

use PayXCommerce\WooCommerce\Gateway\Gateway;
use PayXCommerce\WooCommerce\Blocks\PaymentMethodType;

final class Plugin
{
    public static function init(): void
    {
        add_filter('woocommerce_payment_gateways', static function (array $gateways): array {
            $gateways[] = Gateway::class;
            return $gateways;
        });

        add_filter('plugin_action_links_' . plugin_basename(PAYXCOMMERCE_WC_FILE), [self::class, 'actionLinks']);
        add_filter('plugin_row_meta', [self::class, 'rowMeta'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'adminAssets']);
        add_action('woocommerce_blocks_loaded', [self::class, 'blocksLoaded']);
    }

    public static function actionLinks(array $links): array
    {
        array_unshift($links, sprintf('<a href="%s">%s</a>', esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=payxcommerce')), esc_html__('Settings', 'payxcommerce-gateway')));
        return $links;
    }

    public static function rowMeta(array $links, string $file): array
    {
        if (plugin_basename(PAYXCOMMERCE_WC_FILE) !== $file) {
            return $links;
        }

        $links[] = sprintf('<a target="_blank" rel="noopener" href="%s">%s</a>', esc_url('https://payxcommerce.com/docs'), esc_html__('Documentation', 'payxcommerce-gateway'));
        $links[] = sprintf('<a target="_blank" rel="noopener" href="%s">%s</a>', esc_url('https://payxcommerce.com/contact'), esc_html__('Support', 'payxcommerce-gateway'));
        return $links;
    }

    public static function blocksLoaded(): void
    {
        if (!class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }

        add_action('woocommerce_blocks_payment_method_type_registration', static function ($registry): void {
            $registry->register(new PaymentMethodType());
        });
    }

    public static function adminAssets(string $hook): void
    {
        if ($hook !== 'woocommerce_page_wc-settings') {
            return;
        }
        wp_enqueue_style('payxcommerce-wc-admin', PAYXCOMMERCE_WC_URL . 'assets/css/admin.css', [], PAYXCOMMERCE_WC_VERSION);
    }
}
