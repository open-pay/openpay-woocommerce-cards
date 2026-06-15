<?php
namespace OpenpayCards\Handlers;
use OpenpayCards\Services\PaymentSettings\Openpay3dSecure;
class OpenpayChargeHandlerCo {

    public function __construct()
    {
        $this->logger = wc_get_logger();
    }
    public function applyPaymentSettings($charge_request,$payment_settings,$order){

        // CUOTAS
        if (isset($payment_settings['openpay_payment_plan']) && $payment_settings['openpay_payment_plan'] != 1){
            $charge_request["payment_plan"] = array("payments" => $payment_settings['openpay_payment_plan']);
        }

        // 3D SECURE
        if ($payment_settings['openpay_charge_type'] == '3d') {
            $charge_request['use_3d_secure'] = true;
            $charge_request['redirect_url'] = Openpay3dSecure::redirect_url_3d();
        }

        // SOLO APLICA CARGO DIRECTO (capture=true)
        if (isset($payment_settings['capture'])){
            $charge_request["capture"] = $payment_settings['capture'];
        }

        // APLICA IVA
        if (isset($payment_settings['iva']) && $payment_settings['iva'] != 0){

            foreach ( $order->get_items() as $item_id => $item ) {

                // Obtener el ID del producto (o ID de la variación)
                $product_id = $item->get_product_id();

                // Aquí es donde llamas a get_post_meta pasándole el ID del producto real
                $iva_producto = get_post_meta( $product_id, 'openpay_taxes_iva', true );
                $total_iva += (float) $iva_producto;
            }
            $charge_request['taxes'] = array(
                "base_amount" => $order->get_total(),
                "iva_amount" => $total_iva
            );
        }

        $this->logger->info("[OpenpayChargeHandlerCo.applyPaymentSettings] => " . json_encode($charge_request) );
        return $charge_request;
    }

}