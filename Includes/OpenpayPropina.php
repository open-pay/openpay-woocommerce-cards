<?php

namespace OpenpayCards\Includes;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;

if (!defined('ABSPATH')) {
    exit;
}

class OpenpayPropina
{
    private const SETTINGS_KEY = 'woocommerce_wc_openpay_gateway_settings';
    private const SESSION_KEY = 'openpay_propina_amount';
    private const ORDER_META_KEY = '_openpay_propina_amount';
    private const STORE_API_NAMESPACE = 'openpay_cards_propina';

    private static $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        add_action('woocommerce_checkout_before_order_review', [__CLASS__, 'render_classic_checkout_field']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_classic_checkout_script']);

        add_action('woocommerce_checkout_update_order_review', [__CLASS__, 'update_classic_checkout_session']);
        add_action('woocommerce_cart_calculate_fees', [__CLASS__, 'add_tip_fee']);

        add_action('woocommerce_checkout_create_order', [__CLASS__, 'save_order_meta'], 20, 2);
        add_action('woocommerce_after_checkout_validation', [__CLASS__, 'validate_checkout_tip'], 10, 2);

        if (did_action('woocommerce_blocks_loaded')) {
            self::register_store_api_callbacks();
        } else {
            add_action('woocommerce_blocks_loaded', [__CLASS__, 'register_store_api_callbacks']);
        }
    }

    public static function is_enabled(): bool
    {
        $settings = get_option(self::SETTINGS_KEY, []);

        if (!is_array($settings)) {
            return false;
        }

        return ($settings['country'] ?? '') === 'CO'
            && ($settings['propina'] ?? 'no') === 'yes';
    }

    public static function render_classic_checkout_field(): void
    {
        if (!self::is_enabled()) {
            return;
        }

        $amount = self::get_session_amount();
        $display_amount = $amount > 0 ? self::format_amount_for_display($amount, true) : '';
        $max_amount = self::get_max_allowed_amount();
        $max_amount = self::get_max_allowed_amount();

        echo '<div class="openpay-propina-checkout-field" style="margin-bottom:16px;">';
        echo '<label for="openpay_propina" style="display:block;margin-bottom:6px;">' . esc_html__('Propina', 'openpay-cards') . '</label>';
        echo '<input type="text" id="openpay_propina" name="openpay_propina" class="input-text" data-max="' . esc_attr(wc_format_decimal($max_amount, wc_get_price_decimals())) . '" inputmode="decimal" autocomplete="off" value="' . esc_attr($display_amount) . '" placeholder="" style="width:120px;text-align:center;border:0;background:transparent;outline:none;box-shadow:none;caret-color:currentColor;" />';
        echo '</div>';
    }

    public static function enqueue_classic_checkout_script(): void
    {
        if (!self::is_enabled() || !is_checkout() || is_order_received_page()) {
            return;
        }

        $script = "
            jQuery(function($) {
                var openpayPropinaTimer = null;

                function cleanAmount(value) {
                    value = String(value || '');
                    value = value.replace(/,/g, '');
                    value = value.replace(/[^0-9.]/g, '');

                    var parts = value.split('.');
                    if (parts.length > 2) {
                        value = parts.shift() + '.' + parts.join('');
                    }

                    return value;
                }

                function parseAmount(value) {
                    var amount = parseFloat(cleanAmount(value));
                    return isNaN(amount) ? 0 : amount;
                }

                function formatAmount(value, forceDecimals) {
                    var clean = cleanAmount(value);

                    if (clean === '') {
                        return '';
                    }

                    var hasDecimal = clean.indexOf('.') !== -1;
                    var parts = clean.split('.');
                    var integerPart = parts[0] || '0';
                    var decimalPart = parts[1] || '';

                    integerPart = integerPart.replace(/^0+(?=\\d)/, '');
                    integerPart = integerPart.replace(/\\B(?=(\\d{3})+(?!\\d))/g, ',');

                    if (forceDecimals) {
                        decimalPart = (decimalPart + '00').substring(0, 2);
                        return integerPart + '.' + decimalPart;
                    }

                    if (hasDecimal) {
                        return integerPart + '.' + decimalPart.substring(0, 2);
                    }

                    return integerPart;
                }

                function scheduleCheckoutUpdate() {
                    clearTimeout(openpayPropinaTimer);

                    openpayPropinaTimer = setTimeout(function() {
                        $(document.body).trigger('update_checkout');
                    }, 500);
                }

                $(document.body).on('input change', '#openpay_propina', function() {
                    var \$input = $(this);
                    var max = parseAmount(\$input.data('max'));
                    var value = cleanAmount(\$input.val());
                    var numericValue = parseAmount(value);

                    if (max > 0 && numericValue > max) {
                        value = String(max);
                    }

                    \$input.val(formatAmount(value, false));
                    scheduleCheckoutUpdate();
                });

                $(document.body).on('blur', '#openpay_propina', function() {
                    var \$input = $(this);
                    var max = parseAmount(\$input.data('max'));
                    var value = cleanAmount(\$input.val());
                    var numericValue = parseAmount(value);

                    if (value === '' || numericValue <= 0) {
                        \$input.val('');
                        $(document.body).trigger('update_checkout');
                        return;
                    }

                    if (max > 0 && numericValue > max) {
                        value = String(max);
                    }

                    \$input.val(formatAmount(value, true));
                    $(document.body).trigger('update_checkout');
                });
            });
        ";

        wp_add_inline_script('wc-checkout', $script, 'after');
    }

    public static function update_classic_checkout_session(string $post_data): void
    {
        if (!self::is_enabled()) {
            self::set_session_amount(0);
            return;
        }

        parse_str($post_data, $data);

        self::set_session_amount($data['openpay_propina'] ?? 0);
    }

