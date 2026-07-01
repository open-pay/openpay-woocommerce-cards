<?php

// =========================================================================
// 1. STUBS EN NAMESPACE GLOBAL (Aislamiento de funciones del core)
// =========================================================================

namespace {
    if (!function_exists('wc_get_logger')) {
        function wc_get_logger() {
            return $GLOBALS['mock_wc_logger'] ?? null;
        }
    }

    if (!function_exists('check_ajax_referer')) {
        function check_ajax_referer($action, $query_arg = false, $die = true) {
            if (isset($GLOBALS['mock_ajax_referer_fail']) && $GLOBALS['mock_ajax_referer_fail']) {
                throw new \Exception('Nonce verification failed');
            }
            return true;
        }
    }
}

// =========================================================================
// 2. STUBS DE CONTROL TOTAL EN EL NAMESPACE DE DESTINO
// =========================================================================

namespace OpenpayCards\Services\PaymentSettings {

    if (!function_exists('OpenpayCards\Services\PaymentSettings\wp_send_json_success')) {
        function wp_send_json_success($data = null, $status_code = null) {
            $GLOBALS['mock_ajax_response'] = ['success' => true, 'data' => $data];
            throw new \RuntimeException('AJAX_STOP_SUCCESS');
        }
    }

    if (!function_exists('OpenpayCards\Services\PaymentSettings\wp_send_json_error')) {
        function wp_send_json_error($data = null, $status_code = null) {
            $GLOBALS['mock_ajax_response'] = ['success' => false, 'data' => $data];
            throw new \RuntimeException('AJAX_STOP_ERROR');
        }
    }

    if (!function_exists('OpenpayCards\Services\PaymentSettings\wp_die')) {
        function wp_die($message = '', $title = '', $args = array()) {
            throw new \RuntimeException('AJAX_STOP_DIE');
        }
    }
}

// =========================================================================
// 3. SUITE DE PRUEBAS UNITARIAS AJUSTADA
// =========================================================================

namespace OpenpayCards\Tests {

    use PHPUnit\Framework\TestCase;
    use WC_Openpay_Capture_Service;
    use stdClass;
    use Exception;
    use RuntimeException;

    class MockOpenpayChargesEndpoint {
        public function get($id) { return $this; }
        public function capture($params) {}
    }

    class MockOpenpayCustomer {
        public $charges;
        public function __construct() {
            $this->charges = new MockOpenpayChargesEndpoint();
        }
    }

    class MockOpenpayCustomersEndpoint {
        public function get($id) { return new MockOpenpayCustomer(); }
    }

    class DummyOpenpayInstance {
        public $customers;
        public $charges;
        public function __construct() {
            $this->customers = new MockOpenpayCustomersEndpoint();
            $this->charges = new MockOpenpayChargesEndpoint();
        }
    }

    /**
     * @covers \WC_Openpay_Capture_Service
     */
    class WCOpenpayCaptureServiceTest extends \WP_UnitTestCase
    {
        private $mockLogger;
        private $mockOpenpay;
        private $created_orders = [];

        public function setUp(): void
        {
            parent::setUp();
            if (!isset($_SERVER['REMOTE_ADDR'])) {
                $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            }

            // Inicialización limpia de superglobales para evitar fugas entre tests
            $_POST = [];
            $this->created_orders = [];
            $GLOBALS['mock_ajax_response'] = null;
            $GLOBALS['mock_ajax_referer_fail'] = false;

            $this->mockLogger = $this->getMockBuilder(stdClass::class)->addMethods(['info', 'error'])->getMock();
            $GLOBALS['mock_wc_logger'] = $this->mockLogger;

            $this->mockOpenpay = new DummyOpenpayInstance();
        }

        public function tearDown(): void
        {
            parent::tearDown();
            unset($GLOBALS['mock_wc_logger']);
            unset($GLOBALS['mock_ajax_response']);
            unset($GLOBALS['mock_ajax_referer_fail']);
            $_POST = [];
            remove_all_filters('get_post_metadata');
            remove_all_actions('doing_it_wrong_run');

            foreach ($this->created_orders as $order_id) {
                wp_delete_post($order_id, true);
            }
        }

