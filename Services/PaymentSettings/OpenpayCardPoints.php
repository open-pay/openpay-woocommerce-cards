<?php
namespace OpenpayCards\Services\PaymentSettings;

use WC_Openpay_Gateway;
class OpenpayCardPoints extends WC_Openpay_Gateway{

    public function __construct(){
        parent::__construct();
    }
    public function dataValidationAssignement(&$charge_request,$openpay_card_points_confirm){
        if($this->country == "MX"){
            if (isset($openpay_card_points_confirm)){
                $charge_request["use_card_points"] = $openpay_card_points_confirm;
            }
        }
    }
}