<?php

use OpenpayCards\Includes\OpenpayImpoconsumo;

if (!class_exists(OpenpayImpoconsumo::class)) {
    require_once dirname(__DIR__, 2) . '/Includes/OpenpayImpoconsumo.php';
}

final class OpenpayImpoconsumoTest extends WP_UnitTestCase
{
    private const SETTINGS_KEY = 'woocommerce_wc_openpay_gateway_settings';
    private const PRODUCT_META_KEY = '_openpay_impoconsumo';
    private const ORDER_TOTAL_META_KEY = '_openpay_impoconsumo_total';
    private const ORDER_ITEM_UNIT_META_KEY = '_openpay_impoconsumo_unit';
    private const ORDER_ITEM_TOTAL_META_KEY = '_openpay_impoconsumo_line_total';

    protected function setUp(): void
    {
        parent::setUp();

        update_option(self::SETTINGS_KEY, [
            'country' => 'CO',
            'impoconsumo' => 'yes',
        ]);

        update_option('woocommerce_currency', 'COP');
        update_option('woocommerce_price_thousand_sep', ',');
        update_option('woocommerce_price_decimal_sep', '.');
        update_option('woocommerce_price_num_decimals', 2);

        $this->resetWooCommerceCart();
    }

    protected function tearDown(): void
    {
        if (function_exists('WC') && WC()->cart) {
            WC()->cart->empty_cart();
        }

        delete_option(self::SETTINGS_KEY);

        parent::tearDown();
    }

    public function test_is_enabled_returns_true_for_colombia_when_impoconsumo_is_enabled(): void
    {
        $this->assertTrue(OpenpayImpoconsumo::is_enabled());
    }

    public function test_is_enabled_returns_false_when_country_is_not_colombia(): void
    {
        update_option(self::SETTINGS_KEY, [
            'country' => 'MX',
            'impoconsumo' => 'yes',
        ]);

        $this->assertFalse(OpenpayImpoconsumo::is_enabled());
    }

    public function test_is_enabled_returns_false_when_impoconsumo_is_disabled(): void
    {
        update_option(self::SETTINGS_KEY, [
            'country' => 'CO',
            'impoconsumo' => 'no',
        ]);

        $this->assertFalse(OpenpayImpoconsumo::is_enabled());
    }

    public function test_get_cart_impoconsumo_total_sums_product_value_by_quantity(): void
    {
        $product_a = $this->createSimpleProduct('100.00', '2.50');
        $product_b = $this->createSimpleProduct('150.00', '3.00');

        WC()->cart->add_to_cart($product_a->get_id(), 2);
        WC()->cart->add_to_cart($product_b->get_id(), 1);

        $this->assertEqualsWithDelta(
            8.00,
            OpenpayImpoconsumo::get_cart_impoconsumo_total(),
            0.0001
        );
    }

    public function test_get_cart_impoconsumo_total_returns_zero_when_disabled(): void
    {
        update_option(self::SETTINGS_KEY, [
            'country' => 'CO',
            'impoconsumo' => 'no',
        ]);

        $product = $this->createSimpleProduct('100.00', '10.00');

        WC()->cart->add_to_cart($product->get_id(), 2);

        $this->assertSame(0.0, OpenpayImpoconsumo::get_cart_impoconsumo_total());
    }

    public function test_variation_uses_parent_impoconsumo_when_variation_has_no_value(): void
    {
        $parent = new WC_Product_Variable();
        $parent->set_name('Producto variable de prueba');
        $parent->set_status('publish');
        $parent->update_meta_data(self::PRODUCT_META_KEY, '4.00');
        $parent_id = $parent->save();

        $variation = new WC_Product_Variation();
        $variation->set_parent_id($parent_id);
        $variation->set_status('publish');
        $variation->set_regular_price('50.00');
        $variation->set_price('50.00');
        $variation->set_stock_status('instock');
        $variation_id = $variation->save();

        $variation_product = wc_get_product($variation_id);

        WC()->cart->cart_contents['variation-test-key'] = [
            'key' => 'variation-test-key',
            'product_id' => $parent_id,
            'variation_id' => $variation_id,
            'quantity' => 2,
            'data' => $variation_product,
        ];

        $this->assertEqualsWithDelta(
            8.00,
            OpenpayImpoconsumo::get_cart_impoconsumo_total(),
            0.0001
        );
    }

    public function test_store_api_cart_data_returns_enabled_total_and_formatted_total(): void
    {
        $product = $this->createSimpleProduct('100.00', '10000.00');

        WC()->cart->add_to_cart($product->get_id(), 1);

        $data = OpenpayImpoconsumo::get_store_api_cart_data();

        $this->assertTrue($data['enabled']);
        $this->assertSame('10000.00', $data['total']);
        $this->assertStringContainsString('10,000.00', $data['total_formatted']);
    }

