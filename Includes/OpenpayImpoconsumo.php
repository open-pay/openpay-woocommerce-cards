<?php

namespace OpenpayCards\Includes;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;

if (!defined('ABSPATH')) {
    exit;
}

class OpenpayImpoconsumo
{
    private const PRODUCT_META_KEY = '_openpay_impoconsumo';
    private const ORDER_TOTAL_META_KEY = '_openpay_impoconsumo_total';
    private const ORDER_ITEM_UNIT_META_KEY = '_openpay_impoconsumo_unit';
    private const ORDER_ITEM_TOTAL_META_KEY = '_openpay_impoconsumo_line_total';
    private const SETTINGS_KEY = 'woocommerce_wc_openpay_gateway_settings';
    private const STORE_API_NAMESPACE = 'openpay_cards_impoconsumo';

    private static $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        add_action('woocommerce_product_options_pricing', [__CLASS__, 'render_product_field']);
        add_action('woocommerce_admin_process_product_object', [__CLASS__, 'save_product_field']);

        add_action('woocommerce_product_after_variable_attributes', [__CLASS__, 'render_variation_field'], 10, 3);
        add_action('woocommerce_save_product_variation', [__CLASS__, 'save_variation_field'], 10, 2);

        add_action('woocommerce_review_order_before_order_total', [__CLASS__, 'render_classic_checkout_total']);

        add_action('woocommerce_checkout_create_order', [__CLASS__, 'save_order_total_meta'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [__CLASS__, 'save_order_line_item_meta'], 10, 4);

        add_action('woocommerce_blocks_loaded', [__CLASS__, 'register_store_api_data']);
    }

    public static function is_enabled(): bool
    {
        $settings = get_option(self::SETTINGS_KEY, []);

        if (!is_array($settings)) {
            return false;
        }

        return ($settings['country'] ?? '') === 'CO'
            && ($settings['impoconsumo'] ?? 'no') === 'yes';
    }

    public static function render_product_field(): void
    {
        if (!self::is_enabled()) {
            return;
        }

        woocommerce_wp_text_input([
            'id' => self::PRODUCT_META_KEY,
            'label' => __('Impoconsumo', 'openpay-cards'),
            'description' => __('Valor informativo de Impoconsumo para este producto. No se suma al total del pedido.', 'openpay-cards'),
            'desc_tip' => true,
            'type' => 'number',
            'data_type' => 'price',
            'custom_attributes' => [
                'min' => '0',
                'step' => '0.01',
                'inputmode' => 'decimal',
            ],
        ]);
    }

    public static function save_product_field(\WC_Product $product): void
    {
        if (!self::is_enabled()) {
            return;
        }

        if (!current_user_can('edit_product', $product->get_id())) {
            return;
        }

        $raw_value = isset($_POST[self::PRODUCT_META_KEY])
            ? wc_clean(wp_unslash($_POST[self::PRODUCT_META_KEY]))
            : '';

        self::set_product_impoconsumo_meta($product, $raw_value);
    }

    public static function render_variation_field(int $loop, array $variation_data, \WP_Post $variation): void
    {
        if (!self::is_enabled()) {
            return;
        }

        woocommerce_wp_text_input([
            'id' => self::PRODUCT_META_KEY . '_' . $variation->ID,
            'name' => self::PRODUCT_META_KEY . '[' . $variation->ID . ']',
            'value' => get_post_meta($variation->ID, self::PRODUCT_META_KEY, true),
            'label' => __('Impoconsumo', 'openpay-cards'),
            'description' => __('Valor informativo de Impoconsumo para esta variación.', 'openpay-cards'),
            'desc_tip' => true,
            'type' => 'number',
            'data_type' => 'price',
            'wrapper_class' => 'form-row form-row-full',
            'custom_attributes' => [
                'min' => '0',
                'step' => '0.01',
                'inputmode' => 'decimal',
            ],
        ]);
    }

    public static function save_variation_field(int $variation_id, int $i): void
    {
        if (!self::is_enabled()) {
            return;
        }

        if (!current_user_can('edit_product', $variation_id)) {
            return;
        }

        $posted_values = isset($_POST[self::PRODUCT_META_KEY])
            ? wp_unslash($_POST[self::PRODUCT_META_KEY])
            : [];

        if (!is_array($posted_values) || !array_key_exists($variation_id, $posted_values)) {
            return;
        }

        $product = wc_get_product($variation_id);

        if (!$product instanceof \WC_Product) {
            return;
        }

        self::set_product_impoconsumo_meta($product, wc_clean($posted_values[$variation_id]));
        $product->save();
    }

