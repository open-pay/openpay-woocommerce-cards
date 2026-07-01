<?php

// =========================================================================
// 1. STUBS DE DEPENDENCIAS (WooCommerce y Estructuras Globales)
// =========================================================================

namespace OpenpayCards\Services\PaymentSettings {

    if (!function_exists('OpenpayCards\Services\PaymentSettings\get_post_meta')) {
        function get_post_meta($post_id, $key, $single = false) {
            return $GLOBALS['mock_post_meta'][$post_id][$key] ?? '';
        }
    }

    if (!function_exists('OpenpayCards\Services\PaymentSettings\WC')) {
        function WC() {
            return $GLOBALS['mock_woocommerce_instance'];
        }
    }
}

// =========================================================================
// 2. CLASE DE PRUEBAS UNITARIAS BLINDADA CONTRA CLI / SERVER ENV
// =========================================================================

namespace OpenpayCards\Tests {

    use PHPUnit\Framework\TestCase;
    use OpenpayCards\Services\PaymentSettings\OpenpayIVA;

    class DummyCart {
        private $items = [];
        public function __construct($items) { $this->items = $items; }
        public function get_cart() { return $this->items; }
    }
    class DummyWooCommerce {
        public $cart;
        public function __construct($cart = null) { $this->cart = $cart; }
    }

    /**
     * @covers \OpenpayCards\Services\PaymentSettings\OpenpayIVA
     */
    class OpenpayIVATest extends \WP_UnitTestCase
    {
        private $gateway;

        public function setUp(): void
        {
            parent::setUp();

            // CORRECCIÓN CRÍTICA: Inicializamos variables de servidor para el entorno CLI
            if (!isset($_SERVER['REMOTE_ADDR'])) {
                $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            }
            if (!isset($_SERVER['SERVER_NAME'])) {
                $_SERVER['SERVER_NAME'] = 'localhost';
            }

            $GLOBALS['mock_post_meta'] = [];
            $GLOBALS['mock_woocommerce_instance'] = new DummyWooCommerce();

            $this->gateway = new OpenpayIVA();
        }

        public function tearDown(): void
        {
            parent::tearDown();
            unset($GLOBALS['mock_post_meta']);
            unset($GLOBALS['mock_woocommerce_instance']);
            // Nota: Se dejan las variables de $_SERVER para evitar romper tests subsecuentes
        }

        // =====================================================================
        // CASO 1: El carrito no está inicializado (Bifurcación isset(WC()->cart))
        // =====================================================================
        public function test_getTotalIVA_returns_zero_if_cart_is_not_set()
        {
            $GLOBALS['mock_woocommerce_instance']->cart = null;

            $total = $this->gateway->getTotalIVA();

            $this->assertEquals(0, $total);
        }

        // =====================================================================
        // CASO 2: Carrito vacío
        // =====================================================================
        public function test_getTotalIVA_returns_zero_if_cart_is_empty()
        {
            $GLOBALS['mock_woocommerce_instance']->cart = new DummyCart([]);

            $total = $this->gateway->getTotalIVA();

            $this->assertEquals(0, $total);
        }

        // =====================================================================
        // CASO 3: Carrito con productos (Con y sin IVA configurado)
        // =====================================================================
        public function test_getTotalIVA_calculates_correctly_with_items()
        {
            $cart_items = [
                'item_1' => [
                    'product_id' => 101,
                    'quantity'   => 2
                ],
                'item_2' => [
                    'product_id' => 102,
                    'quantity'   => 3
                ],
                'item_3' => [
                    'product_id' => 103,
                    'quantity'   => 5
                ]
            ];

            $GLOBALS['mock_woocommerce_instance']->cart = new DummyCart($cart_items);

            $GLOBALS['mock_post_meta'][101]['openpay_taxes_iva'] = '16.50';
            $GLOBALS['mock_post_meta'][102]['openpay_taxes_iva'] = 5.00;
            $GLOBALS['mock_post_meta'][103]['openpay_taxes_iva'] = 'NoAplica';

            $total = $this->gateway->getTotalIVA();

            $this->assertEquals(48.00, $total);
        }
    }
}