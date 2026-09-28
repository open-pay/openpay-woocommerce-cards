<?php
use OpenpayCards\Includes\OpenpayUtils;

class WC_Openpay_Bines_Consult {

    public function __construct() {}

    public function getTypeCardOpenpay() {
        global $woocommerce;

        $logger     = wc_get_logger();
        $logger->info("[WC_Openpay_Bines_Consult.getTypeCardOpenpay] start");
        $card_bin   = $this->getSanitizedCardBin();
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

                $safe_card_bin = rawurlencode($card_bin);

                switch ($country) {

                    case 'MX':
                        $path       = sprintf('/%s/bines/man/%s', $merchant_id, $safe_card_bin);
                        $cardInfo = OpenpayUtils::requestOpenpay($path, $country, $is_sandbox,null,null,$auth);
                        
                        wp_send_json(array(
                            'status'    => 'success',
                            'card_type' => $cardInfo->type
                        ));

                    break;

                    case 'PE':
                        $logger->info("Entra a peru");
                        $path       = sprintf('/%s/bines/%s/promotions', $merchant_id, $safe_card_bin);
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
                        $path       = sprintf('/cards/validate-bin?bin=%s', $safe_card_bin);
                        $cardInfo = OpenpayUtils::requestOpenpay($path, $country, $is_sandbox);
                        wp_send_json(array(
                            'status' => 'success',
                            'card_type' => $cardInfo->card_type
                        ));

                    break;

                }

            } catch (Exception $e) {
                $logger->error('[WC_Openpay_Bines_Consult.getTypeCardOpenpay => ERROR ]'.$e->getMessage());
            }
        }
        wp_send_json(array(
            'status' => 'error',
            'card_type' => "credit card not found"
        ));
        $logger->info("[WC_Openpay_Bines_Consult.getTypeCardOpenpay] end");
    }

    private function getSanitizedCardBin()
    {
        if (!isset($_POST['card_bin'])) {
            return false;
        }

        $card_bin = sanitize_text_field(wp_unslash($_POST['card_bin']));
        $card_bin = preg_replace('/\D+/', '', $card_bin);

        if (!preg_match('/^\d{6,8}$/', $card_bin)) {
            return false;
        }

        return $card_bin;
    }
}