    public static function render_classic_checkout_total(): void
    {
        $total = self::get_cart_impoconsumo_total();

        if ($total <= 0) {
            return;
        }

        echo '<tr class="openpay-impoconsumo-total">';
        echo '<th>' . esc_html__('Impoconsumo', 'openpay-cards') . '</th>';
        echo '<td>' . wp_kses_post(wc_price($total)) . '</td>';
        echo '</tr>';
    }

    public static function register_store_api_data(): void
    {
        if (
            !function_exists('woocommerce_store_api_register_endpoint_data')
            || !class_exists(CartSchema::class)
        ) {
            return;
        }

        woocommerce_store_api_register_endpoint_data([
            'endpoint' => CartSchema::IDENTIFIER,
            'namespace' => self::STORE_API_NAMESPACE,
            'data_callback' => [__CLASS__, 'get_store_api_cart_data'],
            'schema_callback' => [__CLASS__, 'get_store_api_cart_schema'],
            'schema_type' => ARRAY_A,
        ]);
    }

    public static function get_store_api_cart_data(): array
    {
        $total = self::get_cart_impoconsumo_total();

        return [
            'enabled' => self::is_enabled(),
            'total' => wc_format_decimal($total, wc_get_price_decimals()),
            'total_formatted' => wp_strip_all_tags(wc_price($total)),
        ];
    }

    public static function get_store_api_cart_schema(): array
    {
        return [
            'properties' => [
                'enabled' => [
                    'description' => __('Indica si Impoconsumo está habilitado.', 'openpay-cards'),
                    'type' => 'boolean',
                    'readonly' => true,
                ],
                'total' => [
                    'description' => __('Total informativo de Impoconsumo.', 'openpay-cards'),
                    'type' => 'string',
                    'readonly' => true,
                ],
                'total_formatted' => [
                    'description' => __('Total informativo de Impoconsumo formateado.', 'openpay-cards'),
                    'type' => 'string',
                    'readonly' => true,
                ],
            ],
        ];
    }

    public static function save_order_total_meta(\WC_Order $order, array $data): void
    {
        $total = self::get_cart_impoconsumo_total();

        if ($total <= 0) {
            return;
        }

        $order->update_meta_data(
            self::ORDER_TOTAL_META_KEY,
            wc_format_decimal($total, wc_get_price_decimals())
        );
    }

    public static function save_order_line_item_meta(
        \WC_Order_Item_Product $item,
        string $cart_item_key,
        array $values,
        \WC_Order $order
    ): void {
        if (!self::is_enabled()) {
            return;
        }

        if (empty($values['data']) || !$values['data'] instanceof \WC_Product) {
            return;
        }

        $unit_value = self::get_product_impoconsumo_value($values['data']);
        $quantity = isset($values['quantity']) ? (float) $values['quantity'] : 1;
        $line_total = $unit_value * $quantity;

        if ($unit_value <= 0) {
            return;
        }

        $item->add_meta_data(
            self::ORDER_ITEM_UNIT_META_KEY,
            wc_format_decimal($unit_value, wc_get_price_decimals()),
            true
        );

        $item->add_meta_data(
            self::ORDER_ITEM_TOTAL_META_KEY,
            wc_format_decimal($line_total, wc_get_price_decimals()),
            true
        );
    }

    public static function get_cart_impoconsumo_total(): float
    {
        if (!self::is_enabled()) {
            return 0.0;
        }

        if (!function_exists('WC') || !(WC()->cart instanceof \WC_Cart)) {
            return 0.0;
        }

        $total = 0.0;

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (empty($cart_item['data']) || !$cart_item['data'] instanceof \WC_Product) {
                continue;
            }

            $quantity = isset($cart_item['quantity']) ? (float) $cart_item['quantity'] : 1;
            $total += self::get_product_impoconsumo_value($cart_item['data']) * $quantity;
        }

        return max(0.0, $total);
    }

    private static function get_product_impoconsumo_value(\WC_Product $product): float
    {
        $value = $product->get_meta(self::PRODUCT_META_KEY, true);

        if (($value === '' || $value === null) && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();

            if ($parent_id) {
                $value = get_post_meta($parent_id, self::PRODUCT_META_KEY, true);
            }
        }

        if ($value === '' || $value === null) {
            return 0.0;
        }

        $value = wc_format_decimal($value);

        if ($value === '' || (float) $value < 0) {
            return 0.0;
        }

        return (float) $value;
    }

    private static function set_product_impoconsumo_meta(\WC_Product $product, $raw_value): void
    {
        if ($raw_value === '' || $raw_value === null) {
            $product->delete_meta_data(self::PRODUCT_META_KEY);
            return;
        }

        $value = wc_format_decimal($raw_value);

        if ($value === '' || (float) $value < 0) {
            $product->delete_meta_data(self::PRODUCT_META_KEY);
            return;
        }

        $product->update_meta_data(self::PRODUCT_META_KEY, $value);
    }
}