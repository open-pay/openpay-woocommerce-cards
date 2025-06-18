<?php

add_action('woocommerce_api_openpay_confirm', 'openpay_woocommerce_confirm', 10, 0);
add_action('template_redirect', 'wc_custom_redirect_after_purchase');
class WC_Openpay_3d_secure extends WC_Openpay_Gateway
{

    public function __construct()
    {
        parent::__construct();

    }

}
function openpay_woocommerce_confirm()
{
    global $woocommerce;
    $logger = wc_get_logger();

    $id = $_GET['id'];

    $logger->info('openpay_woocommerce_confirm => ' . $id);

    try {
        $openpay_cards = new WC_Openpay_Gateway();
        $openpay = WC_Openpay_Client::getOpenpayInstance($openpay_cards->sandbox, $openpay_cards->merchant_id, $openpay_cards->private_key, $openpay_cards->country);
        $charge = $openpay->charges->get($id);
        $order = new WC_Order($charge->order_id);
        $logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_confirm] => openpay_woocommerce_confirm => ' . json_encode($order));
        $logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_confirm] => openpay_woocommerce_confirm => ' . json_encode(array('id' => $charge->id, 'status' => $charge->status)));

        if ($order && $charge->status != 'completed') {
            if (property_exists($charge, 'authorization') && ($charge->status == 'in_progress' && ($charge->id != $charge->authorization))) {
                $order->set_status('on-hold');
                $order->save();
            } else {
                $order->add_order_note(sprintf("%s Credit Card Payment Failed with message: '%s'", 'Openpay_Cards', 'Status ' . $charge->status));
                $order->set_status('failed');
                $order->save();

                if (function_exists('wc_add_notice')) {
                    wc_add_notice(__('Error en la transacción: No se pudo completar tu pago.'), 'error');
                } else {
                    $woocommerce->add_error(__('Error en la transacción: No se pudo completar tu pago.'), 'woothemes');
                }
            }
        } else if ($order && $charge->status == 'completed') {
            $order->payment_complete();
            $woocommerce->cart->empty_cart();
            $order->add_order_note(sprintf("%s payment completed with Transaction Id of '%s'", 'Openpay_Cards', $charge->id));
        }

        wp_redirect($openpay_cards->get_return_url($order));
    } catch (Exception $e) {
        $logger->error('[WC_Openpay_3d_secure.openpay_woocommerce_confirm] => error'.$e->getMessage());
        status_header(404);
        nocache_headers();
        include(get_query_template('404'));
        die();
    }
}

function wc_custom_redirect_after_purchase() {
    global $wp;
    $logger = wc_get_logger();
    if (is_checkout() && !empty($wp->query_vars['order-received'])) {
        $order = new WC_Order($wp->query_vars['order-received']);
        $redirect_url = $order->get_meta('_openpay_3d_secure_url');
        $logger->debug('[WC_Openpay_3d_secure.wc_custom_redirect_after_purchase] => wc_custom_redirect_after_purchase ');
        $logger->debug('[WC_Openpay_3d_secure.wc_custom_redirect_after_purchase] => 3DS_redirect_url : ' .  $redirect_url);
        $logger->debug('[WC_Openpay_3d_secure.wc_custom_redirect_after_purchase] => order_status : ' .  $order->get_status());

        if ($redirect_url && $order->get_status() != 'processing') {
//        if ($redirect_url && $order->get_status() == 'processing') {
            $order->delete_meta_data('_openpay_3d_secure_url');
            $order->save();
            $logger->debug('[WC_Openpay_3d_secure.wc_custom_redirect_after_purchase] => order not processed redirect_url : ' . $redirect_url);
            wp_redirect($redirect_url);
            exit();
        }
    }
}