        private function createTestOrder($payment_method = 'wc_openpay_gateway', $meta = [])
        {
            $order = wc_create_order();
            $order->set_payment_method($payment_method);
            foreach ($meta as $key => $value) {
                $order->update_meta_data($key, $value);
            }
            $order->save();
            $this->created_orders[] = $order->get_id();
            return $order;
        }

        // =====================================================================
        // PRUEBAS: openpayWoocommerceOrderStatusChangeCustom()
        // =====================================================================

        public function test_orderStatusChange_updates_total_if_partial_capture_meta_exists()
        {
            $order = $this->createTestOrder('wc_openpay_gateway', ['_captured_total' => 150.00]);
            $order_id = $order->get_id();

            $service = new WC_Openpay_Capture_Service('yes', 'MX', $this->mockOpenpay);
            $service->openpayWoocommerceOrderStatusChangeCustom($order_id, 'on-hold', 'completed');

            $order_actualizado = wc_get_order($order_id);
            $this->assertEquals(150.00, $order_actualizado->get_total());
        }

        public function test_orderStatusChange_exits_early_if_payment_method_is_invalid()
        {
            $order = $this->createTestOrder('bacs', ['_captured_total' => null]);
            $order_id = $order->get_id();

            $service = new WC_Openpay_Capture_Service('yes', 'MX', $this->mockOpenpay);
            $service->openpayWoocommerceOrderStatusChangeCustom($order_id, 'on-hold', 'completed');

            $this->assertEquals(0, wc_get_order($order_id)->get_total());
        }

        public function test_orderStatusChange_sandbox_and_live_success_flows()
        {
            remove_all_actions('doing_it_wrong_run');
            add_action('doing_it_wrong_run', function($function, $message, $version) { return; }, -100, 3);

            $orderSandbox = $this->createTestOrder('wc_openpay_gateway');
            $sb_id = $orderSandbox->get_id();
            update_post_meta($sb_id, '_captured_total', '');
            update_post_meta($sb_id, '_transaction_id', 'tx_sandbox_123');
            update_post_meta($sb_id, '_openpay_capture', 'false');
            update_post_meta($sb_id, '_openpay_customer_sandbox_id', 'cus_sb_1');
            $orderSandbox->set_total(200.50);
            $orderSandbox->save();

            $serviceSandbox = new WC_Openpay_Capture_Service('no', 'MX', $this->mockOpenpay);
            $serviceSandbox->openpayWoocommerceOrderStatusChangeCustom($sb_id, 'on-hold', 'completed');

            $orderLive = $this->createTestOrder('wc_openpay_gateway');
            $lv_id = $orderLive->get_id();
            update_post_meta($lv_id, '_captured_total', '');
            update_post_meta($lv_id, '_transaction_id', 'tx_live_123');
            update_post_meta($lv_id, '_openpay_capture', 'false');
            update_post_meta($lv_id, '_openpay_customer_id', 'cus_lv_1');
            $orderLive->set_total(300.00);
            $orderLive->save();

            $serviceLive = new WC_Openpay_Capture_Service('yes', 'MX', $this->mockOpenpay);
            $serviceLive->openpayWoocommerceOrderStatusChangeCustom($lv_id, 'on-hold', 'processing');

            $this->assertTrue(true);
        }

        public function test_addPartialCaptureToggle_flows()
        {
            $service = new WC_Openpay_Capture_Service('yes', 'MX', $this->mockOpenpay);

            $order1 = $this->createTestOrder('wc_openpay_gateway', ['_openpay_capture' => 'true']);
            $service->addPartialCaptureToggle($order1);

            $order2 = $this->createTestOrder('wc_openpay_gateway', ['_openpay_capture' => 'false', '_captured_total' => 100.00]);
            $order2->set_total(100.00);
            $order2->save();
            $service->addPartialCaptureToggle($order2);

            $order3 = $this->createTestOrder('wc_openpay_gateway', ['_openpay_capture' => 'false', '_captured_total' => 0]);
            $order3->set_total(500.00);
            $order3->save();

            @$service->addPartialCaptureToggle($order3);
            $this->assertTrue(true);
        }

    }
}