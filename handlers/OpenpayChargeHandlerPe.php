<?php
namespace OpenpayCards\Handlers;
use OpenpayCards\Services\PaymentSettings\Openpay3dSecure;
class OpenpayChargeHandlerPe {

    public function __construct()
    {
        $this->logger = wc_get_logger();
    }
    public function applyPaymentSettings($charge_request,$payment_settings){

        // CUOTAS CON/SIN INTERÉS
        if (isset($payment_settings['openpay_payment_plan'])){
            if(isset($payment_settings['openpay_has_interest_pe']) && $payment_settings['openpay_has_interest_pe'] == true) {
                $charge_request["payment_plan"] = array("payments" => $payment_settings['openpay_payment_plan'], "payments_type" => "WITH_INTEREST");
            } else {
                $charge_request["payment_plan"] = array("payments" => $payment_settings['openpay_payment_plan'], "payments_type" => "WITHOUT_INTEREST");
            }
        }

        // 3D SECURE
        if ($payment_settings['openpay_charge_type'] == '3d') {
            $charge_request['use_3d_secure'] = true;
            $charge_request['redirect_url'] = Openpay3dSecure::redirect_url_3d();
        }

        // PRE-AUTHORIZATION & CAPTURE
        if (isset($payment_settings['capture'])){
            $charge_request["capture"] = $payment_settings['capture'];
        }

        $this->logger->info("[OpenpayChargeHandlerCo.applyPaymentSettings] => " . json_encode($charge_request) );
        return $charge_request;
    }

}