<?php

declare(strict_types=1);

namespace PayXCommerce\WooCommerce\Gateway;

use PayXCommerce\WooCommerce\Admin\Settings;
use PayXCommerce\WooCommerce\Api\SdkFactory;
use PayXCommerce\WooCommerce\Order\Metadata;
use PayXCommerce\WooCommerce\Order\PayloadBuilder;
use PayXCommerce\WooCommerce\Support\Logger;
use PayXCommerce\WooCommerce\Webhook\Handler;
use PayXCommerce\Exceptions\AuthException;
use WC_Order;
use WC_Payment_Gateway;
use WP_Error;

final class Gateway extends WC_Payment_Gateway
{
    private Metadata $metadata;
    private PayloadBuilder $payloadBuilder;
    private Logger $logger;

    public function __construct()
    {
        $this->id = 'payxcommerce';
        $this->icon = PAYXCOMMERCE_WC_URL . 'assets/img/logo-icon-dark-64.png';
        $this->method_title = __('PayXCommerce Hosted Checkout', 'payxcommerce-gateway');
        $this->method_description = sprintf(
            '<span class="payxcommerce-admin-brand"><img src="%1$s" alt="" width="28" height="28"> <strong>%2$s</strong></span><br>%3$s',
            esc_url(PAYXCOMMERCE_WC_URL . 'assets/img/logo-icon-dark-64.png'),
            esc_html__('PayXCommerce Hosted Checkout', 'payxcommerce-gateway'),
            esc_html__('Create hosted checkout payment requests and receive signed payment status webhooks.', 'payxcommerce-gateway')
        );
        $this->has_fields = false;
        $this->supports = ['products', 'refunds'];

        $this->init_form_fields();
        $this->init_settings();

        $this->metadata = new Metadata();
        $this->payloadBuilder = new PayloadBuilder();
        $this->logger = new Logger($this->get_option('debug') === 'yes');

        $this->title = $this->publicText('title', __('Pay securely', 'payxcommerce-gateway'));
        $this->description = $this->publicText('description', __('You will be redirected to secure hosted checkout to complete your payment.', 'payxcommerce-gateway'));
        $this->enabled = (string) $this->get_option('enabled', 'no');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_payxcommerce', [$this, 'handle_webhook']);
        add_filter('woocommerce_order_button_text', [$this, 'orderButtonText']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = Settings::fields(WC()->api_request_url('payxcommerce'));
    }

    public function needs_setup(): bool
    {
        if ($this->get_option('auth_method') === 'bearer') {
            return !$this->get_option('client_id') || !$this->get_option('client_secret') || !$this->get_option('webhook_secret');
        }

        return !$this->get_option('public_key') || !$this->get_option('secret_key') || !$this->get_option('webhook_secret');
    }

    public function is_available(): bool
    {
        if (!parent::is_available() || $this->needs_setup()) {
            return false;
        }

        $currency = strtoupper(get_woocommerce_currency());
        $allowedCurrencies = Settings::csv((string) $this->get_option('allowed_currencies'));
        if ($allowedCurrencies && !in_array($currency, $allowedCurrencies, true)) {
            return false;
        }

        $total = WC()->cart ? (float) WC()->cart->get_total('edit') : 0.0;
        $min = (float) $this->get_option('min_amount', '0');
        $max = (float) $this->get_option('max_amount', '0');
        if (($min > 0 && $total > 0 && $total < $min) || ($max > 0 && $total > $max)) {
            return false;
        }

        $country = WC()->customer ? strtoupper((string) WC()->customer->get_billing_country()) : '';
        $allowedCountries = Settings::csv((string) $this->get_option('allowed_countries'));
        if ($country !== '' && $allowedCountries && !in_array($country, $allowedCountries, true)) {
            return false;
        }

        return true;
    }

    public function payment_fields(): void
    {
        printf(
            '<div class="payxcommerce-checkout-brand" style="display:flex;align-items:center;gap:10px;margin:0 0 10px;"><img src="%1$s" alt="%2$s" width="32" height="32" style="width:32px;height:32px;border-radius:6px;"><strong>%2$s</strong></div>',
            esc_url(PAYXCOMMERCE_WC_URL . 'assets/img/logo-icon-dark-64.png'),
            esc_html($this->brandName())
        );

        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
        printf('<p class="payxcommerce-checkout-note">%s</p>', esc_html(sprintf(__('You will complete payment through %s hosted checkout.', 'payxcommerce-gateway'), $this->brandName())));
    }

    public function get_icon(): string
    {
        $icon = sprintf(
            '<img src="%1$s" alt="%2$s" class="payxcommerce-payment-icon" style="height:24px;width:24px;vertical-align:middle;margin-left:8px;border-radius:5px;" />',
            esc_url(PAYXCOMMERCE_WC_URL . 'assets/img/logo-icon-dark-64.png'),
            esc_attr($this->brandName())
        );

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    public function orderButtonText(string $buttonText): string
    {
        if (!function_exists('WC') || !WC()->session || WC()->session->get('chosen_payment_method') !== $this->id) {
            return $buttonText;
        }

        return $this->publicText('button_text', __('Continue to secure checkout', 'payxcommerce-gateway'));
    }

    public function process_admin_options(): bool
    {
        $saved = parent::process_admin_options();
        if (!$saved) {
            return false;
        }

        delete_transient('payxcommerce_bearer_token_' . md5((string) $this->get_option('client_id')));
        if ($this->get_option('enabled') === 'yes') {
            try {
                if ($this->needs_setup()) {
                    throw new \RuntimeException(__('Required credentials or webhook secret are missing.', 'payxcommerce-gateway'));
                }
                $this->sdk()->validateCredentials();
                \WC_Admin_Settings::add_message(__('Credentials validated successfully.', 'payxcommerce-gateway'));
            } catch (\Throwable $exception) {
                \WC_Admin_Settings::add_error(sprintf(__('Credential validation failed: %s', 'payxcommerce-gateway'), esc_html($exception->getMessage())));
            }
        }

        return true;
    }

    public function validate_secret_key_field($key, $value): string
    {
        return $this->preservePassword('secret_key', $value);
    }

    public function validate_client_secret_field($key, $value): string
    {
        return $this->preservePassword('client_secret', $value);
    }

    public function validate_webhook_secret_field($key, $value): string
    {
        return $this->preservePassword('webhook_secret', $value);
    }

    public function generate_payxcommerce_secret_html($key, $data): string
    {
        $fieldKey = $this->get_field_key($key);
        $data = wp_parse_args($data, [
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'password',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => [],
        ]);
        $placeholder = (string) ($data['placeholder'] ?: __('Enter secret', 'payxcommerce-gateway'));
        if ((string) $this->get_option($key, '') !== '') {
            $placeholder = __('Stored — leave blank to keep existing', 'payxcommerce-gateway');
        }

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($fieldKey); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
            </th>
            <td class="forminp">
                <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>" type="password" name="<?php echo esc_attr($fieldKey); ?>" id="<?php echo esc_attr($fieldKey); ?>" style="<?php echo esc_attr($data['css']); ?>" value="" placeholder="<?php echo esc_attr($placeholder); ?>" <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
                <?php echo $this->get_description_html($data); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            wc_add_notice(__('Unable to load order for hosted checkout.', 'payxcommerce-gateway'), 'error');
            return ['result' => 'failure'];
        }

        if (!$this->orderSupported($order)) {
            wc_add_notice(__('This payment method is not available for this order.', 'payxcommerce-gateway'), 'error');
            return ['result' => 'failure'];
        }

        $existingCheckout = (string) $order->get_meta(Metadata::CHECKOUT_URL);
        if ($existingCheckout && !$order->is_paid()) {
            return ['result' => 'success', 'redirect' => $existingCheckout];
        }

        try {
            $response = $this->createHostedCheckout($order);
        } catch (AuthException $exception) {
            if ($this->get_option('auth_method') === 'bearer') {
                $this->sdk()->clearAccessTokenCache();
                try {
                    $response = $this->createHostedCheckout($order);
                } catch (\Throwable $retryException) {
                    $this->logger->info('Create payment request failed after token refresh: ' . $retryException->getMessage());
                    wc_add_notice(__('Unable to start hosted checkout. Please try again.', 'payxcommerce-gateway'), 'error');
                    return ['result' => 'failure'];
                }
            } else {
                $this->logger->info('Create payment request failed: ' . $exception->getMessage());
                wc_add_notice(__('Unable to start hosted checkout. Please try again.', 'payxcommerce-gateway'), 'error');
                return ['result' => 'failure'];
            }
        } catch (\Throwable $exception) {
            $this->logger->info('Create payment request failed: ' . $exception->getMessage());
            wc_add_notice(__('Unable to start hosted checkout. Please try again.', 'payxcommerce-gateway'), 'error');
            return ['result' => 'failure'];
        }

        $checkoutUrl = esc_url_raw((string) ($response['checkout_url'] ?? ''));
        if ($checkoutUrl === '') {
            wc_add_notice(__('Hosted checkout URL was not returned.', 'payxcommerce-gateway'), 'error');
            return ['result' => 'failure'];
        }

        $this->metadata->saveCheckout($order, $response, (string) $this->get_option('environment', 'test'));
        $order->save();
        $order->add_order_note(sprintf(__('%1$s checkout created: %2$s', 'payxcommerce-gateway'), $this->brandName(), sanitize_text_field((string) ($response['request_number'] ?? ''))));

        return ['result' => 'success', 'redirect' => $checkoutUrl];
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return new WP_Error('payxcommerce_order_missing', __('Unable to load WooCommerce order.', 'payxcommerce-gateway'));
        }

        $transactionReference = (string) $order->get_meta(Metadata::TRANSACTION_REFERENCE);
        if ($transactionReference === '') {
            return new WP_Error('payxcommerce_transaction_missing', __('Missing transaction reference.', 'payxcommerce-gateway'));
        }

        try {
            $response = $this->sdk()->client()->refunds()->create([
                'transaction_reference' => $transactionReference,
                'amount' => $amount !== null ? (float) $amount : null,
                'reason' => $reason ?: 'WooCommerce refund request',
            ], 'woocommerce-refund-' . $order->get_id() . '-' . time());
        } catch (\Throwable $exception) {
            $this->logger->info('Refund request failed: ' . $exception->getMessage());
            return new WP_Error('payxcommerce_refund_failed', $exception->getMessage());
        }

        $order->add_order_note(sprintf(__('Refund requested: %s', 'payxcommerce-gateway'), sanitize_text_field((string) ($response['refund_reference'] ?? 'pending'))));
        return true;
    }

