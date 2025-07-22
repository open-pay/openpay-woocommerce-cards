<?php
use OpenpayCards\Includes\OpenpayUtils;

class WC_Openpay_Bines_Consult {

    public function __construct() {}

    public function getTypeCardOpenpay() {
        global $woocommerce;

        $logger     = wc_get_logger();
        $logger->info("Entra a los bines");
        $card_bin   = isset( $_POST['card_bin'] ) ? $_POST['card_bin'] : false;
        $logger->info("Bin: " . $card_bin);
        if($card_bin) {
            try {
                $openpay_gateway = new WC_Openpay_Gateway();
                $country        = $openpay_gateway->settings['country'];
                $is_sandbox     = strcmp($openpay_gateway->settings['sandbox'], 'yes') == 0;
                $merchant_id    = $is_sandbox === true ? $openpay_gateway->settings['test_merchant_id'] : $openpay_gateway->settings['live_merchant_id'];
                $auth           = $is_sandbox === true ? $openpay_gateway->settings['test_private_key'] : $openpay_gateway->settings['live_private_key'];
                $amount         = $woocommerce->cart->total;
                $currency       = get_woocommerce_currency();
                $logger->info("Pais: " . $country);

                switch ($country) {

                    case 'MX':
                        $path       = sprintf('/%s/bines/man/%s', $merchant_id, $card_bin);
                        $cardInfo = OpenpayUtils::requestOpenpay($path, $country, $is_sandbox,null,null,$auth);
                        
                        wp_send_json(array(
                            'status'    => 'success',
                            'card_type' => $cardInfo->type
                        ));

                    break;

                    case 'PE':
                        $logger->info("Entra a peru");
                        $path       = sprintf('/%s/bines/%s/promotions', $merchant_id, $card_bin);
                        $params     = array('amount' => $amount, 'currency' => $currency);
                        $cardInfo    = OpenpayUtils::requestOpenpay($path, $country, $is_sandbox);

                        wp_send_json(array(
                            'status'        => 'success',
                            'card_type' => $cardInfo->cardType,
                            'installments'  => $cardInfo->installments,
                            'withInterest' => $cardInfo->withInterest
                        ));

                    break;

                    default:
                        $path       = sprintf('/cards/validate-bin?bin=%s', $card_bin);
                        $cardInfo = OpenpayUtils::requestOpenpay($path, $country, $is_sandbox);
                        wp_send_json(array(
                            'status' => 'success',
                            'card_type' => $cardInfo->card_type
                        ));

                    break;

                }

            } catch (Exception $e) {
                $logger->error($e->getMessage());
            }
        }
        wp_send_json(array(
            'status' => 'error',
            'card_type' => "credit card not found"
        ));
    }
}