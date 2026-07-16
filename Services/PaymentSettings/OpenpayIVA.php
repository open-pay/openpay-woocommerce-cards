<?php
namespace OpenpayCards\Services\PaymentSettings;

use WC_Openpay_Gateway;

class OpenpayIVA extends WC_Openpay_Gateway
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getTotalIVA()
    {
        $settings = get_option('woocommerce_wc_openpay_gateway_settings', []);

        if (
            !is_array($settings)
            || ($settings['country'] ?? '') !== 'CO'
            || ($settings['iva'] ?? 'no') !== 'yes'
        ) {
            return 0.0;
        }

        $total_campo = 0;

        if (isset(WC()->cart)) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_id = $cart_item['product_id'];
                $cantidad = $cart_item['quantity'];

                $valor = get_post_meta($product_id, 'openpay_taxes_iva', true);

                if (is_numeric($valor)) {
                    $total_campo += ((float) $valor * $cantidad);
                }
            }
        }
        return $total_campo;

    }
}