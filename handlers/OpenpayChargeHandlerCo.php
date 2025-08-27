<?php
namespace OpenpayCards\Handlers;
use OpenpayCards\Services\PaymentSettings\Openpay3dSecure;
class OpenpayChargeHandlerCo {

    public function __construct()
    {
        $this->logger = wc_get_logger();
    }
    public function applyPaymentSettings($charge_request,$payment_settings){

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

        $this->logger->info("[OpenpayChargeHandlerCo.applyPaymentSettings] => " . json_encode($charge_request) );
        return $charge_request;
    }

}