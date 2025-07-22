<?php
namespace OpenpayCards\Services\PaymentSettings;

use WC_Openpay_Gateway;

Class OpenpayInstallments extends WC_Openpay_Gateway{

    public function __construct(){
        parent::__construct();
    }

    public function processInstallments(){
        global $woocommerce;

        if ($this->country == 'MX') {
            if (is_array($this->msi) && count($this->msi) > 0 && ($woocommerce->cart->total >= $this->minimum_amount_interest_free)) {
                return array(
                    'payments' => $this->msi,
                );
            }
            $this->logger->info('La compra no cumple los requisitos para MSI - minimum_amount_interest_free: ' . json_encode($this->minimum_amount_interest_free). '- msi:' . json_encode($this->msi) );
        }

        if ($this->country == 'PE'){
            return array(
                'paymentPlan' => $this->installments_is_active
            );
        }

        if ($this->country == 'CO') {
            $installments = [];
            for($i=2; $i <= 36; $i++) {
                $installments[] = $i.' cuotas';
            }
            return array(
                'payments' => $installments,
            );
        }
        return false;
    }

    public function getInstallments(){
       return $this->processInstallments();
    }

    public function dataValidationAssignement(&$charge_request,$openpay_payment_plan, $openpay_has_interest_pe){
            if (isset($openpay_payment_plan)){
                if(isset($openpay_has_interest_pe) && $openpay_has_interest_pe == true) {
                    $charge_request["payment_plan"] = array("payments" => $openpay_payment_plan, "payments_type" => "WITH_INTEREST");
                } else {
                    $charge_request["payment_plan"] = array("payments" => $openpay_payment_plan, "payments_type" => "WITHOUT_INTEREST");
                }
        }
    }

}

