<?php
namespace OpenpayCards\Includes;

if (!class_exists('Openpay\Data\Openpay')) {
    require_once(dirname(__FILE__) . '/../lib/openpay/Openpay.php');
}

use Openpay\Data\Openpay as Openpay;

class OpenpayClient {

    public static function getOpenpayInstance($sandbox, $merchant_id, $private_key, $country){
        //$logger = wc_get_logger();
        //$logger->info('getOpenpayInstance'); 
        Openpay::setClassificationMerchant('general');
        Openpay::setProductionMode($sandbox ? false : true);
        $openpay = Openpay::getInstance($merchant_id, $private_key, $country, self::getClientIp());
        $userAgent = "Openpay-WOOC".$country."/v2";
        Openpay::setUserAgent($userAgent);
        return $openpay;
    }

    private static function getClientIp() {
        //$logger = wc_get_logger();
        //$logger->info('getClientIp'); 
        // Recogemos la IP de la cabecera de la conexión
        if (!empty($_SERVER['HTTP_CLIENT_IP']))   
        {
          $ipAdress = $_SERVER['HTTP_CLIENT_IP'];
        }
        // Caso en que la IP llega a través de un Proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))  
        {
          $ipAdress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        // Caso en que la IP lleva a través de la cabecera de conexión remota
        else
        {
          $ipAdress = $_SERVER['REMOTE_ADDR'];
        }
        //$logger->debug('IP IN HEADER: ' . $ipAdress);  
        $ipAdress = trim(explode(",", $ipAdress)[0]);
        return $ipAdress;
      }
}