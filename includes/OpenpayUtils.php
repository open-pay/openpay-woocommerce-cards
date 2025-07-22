<?php
namespace OpenpayCards\Includes;
class OpenpayUtils {

    public static function getUrlScripts($country){
        $scripts = [
            'openpay_js' => [
                'tag'=>'',
                'script'=>''],
            'openpay_fraud_js' => ''
        ];
        $routeBaseOpenpayJs = '%s/openpay.v1.min.js';
        $routeBaseOpenpayFraud = '%s/openpay-data.v1.min.js';
 
        switch ($country) {
            case 'MX':
                $baseUrl = 'https://openpay.s3.amazonaws.com';
                $scripts['openpay_js']['tag'] = 'mx_openpay_js';
                $scripts['openpay_js']['script'] = 'assets/js/mx-openpay.v1.min.js';

                $scripts['openpay_fraud_js'] = sprintf($routeBaseOpenpayFraud, $baseUrl);
                return $scripts;
            case 'CO':
                $baseUrl = 'https://resources.openpay.co';
                $scripts['openpay_js']['tag'] = 'co_openpay_js';
                $scripts['openpay_js']['script'] = 'assets/js/co-openpay.v1.min.js';
                $scripts['openpay_fraud_js'] = sprintf($routeBaseOpenpayFraud, $baseUrl);
                return $scripts;
            case 'PE':
                $baseUrl = 'https://js.openpay.pe';
                $scripts['openpay_js']['tag'] = 'pe_openpay_js';
                $scripts['openpay_js']['script'] = 'assets/js/pe-openpay.v1.min.js';
                $scripts['openpay_fraud_js'] = sprintf($routeBaseOpenpayFraud, $baseUrl);
                return $scripts;
            default:
                break;
        }
        return false;
    }

    public static function getCountryName($countryCode) {
        switch ($countryCode){
            case 'MX':
                return 'Mexico';
            case 'CO':
                return 'Colombia';
            case 'PE':
                return 'Peru';
            default:
                break;
        }
        return false;
    }

    public static function requestOpenpay($api, $country, $is_sandbox, $method = 'GET', $params = [], $auth = null) {

        $logger = wc_get_logger();
        $logger->info("MODO SANDBOX ACTIVO: " . $is_sandbox);

        $country_tld    = strtolower($country);
        $sandbox_url    = 'https://sandbox-api.openpay.'.$country_tld.'/v1';
        $url            = 'https://api.openpay.'.$country_tld.'/v1';
        $absUrl         = $is_sandbox === true ? $sandbox_url : $url;
        $absUrl        .= $api;
        $headers        = Array();

        $logger->info('Current Route => '.$absUrl);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $absUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if(!empty($params)){
            $data = json_encode($params);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $headers[] = 'Content-Type:application/json';
        }

        if(!empty($auth)){
            $auth = base64_encode($auth.":");
            $headers[] = 'Authorization: Basic '.$auth;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $logger->info($result);

        if ($result === false) {
            $logger->error('Curl error '.curl_errno($ch).': '.curl_error($ch));
        } else {
            $info = curl_getinfo($ch);
            $logger->info('HTTP code '.$info['http_code'].' on request to '.$info['url']);
        }
        curl_close($ch);

        return json_decode($result);
    }

    public static function isNullOrEmptyString($string) {
        return (!isset($string) || trim($string) === '');
    }
}