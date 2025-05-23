<?php

//custom test 3
Class WC_Openpay_MSI{
    public static function getMSI($msi_settings,$minimum_amount_interest_free){
        global $woocommerce;
        if (count($msi_settings) > 0 && ($woocommerce->cart->total >= $minimum_amount_interest_free)) {
            return $msi_settings;
        }else{
            return Array();
        }
    }
}