    public function test_store_api_cart_schema_contains_expected_properties(): void
    {
        $schema = OpenpayImpoconsumo::get_store_api_cart_schema();

        $this->assertArrayHasKey('enabled', $schema);
        $this->assertArrayHasKey('total', $schema);
        $this->assertArrayHasKey('total_formatted', $schema);

        $this->assertSame('boolean', $schema['enabled']['type']);
        $this->assertSame('string', $schema['total']['type']);
        $this->assertSame('string', $schema['total_formatted']['type']);
    }

    public function test_save_order_total_meta_saves_cart_impoconsumo_total(): void
    {
        $product = $this->createSimpleProduct('100.00', '5.00');

        WC()->cart->add_to_cart($product->get_id(), 3);

        $order = new WC_Order();

        OpenpayImpoconsumo::save_order_total_meta($order, []);

        $this->assertSame('15.00', $order->get_meta(self::ORDER_TOTAL_META_KEY));
    }

    public function test_save_order_total_meta_does_not_save_when_total_is_zero(): void
    {
        $product = $this->createSimpleProduct('100.00', '0');

        WC()->cart->add_to_cart($product->get_id(), 3);

        $order = new WC_Order();

        OpenpayImpoconsumo::save_order_total_meta($order, []);

        $this->assertSame('', $order->get_meta(self::ORDER_TOTAL_META_KEY));
    }

    public function test_save_order_line_item_meta_saves_unit_and_line_total(): void
    {
        $item = new WC_Order_Item_Product();
        $order = new WC_Order();
        $product = $this->createSimpleProduct('100.00', '2.50');

        OpenpayImpoconsumo::save_order_line_item_meta(
            $item,
            'cart-key',
            [
                'data' => $product,
                'quantity' => 4,
            ],
            $order
        );

        $this->assertSame('2.50', $item->get_meta(self::ORDER_ITEM_UNIT_META_KEY));
        $this->assertSame('10.00', $item->get_meta(self::ORDER_ITEM_TOTAL_META_KEY));
    }

    public function test_save_order_line_item_meta_does_not_save_when_unit_value_is_zero(): void
    {
        $item = new WC_Order_Item_Product();
        $order = new WC_Order();
        $product = $this->createSimpleProduct('100.00', '0');

        OpenpayImpoconsumo::save_order_line_item_meta(
            $item,
            'cart-key',
            [
                'data' => $product,
                'quantity' => 4,
            ],
            $order
        );

        $this->assertSame('', $item->get_meta(self::ORDER_ITEM_UNIT_META_KEY));
        $this->assertSame('', $item->get_meta(self::ORDER_ITEM_TOTAL_META_KEY));
    }

    public function test_render_classic_checkout_total_outputs_impoconsumo_row(): void
    {
        $product = $this->createSimpleProduct('100.00', '3.00');

        WC()->cart->add_to_cart($product->get_id(), 2);

        ob_start();
        OpenpayImpoconsumo::render_classic_checkout_total();
        $html = ob_get_clean();

        $this->assertStringContainsString('openpay-impoconsumo-total', $html);
        $this->assertStringContainsString('Impoconsumo', $html);
        $this->assertStringContainsString('6.00', $html);
    }

    public function test_save_product_field_updates_product_meta(): void
    {
        $product = $this->createSimpleProduct('100.00');

        $_POST[self::PRODUCT_META_KEY] = '123.45';

        OpenpayImpoconsumo::save_product_field($product);

        $this->assertSame('123.45', $product->get_meta(self::PRODUCT_META_KEY));
    }

    public function test_save_product_field_deletes_meta_when_value_is_empty(): void
    {
        $product = $this->createSimpleProduct('100.00', '50.00');

        $_POST[self::PRODUCT_META_KEY] = '';

        OpenpayImpoconsumo::save_product_field($product);

        $this->assertSame('', $product->get_meta(self::PRODUCT_META_KEY));
    }

    private function createSimpleProduct(string $price = '100.00', ?string $impoconsumo = null): WC_Product_Simple
    {
        $product = new WC_Product_Simple();
        $product->set_name('Producto simple de prueba');
        $product->set_status('publish');
        $product->set_regular_price($price);
        $product->set_price($price);
        $product->set_stock_status('instock');

        if ($impoconsumo !== null) {
            $product->update_meta_data(self::PRODUCT_META_KEY, $impoconsumo);
        }

        $product->save();

        return $product;
    }

    private function resetWooCommerceCart(): void
    {
        if (!WC()->session) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }

        if (!WC()->customer) {
            WC()->customer = new WC_Customer(0, true);
        }

        WC()->cart = new WC_Cart();
        WC()->cart->empty_cart();
    }
}