    public static function add_tip_fee(\WC_Cart $cart): void
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (!self::is_enabled()) {
            return;
        }

        $amount = self::get_session_amount();
        $max_amount = self::get_max_allowed_amount();

        if ($amount > $max_amount) {
            $amount = $max_amount;
            self::set_session_amount($amount);
        }

        if ($amount <= 0) {
            return;
        }

        $cart->add_fee(__('Propina', 'openpay-cards'), $amount, false);
    }

    public static function save_order_meta(\WC_Order $order, array $data): void
    {
        $amount = self::get_session_amount();

        if ($amount <= 0) {
            return;
        }

        $order->update_meta_data(
            self::ORDER_META_KEY,
            wc_format_decimal($amount, wc_get_price_decimals())
        );
    }

    public static function register_store_api_callbacks(): void
    {
        if (function_exists('woocommerce_store_api_register_update_callback')) {
            woocommerce_store_api_register_update_callback([
                'namespace' => self::STORE_API_NAMESPACE,
                'callback' => function ($data) {
                    self::set_session_amount($data['amount'] ?? 0);
                },
            ]);
        }

        if (
            function_exists('woocommerce_store_api_register_endpoint_data')
            && class_exists(CartSchema::class)
        ) {
            woocommerce_store_api_register_endpoint_data([
                'endpoint' => CartSchema::IDENTIFIER,
                'namespace' => self::STORE_API_NAMESPACE,
                'data_callback' => [__CLASS__, 'get_store_api_cart_data'],
                'schema_callback' => [__CLASS__, 'get_store_api_cart_schema'],
                'schema_type' => ARRAY_A,
            ]);
        }
    }

    public static function get_store_api_cart_data(): array
    {
        $amount = self::get_session_amount();
        $max_amount = self::get_max_allowed_amount();

        return [
            'enabled' => self::is_enabled(),
            'amount' => wc_format_decimal($amount, wc_get_price_decimals()),
            'max_amount' => wc_format_decimal($max_amount, wc_get_price_decimals()),
            'max_amount_formatted' => html_entity_decode(
                wp_strip_all_tags(wc_price($max_amount)),
                ENT_QUOTES,
                get_bloginfo('charset')
            ),
        ];
    }

    public static function get_store_api_cart_schema(): array
    {
        return [
            'enabled' => [
                'description' => __('Indica si Propina está habilitada.', 'openpay-cards'),
                'type' => 'boolean',
                'readonly' => true,
            ],
            'amount' => [
                'description' => __('Monto actual de Propina.', 'openpay-cards'),
                'type' => 'string',
                'readonly' => true,
            ],
            'max_amount' => [
                'description' => __('Monto máximo permitido para Propina.', 'openpay-cards'),
                'type' => 'string',
                'readonly' => true,
            ],
            'max_amount_formatted' => [
                'description' => __('Monto máximo permitido para Propina formateado.', 'openpay-cards'),
                'type' => 'string',
                'readonly' => true,
            ],
        ];
    }

    public static function validate_checkout_tip(array $data, \WP_Error $errors): void
    {
        if (!self::is_enabled()) {
            return;
        }

        $amount = isset($_POST['openpay_propina'])
            ? self::normalize_amount(wp_unslash($_POST['openpay_propina']))
            : self::get_session_amount();

        $max_amount = self::get_max_allowed_amount();

        if ($amount > $max_amount) {
            $errors->add(
                'openpay_propina_invalid_amount',
                sprintf(
                    __('La propina no puede ser mayor que el importe total de la venta: %s.', 'openpay-cards'),
                    wc_price($max_amount)
                )
            );
        }
    }

    private static function get_max_allowed_amount(): float
    {
        if (!function_exists('WC') || !(WC()->cart instanceof \WC_Cart)) {
            return 0.0;
        }

        $totals = WC()->cart->get_totals();
        $cart_total = isset($totals['total']) ? (float) $totals['total'] : 0.0;

        $current_tip = 0.0;

        if (WC()->session) {
            $current_tip = self::normalize_amount(WC()->session->get(self::SESSION_KEY, 0));
        }

        if ($cart_total <= 0) {
            $cart_total =
                (float) WC()->cart->get_cart_contents_total()
                + (float) WC()->cart->get_shipping_total()
                + (float) WC()->cart->get_fee_total()
                + (float) WC()->cart->get_total_tax();
        }

        return max(0.0, $cart_total - $current_tip);
    }

    private static function get_session_amount(): float
    {
        if (!function_exists('WC') || !WC()->session) {
            return 0.0;
        }

        return self::normalize_amount(WC()->session->get(self::SESSION_KEY, 0));
    }

    private static function set_session_amount($amount): void
    {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $amount = self::normalize_amount($amount);
        $max_amount = self::get_max_allowed_amount();

        if ($max_amount <= 0) {
            $amount = 0.0;
        } elseif ($amount > $max_amount) {
            $amount = $max_amount;
        }

        WC()->session->set(self::SESSION_KEY, $amount);
    }

    private static function format_amount_for_display($amount, bool $force_decimals = false): string
    {
        $amount = self::normalize_amount($amount);

        if ($amount <= 0) {
            return '';
        }

        return number_format(
            $amount,
            $force_decimals ? wc_get_price_decimals() : 0,
            '.',
            ','
        );
    }

    private static function normalize_amount($amount): float
    {
        if (is_array($amount)) {
            return 0.0;
        }

        $amount = wc_clean(wp_unslash((string) $amount));
        $amount = str_replace([',', ' '], '', $amount);
        $amount = preg_replace('/[^0-9.\-]/', '', $amount);
        $amount = wc_format_decimal($amount);

        if ($amount === '' || !is_numeric($amount)) {
            return 0.0;
        }

        return max(0.0, (float) $amount);
    }
}