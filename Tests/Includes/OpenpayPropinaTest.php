<?php

use OpenpayCards\Includes\OpenpayPropina;

if (!class_exists(OpenpayPropina::class)) {
    require_once dirname(__DIR__, 2) . '/Includes/OpenpayPropina.php';
}

final class OpenpayPropinaTest extends WP_UnitTestCase
{
    private const SETTINGS_KEY = 'woocommerce_wc_openpay_gateway_settings';
    private const SESSION_KEY = 'openpay_propina_amount';
    private const ORDER_META_KEY = '_openpay_propina_amount';

    protected function setUp(): void
    {
        parent::setUp();

        $_POST = [];

        update_option(self::SETTINGS_KEY, [
            'country' => 'CO',
            'propina' => 'yes',
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

        if (function_exists('WC') && WC()->session) {
            WC()->session->set(self::SESSION_KEY, 0);
        }

        delete_option(self::SETTINGS_KEY);

        parent::tearDown();
    }

    public function test_is_enabled_returns_true_for_colombia_when_propina_is_enabled(): void
    {
        $this->assertTrue(OpenpayPropina::is_enabled());
    }

    public function test_is_enabled_returns_false_when_country_is_not_colombia(): void
    {
        update_option(self::SETTINGS_KEY, [
            'country' => 'PE',
            'propina' => 'yes',
        ]);

        $this->assertFalse(OpenpayPropina::is_enabled());
    }

    public function test_is_enabled_returns_false_when_propina_is_disabled(): void
    {
        update_option(self::SETTINGS_KEY, [
            'country' => 'CO',
            'propina' => 'no',
        ]);

        $this->assertFalse(OpenpayPropina::is_enabled());
    }

    public function test_update_classic_checkout_session_normalizes_formatted_amount(): void
    {
        $this->addProductToCart('20000.00');

        OpenpayPropina::update_classic_checkout_session('openpay_propina=10%2C000.50');

        $this->assertEqualsWithDelta(
            10000.50,
            WC()->session->get(self::SESSION_KEY),
            0.0001
        );
    }

    public function test_update_classic_checkout_session_sets_zero_when_propina_is_disabled(): void
    {
        $this->addProductToCart('200.00');

        WC()->session->set(self::SESSION_KEY, 25);

        update_option(self::SETTINGS_KEY, [
            'country' => 'CO',
            'propina' => 'no',
        ]);

        OpenpayPropina::update_classic_checkout_session('openpay_propina=50');

        $this->assertSame(0.0, WC()->session->get(self::SESSION_KEY));
    }

    public function test_update_classic_checkout_session_clamps_tip_to_sale_total(): void
    {
        $this->addProductToCart('100.00');

        OpenpayPropina::update_classic_checkout_session('openpay_propina=150');

        $this->assertEqualsWithDelta(
            100.00,
            WC()->session->get(self::SESSION_KEY),
            0.0001
        );
    }

    public function test_add_tip_fee_adds_non_taxable_fee_when_tip_is_greater_than_zero(): void
    {
        $this->addProductToCart('150.00');

        OpenpayPropina::update_classic_checkout_session('openpay_propina=25');

        OpenpayPropina::add_tip_fee(WC()->cart);

        $fees = WC()->cart->get_fees();

        $this->assertCount(1, $fees);

        $fee = reset($fees);

        $this->assertSame('Propina', $fee->name);
        $this->assertEqualsWithDelta(25.00, (float) $fee->amount, 0.0001);
        $this->assertFalse((bool) $fee->taxable);
    }

    public function test_add_tip_fee_does_not_add_fee_when_tip_is_zero(): void
    {
        $this->addProductToCart('150.00');

        OpenpayPropina::update_classic_checkout_session('openpay_propina=0');

        OpenpayPropina::add_tip_fee(WC()->cart);

        $this->assertCount(0, WC()->cart->get_fees());
    }

    public function test_add_tip_fee_does_not_add_fee_when_propina_is_disabled(): void
    {
        $this->addProductToCart('150.00');

        update_option(self::SETTINGS_KEY, [
            'country' => 'CO',
            'propina' => 'no',
        ]);

        OpenpayPropina::update_classic_checkout_session('openpay_propina=25');

        OpenpayPropina::add_tip_fee(WC()->cart);

        $this->assertCount(0, WC()->cart->get_fees());
    }

    public function test_save_order_meta_saves_tip_amount(): void
    {
        $this->addProductToCart('200.00');

        OpenpayPropina::update_classic_checkout_session('openpay_propina=50');

        $order = new WC_Order();

        OpenpayPropina::save_order_meta($order, []);

        $this->assertSame('50.00', $order->get_meta(self::ORDER_META_KEY));
    }

    public function test_save_order_meta_does_not_save_when_tip_is_zero(): void
    {
        $this->addProductToCart('200.00');

        OpenpayPropina::update_classic_checkout_session('openpay_propina=0');

        $order = new WC_Order();

        OpenpayPropina::save_order_meta($order, []);

        $this->assertSame('', $order->get_meta(self::ORDER_META_KEY));
    }

    public function test_validate_checkout_tip_adds_error_when_tip_is_greater_than_sale_total(): void
    {
        $this->addProductToCart('100.00');

        $_POST['openpay_propina'] = '150';

        $errors = new WP_Error();

        OpenpayPropina::validate_checkout_tip([], $errors);

        $this->assertTrue($errors->has_errors());
        $this->assertArrayHasKey('openpay_propina_invalid_amount', $errors->errors);
    }

    public function test_validate_checkout_tip_does_not_add_error_when_tip_is_valid(): void
    {
        $this->addProductToCart('100.00');

        $_POST['openpay_propina'] = '50';

        $errors = new WP_Error();

        OpenpayPropina::validate_checkout_tip([], $errors);

        $this->assertFalse($errors->has_errors());
    }

    public function test_validate_checkout_tip_does_not_add_error_when_propina_is_disabled(): void
    {
        $this->addProductToCart('100.00');

        update_option(self::SETTINGS_KEY, [
            'country' => 'CO',
            'propina' => 'no',
        ]);

        $_POST['openpay_propina'] = '150';

        $errors = new WP_Error();

        OpenpayPropina::validate_checkout_tip([], $errors);

        $this->assertFalse($errors->has_errors());
    }

    public function test_store_api_cart_data_returns_amount_and_max_amount(): void
    {
        $this->addProductToCart('125.00');

        OpenpayPropina::update_classic_checkout_session('openpay_propina=25');

        $data = OpenpayPropina::get_store_api_cart_data();

        $this->assertTrue($data['enabled']);
        $this->assertSame('25.00', $data['amount']);
        $this->assertSame('100.00', $data['max_amount']);
        $this->assertStringContainsString('100.00', $data['max_amount_formatted']);
    }

    public function test_store_api_cart_schema_contains_expected_properties(): void
    {
        $schema = OpenpayPropina::get_store_api_cart_schema();

        $this->assertArrayHasKey('enabled', $schema);
        $this->assertArrayHasKey('amount', $schema);
        $this->assertArrayHasKey('max_amount', $schema);
        $this->assertArrayHasKey('max_amount_formatted', $schema);

        $this->assertSame('boolean', $schema['enabled']['type']);
        $this->assertSame('string', $schema['amount']['type']);
        $this->assertSame('string', $schema['max_amount']['type']);
        $this->assertSame('string', $schema['max_amount_formatted']['type']);
    }

    public function test_render_classic_checkout_field_outputs_formatted_value_and_max_amount(): void
    {
        $this->addProductToCart('20000.00');

        OpenpayPropina::update_classic_checkout_session('openpay_propina=10000');

        ob_start();
        OpenpayPropina::render_classic_checkout_field();
        $html = ob_get_clean();

        $this->assertStringContainsString('id="openpay_propina"', $html);
        $this->assertStringContainsString('value="10,000.00"', $html);
        $this->assertStringContainsString('data-max="10000.00"', $html);
    }

    public function test_enqueue_classic_checkout_script_adds_inline_script_to_wc_checkout(): void
    {
        $this->addProductToCart('100.00');

        wp_register_script(
            'wc-checkout',
            'https://example.test/wc-checkout.js',
            ['jquery'],
            '1.0.0',
            true
        );

        OpenpayPropina::enqueue_classic_checkout_script();

        $registered = wp_scripts()->registered['wc-checkout'] ?? null;
        $after_scripts = $registered->extra['after'] ?? [];

        $this->assertNotEmpty($after_scripts);
        $this->assertStringContainsString('openpay_propina', implode("\n", $after_scripts));

        wp_deregister_script('wc-checkout');
    }

    private function addProductToCart(string $price = '100.00', int $quantity = 1): WC_Product_Simple
    {
        $product = new WC_Product_Simple();
        $product->set_name('Producto simple de prueba');
        $product->set_status('publish');
        $product->set_regular_price($price);
        $product->set_price($price);
        $product->set_stock_status('instock');
        $product->save();

        $added = WC()->cart->add_to_cart($product->get_id(), $quantity);

        $this->assertNotFalse($added);

        WC()->cart->calculate_totals();

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
        WC()->session->set(self::SESSION_KEY, 0);
    }
}