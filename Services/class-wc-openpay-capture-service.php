<?php

class WC_Openpay_Capture_Service
{
    private $logger;
    private $sandbox;
    private $country;
    private $openpayInstance;

    public function __construct($sandbox, $country, $openpayInstance)
    {
        $this->logger = wc_get_logger();
        $this->sandbox = $sandbox;
        $this->country = $country;
        $this->openpayInstance = $openpayInstance;
    }

    function openpayWoocommerceOrderStatusChangeCustom($order_id, $old_status, $new_status)
    {
        $order = wc_get_order($order_id);
        // Execute only if there are not a partial capture yet
        if ($order->get_meta('_captured_total') == null) {
            $logger = wc_get_logger();
            $logger->info('openpay_woocommerce_order_status_change_custom');
            $logger->info('$old_status: ' . $old_status);
            $logger->info('$new_status: ' . $new_status);

            if ($order->get_payment_method() != 'wc_openpay_gateway') {
                $logger->info('get_payment_method: ' . $order->get_payment_method());
                return;
            }

            $expected_new_status = array('completed', 'processing');
            $transaction_id = $order->get_meta('_transaction_id');
            $capture = $order->get_meta('_openpay_capture');
            $logger->info('$capture: ' . $capture);

            if ($capture == 'false' && $old_status == 'on-hold' && in_array($new_status, $expected_new_status)) {
                $this->logger->info("Entra a la captura de Hook");

                try {

                    if (strcmp($this->sandbox, 'yes')) {
                        $this->logger->info("Entra a la captura de Hook Sandbox");
                        $customer_id = $order->get_meta('_openpay_customer_sandbox_id');
                    } else {
                        $this->logger->info("Entra a la captura de Hook Live");
                        $customer_id = $order->get_meta('_openpay_customer_id');
                    }

                    $customer = $this->openpayInstance->customers->get($customer_id);
                    $charge = $customer->charges->get($transaction_id);
                    $charge->capture(array(
                        'amount' => floatval($order->get_total())
                    ));
                    $order->add_order_note('Payment was captured in Openpay');
                } catch (Exception $e) {
                    $logger->error($e->getMessage());
                    $order->add_order_note('There was an error with Openpay plugin: ' . $e->getMessage());
                }
            }
        }
        // Update the total order with the total captured value
        else {
            $order->set_total($order->get_meta('_captured_total'));
            $order->save();
        }
    }

    function addPartialCaptureToggle($order)
    {
        if ($this->is_preauthorized_order($order)) {
            $this->logger->info("Entra al handler capture toggle is preauthorized order");
            $auth_total = $this->get_order_auth_amount($order);
            $auth_remaining = $this->get_order_auth_remaining($order);
            $already_captured = $this->get_order_captured_total($order);

            if ($auth_remaining < 1) {
                return;
            }

            include(dirname(__DIR__) . '/templates/partial-capture.php');
        }
    }


    public function ajaxCaptureHandler()
    {
        $order_id = $_POST['order_id'];
        $amount = isset($_POST['amount']) ? $_POST['amount'] : 0;
        $charge = null;

        $this->logger->info("AJAX Capture Handler [Order ID: " . $order_id . "] - Amount: [" . $amount . "]");

        try {
            check_ajax_referer('wc_openpay_admin_order_capture-' . $order_id, 'capture_nonce');
            $order = wc_get_order($order_id);

            $this->logger->info("AJAX Capture Handler Order " . $order);

            // Capture.

            //$settings = $openpay_cards->init_settings();
            $transaction_id = $order->get_meta('_transaction_id');

            if ($this->sandbox === 'yes' && $order->meta_exists('_openpay_customer_sandbox_id')) {
                $customer_id = $order->get_meta('_openpay_customer_sandbox_id');
                $this->logger->info("AJAX Sandbox and logged customer_id: " . $customer_id);
                $customer = $this->openpayInstance->customers->get($customer_id);
                $charge = $customer->charges->get($transaction_id);
                $charge->capture(array(
                    'amount' => floatval($amount)
                ));
            } else if ($this->sandbox !== 'yes' && $order->meta_exists('_openpay_customer_id')) {
                $customer_id = $order->get_meta('_openpay_customer_id');
                $this->logger->info("AJAX Live and logged customer_id: " . $customer_id);
                $customer = $this->openpayInstance->customers->get($customer_id);
                $charge = $customer->charges->get($transaction_id);
                $charge->capture(array(
                    'amount' => floatval($amount)
                ));
            } else {
                $this->logger->info("AJAX Sandbox transaction_id: " . $transaction_id);
                $charge = $this->openpayInstance->charges->get($transaction_id);
                $charge->capture(array(
                    'amount' => floatval($amount)
                ));
                //throw new Exception('Customer not found.');
            }

            // Actualizar valor de Captura total en los metadatos de la orden
            $order->update_meta_data('_captured_total', $amount);
            $order->set_total($amount);
            $order->payment_complete();
            $order->save();

            $order->add_order_note('Payment was captured in Openpay');

            if ($charge) {
                wp_send_json_success();
            } else {
                throw new Exception('Capture not successful.');
            }
        } catch (Exception $e) {
            wp_send_json_error(array('error' => $e->getMessage()));
        }
        wp_die();
    }

    public function is_preauthorized_order($order)
    {
        $this->logger->info("Verifica si es capture " . $order->get_meta('_openpay_capture'));
        return $order->get_meta('_openpay_capture') == 'false';
    }

    public function get_order_auth_amount($order)
    {
        $order_id = $order->get_id();
        $amount = $order->get_total();
        return $amount;
    }

    public function get_order_auth_remaining($order)
    {
        $order_id = $order->get_id();
        $amount = $this->get_order_auth_amount($order) - $this->get_order_captured_total($order);
        return floatval($amount);
    }

    public function get_order_captured_total($order)
    {
        $this->logger->info("Entra a verificar si tiene valor");
        $amount = $order->get_meta('_captured_total') ? $order->get_meta('_captured_total') : 0;
        return floatval($amount);
    }
}