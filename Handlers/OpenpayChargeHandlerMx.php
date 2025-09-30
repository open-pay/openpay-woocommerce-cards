<?php
namespace OpenpayCards\Handlers;
use OpenpayCards\Services\PaymentSettings\Openpay3dSecure;
class OpenpayChargeHandlerMx {

    public function __construct()
    {
        $this->logger = wc_get_logger();
    }
    public function applyPaymentSettings($charge_request,$payment_settings){

        // CARD POINTS
        if (isset($payment_settings['openpay_card_points_confirm'])){
            $charge_request["use_card_points"] = $payment_settings['openpay_card_points_confirm'];
        }

        // MSI
        if (isset($payment_settings['openpay_payment_plan']) && $payment_settings['openpay_payment_plan'] != 1){
            $charge_request["payment_plan"] = array("payments" => $payment_settings['openpay_payment_plan']);
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

        $this->logger->info("[OpenpayChargeHandlerMx.applyPaymentSettings] => " . json_encode($charge_request) );
        return $charge_request;
    }

}