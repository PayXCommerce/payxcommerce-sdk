<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce\Admin;

final class Settings
{
    public const TOKEN_SCOPE = 'payment_requests.write transactions.read balances.read refunds.write';
    public const DEFAULT_BASE_URL = 'https://payxcommerce.com/api/v1';

    public static function fields(string $webhookUrl): array
    {
        return [
            'enabled' => ['title' => __('Enable/Disable', 'payxcommerce-gateway'), 'type' => 'checkbox', 'label' => __('Enable hosted checkout', 'payxcommerce-gateway'), 'default' => 'no'],
            'brand_name' => ['title' => __('Public Brand Name', 'payxcommerce-gateway'), 'type' => 'text', 'default' => 'PayXCommerce', 'description' => __('Shown to customers in checkout labels and order notes.', 'payxcommerce-gateway')],
            'title' => ['title' => __('Checkout Title', 'payxcommerce-gateway'), 'type' => 'text', 'default' => __('Pay securely', 'payxcommerce-gateway')],
            'description' => ['title' => __('Checkout Description', 'payxcommerce-gateway'), 'type' => 'textarea', 'default' => __('You will be redirected to secure hosted checkout to complete your payment.', 'payxcommerce-gateway')],
            'button_text' => ['title' => __('Checkout Button Text', 'payxcommerce-gateway'), 'type' => 'text', 'default' => __('Continue to secure checkout', 'payxcommerce-gateway')],
            'environment' => ['title' => __('Environment', 'payxcommerce-gateway'), 'type' => 'select', 'default' => 'test', 'options' => ['test' => __('Test', 'payxcommerce-gateway'), 'live' => __('Live', 'payxcommerce-gateway')]],
            'auth_method' => ['title' => __('Authentication Method', 'payxcommerce-gateway'), 'type' => 'select', 'default' => 'hmac', 'options' => ['hmac' => __('HMAC API Key', 'payxcommerce-gateway'), 'bearer' => __('Developer App Bearer Token', 'payxcommerce-gateway')]],
            'base_url' => ['title' => __('API Base URL', 'payxcommerce-gateway'), 'type' => 'text', 'default' => self::DEFAULT_BASE_URL],
            'public_key' => ['title' => __('Public Key', 'payxcommerce-gateway'), 'type' => 'text', 'default' => ''],
            'secret_key' => ['title' => __('Secret Key', 'payxcommerce-gateway'), 'type' => 'password', 'default' => '', 'description' => __('Leave blank to keep the existing saved secret.', 'payxcommerce-gateway')],
            'client_id' => ['title' => __('Developer App Client ID', 'payxcommerce-gateway'), 'type' => 'text', 'default' => ''],
            'client_secret' => ['title' => __('Developer App Client Secret', 'payxcommerce-gateway'), 'type' => 'password', 'default' => '', 'description' => __('Leave blank to keep the existing saved client secret.', 'payxcommerce-gateway')],
            'webhook_secret' => ['title' => __('Webhook Secret', 'payxcommerce-gateway'), 'type' => 'password', 'default' => '', 'description' => sprintf(__('Webhook URL: %s. Leave blank to keep the existing saved secret.', 'payxcommerce-gateway'), esc_html($webhookUrl))],
            'allowed_currencies' => ['title' => __('Allowed Currencies', 'payxcommerce-gateway'), 'type' => 'text', 'default' => 'USD,EUR,GBP,AUD,NZD,CAD,JPY', 'description' => __('Comma-separated ISO currency codes. Leave blank to allow all store currencies.', 'payxcommerce-gateway')],
            'allowed_countries' => ['title' => __('Allowed Billing Countries', 'payxcommerce-gateway'), 'type' => 'text', 'default' => '', 'description' => __('Comma-separated ISO country codes. Leave blank to allow all billing countries.', 'payxcommerce-gateway')],
            'min_amount' => ['title' => __('Minimum Order Amount', 'payxcommerce-gateway'), 'type' => 'number', 'default' => '0', 'custom_attributes' => ['step' => '0.01', 'min' => '0']],
            'max_amount' => ['title' => __('Maximum Order Amount', 'payxcommerce-gateway'), 'type' => 'number', 'default' => '0', 'custom_attributes' => ['step' => '0.01', 'min' => '0'], 'description' => __('Use 0 for no maximum.', 'payxcommerce-gateway')],
            'debug' => ['title' => __('Debug Logging', 'payxcommerce-gateway'), 'type' => 'checkbox', 'label' => __('Enable redacted debug logs', 'payxcommerce-gateway'), 'default' => 'no'],
        ];
    }

    public static function csv(string $value): array
    {
        return array_values(array_filter(array_map(static fn($item) => strtoupper(trim($item)), explode(',', $value))));
    }
}
