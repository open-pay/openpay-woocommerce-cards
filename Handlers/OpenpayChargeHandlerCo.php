<?php
namespace OpenpayCards\Handlers;
use OpenpayCards\Includes\OpenpayImpoconsumo;
use OpenpayCards\Services\PaymentSettings\Openpay3dSecure;
use OpenpayCards\Services\PaymentSettings\OpenpayIVA;
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
            $IVA = new OpenpayIVA();
            $charge_request['taxes']['base_amount'] = $order->get_total();
            $charge_request['taxes']['iva_amount']  = $IVA->getTotalIVA();
        }

        // APLICA Impoconsumo
        if (isset($payment_settings['impoconsumo']) && $payment_settings['impoconsumo'] != 0){
            $impoconsumo = new OpenpayImpoconsumo();
            $charge_request['taxes']['consumption_tax_amount']  = $impoconsumo->get_cart_impoconsumo_total();
        }

        $this->logger->info("[OpenpayChargeHandlerCo.applyPaymentSettings] => " . json_encode($charge_request) );
        return $charge_request;
    }

}