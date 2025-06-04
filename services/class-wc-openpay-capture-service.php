<?php

Class WC_Openpay_Capture_Service extends WC_Payment_Gateway{

    public function __construct(){
        parent::__construct();
    }

    public function openpay_cards_init_your_gateway() {
        if (class_exists('WC_Payment_Gateway')) {
            require_once('../class-wc-openpay-gateway.php');
        }
    }

    // Partial capture.
    add_action('woocommerce_order_item_add_action_buttons','add_partial_capture_toggle', 10, 1 );

    add_action('wp_ajax_wc_openpay_admin_order_capture','ajax_capture_handler');


    function openpay_woocommerce_order_status_change_custom($order_id, $old_status, $new_status)
    {
        $order = wc_get_order( $order_id );
        // Execute only if there are not a partial capture yet
        if ($order->get_meta('_captured_total') == null) {
            $logger = wc_get_logger();
            $logger->info('openpay_woocommerce_order_status_change_custom');
            $logger->info('$old_status: ' . $old_status);
            $logger->info('$new_status: ' . $new_status);

            $order = wc_get_order($order_id);
            if ($order->get_payment_method() != 'openpay_cards') {
                $logger->info('get_payment_method: ' . $order->get_payment_method());
                return;
            }

            $expected_new_status = array('completed', 'processing');
            $transaction_id = $order->get_meta('_transaction_id');
            $capture = $order->get_meta('_openpay_capture');
            $logger->info('$capture: ' . $capture);

            if ($capture == 'false' && $old_status == 'on-hold' && in_array($new_status, $expected_new_status)) {
                try {
                    $openpay_cards = new Openpay_Cards();
                    $openpay = $openpay_cards->getOpenpayInstance();
                    $settings = $openpay_cards->init_settings();

                    if (strcmp($settings['sandbox'], 'yes')) {
                        $customer_id = $order->get_meta('_openpay_customer_sandbox_id');
                    } else {
                        $customer_id = $order->get_meta('_openpay_customer_id');
                    }

                    $customer = $openpay->customers->get($customer_id);
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
        else{
            $order->set_total($order->get_meta('_captured_total'));
            $order->save();
        }
    }

    function add_partial_capture_toggle( $order ) {
        $openpay_cards = new Openpay_Cards();
        if ($openpay_cards->is_preauthorized_order($order)){

            $auth_total       = $openpay_cards->get_order_auth_amount( $order );
            $auth_remaining   = $openpay_cards->get_order_auth_remaining( $order );
            $already_captured = $openpay_cards->get_order_captured_total( $order );

            if ( $auth_remaining < 1 ) {
                return;
            }

            include( plugin_dir_path( __FILE__ ) . 'templates/partial-capture.php' );
        }
    }


    public function ajax_capture_handler() {
        $order_id = $_POST['order_id'];
        $amount   = isset( $_POST['amount'] ) ? $_POST['amount'] : 0;

        try {
            check_ajax_referer( 'wc_openpay_admin_order_capture-' . $order_id, 'capture_nonce' );
            $order = wc_get_order( $order_id );
            // Capture.
            $openpay_cards = new Openpay_Cards();
            $openpay = $openpay_cards->getOpenpayInstance();
            $settings = $openpay_cards->init_settings();
            $transaction_id = $order->get_meta('_transaction_id');
            if(strcmp($settings['sandbox'], 'yes')){
                $customer_id = $order->get_meta('_openpay_customer_sandbox_id');
            }else{
                $customer_id = $order->get_meta('_openpay_customer_id');
            }

            $customer = $openpay->customers->get($customer_id);
            $charge = $customer->charges->get($transaction_id);
            $charge->capture(array(
                'amount' => floatval($amount)
            ));

            // Actualizar valor de Captura total en los metadatos de la orden
            $order->update_meta_data( '_captured_total', $amount );
            $order->set_total($amount);
            $order->payment_complete();
            $order->save();

            $order->add_order_note('Payment was captured in Openpay');

            if ( $charge ) {
                wp_send_json_success();
            } else {
                throw new Exception( 'Capture not successful.' );
            }
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'error' => $e->getMessage() ) );
        }
        wp_die();
    }

}