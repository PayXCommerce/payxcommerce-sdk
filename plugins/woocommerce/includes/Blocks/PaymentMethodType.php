<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class PaymentMethodType extends AbstractPaymentMethodType
{
    protected $name = 'payxcommerce';

    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_payxcommerce_settings', []);
    }

    public function is_active(): bool
    {
        return ($this->settings['enabled'] ?? 'no') === 'yes';
    }

    public function get_payment_method_script_handles(): array
    {
        $handle = 'payxcommerce-wc-blocks';
        wp_register_script(
            $handle,
            PAYXCOMMERCE_WC_URL . 'assets/js/checkout-block.js',
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n'],
            PAYXCOMMERCE_WC_VERSION,
            true
        );

        return [$handle];
    }

    public function get_payment_method_data(): array
    {
        $brand = (string) ($this->settings['brand_name'] ?? 'PayXCommerce');
        $title = str_replace('{brand}', $brand, (string) ($this->settings['title'] ?? __('Pay securely', 'payxcommerce-gateway')));
        $description = str_replace('{brand}', $brand, (string) ($this->settings['description'] ?? __('You will be redirected to secure hosted checkout to complete your payment.', 'payxcommerce-gateway')));
        $buttonText = str_replace('{brand}', $brand, (string) ($this->settings['button_text'] ?? __('Continue to secure checkout', 'payxcommerce-gateway')));

        return [
            'title' => $title,
            'description' => $description,
            'brandName' => $brand,
            'buttonText' => $buttonText,
            'supports' => ['products'],
        ];
    }
}
