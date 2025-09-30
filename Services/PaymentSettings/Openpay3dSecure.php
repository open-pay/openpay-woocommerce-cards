<?php
namespace OpenpayCards\Services\PaymentSettings;

use WC_Openpay_Gateway;
use OpenpayCards\Includes\OpenpayClient;

class Openpay3dSecure extends WC_Openpay_Gateway
{
    public function __construct()
    {
        parent::__construct();
    }

    public static function redirect_url_3d()
    {
        $protocol = (get_option('woocommerce_force_ssl_checkout') == 'no') ? 'http' : 'https';
        return site_url('/', $protocol) . '?wc-api=openpay_confirm';
    }

}