    public function handle_webhook(): void
    {
        (new Handler((string) $this->get_option('webhook_secret'), $this->metadata, $this->logger))->handle();
    }

    private function sdk(): SdkFactory
    {
        return new SdkFactory(fn(string $key, string $default = ''): string => (string) $this->get_option($key, $default));
    }

    private function createHostedCheckout(WC_Order $order): array
    {
        return $this->sdk()->client()->paymentRequests()->create(
            $this->payloadBuilder->build($order, WC()->api_request_url('payxcommerce'), $this->get_option('environment') !== 'live'),
            'woocommerce-order-' . $order->get_id() . '-attempt-' . time()
        );
    }

    private function orderSupported(WC_Order $order): bool
    {
        $allowedCurrencies = Settings::csv((string) $this->get_option('allowed_currencies'));
        if ($allowedCurrencies && !in_array(strtoupper($order->get_currency()), $allowedCurrencies, true)) {
            return false;
        }

        $allowedCountries = Settings::csv((string) $this->get_option('allowed_countries'));
        $country = strtoupper((string) $order->get_billing_country());
        if ($country !== '' && $allowedCountries && !in_array($country, $allowedCountries, true)) {
            return false;
        }

        $total = (float) $order->get_total();
        $min = (float) $this->get_option('min_amount', '0');
        $max = (float) $this->get_option('max_amount', '0');
        return !($min > 0 && $total < $min) && !($max > 0 && $total > $max);
    }

    private function preservePassword(string $optionKey, mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        return $value !== '' ? $value : (string) $this->get_option($optionKey, '');
    }

    private function publicText(string $key, string $default): string
    {
        return str_replace('{brand}', $this->brandName(), (string) $this->get_option($key, $default));
    }

    private function brandName(): string
    {
        return (string) $this->get_option('brand_name', 'PayXCommerce');
    }
}
