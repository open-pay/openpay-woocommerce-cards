<?php

Class WC_Openpay_Payment_Settings_Validation extends WC_Openpay_Gateway{

    private $post_data;
    public $settings;
    public function __construct(){
        parent::__construct();
        $this->post_data = $this->get_post_data();
        $sandboxIsSet = isset($this->post_data['woocommerce_'.$this->id.'_sandbox']);
        $this->sandbox = $sandboxIsSet ? $this->post_data['woocommerce_'.$this->id.'_sandbox'] : false;
        $mode = $this->sandbox == '1' ? 'test':'live';
        $this->merchant_id = $this->post_data['woocommerce_'.$this->id.'_'.$mode.'_merchant_id'];
        $this->private_key = $this->post_data['woocommerce_'.$this->id.'_'.$mode.'_private_key'];
        $this->country = $this->post_data['woocommerce_'.$this->id.'_country'];
        $this->settings = new WC_Admin_Settings();
    }
    public function validateOpenpayCredentials(){
        $env = $this->sandbox == '1' ? 'Sandbox':'Production';

        if($this->merchant_id == '' || $this->private_key == ''){
            $this->settings->add_error('You need to enter "'.$env.'" credentials if you want to use this plugin in this mode.');
            $this->logger->info('NO se encontro merchant_id o private_key durante la validación. ');
        }else{
            try{
                $this->openpay = WC_Openpay_Client::getOpenpayInstance($this->sandbox, $this->merchant_id, $this->private_key, $this->country);
                $this->openpay->webhooks->getList(['limit'=>1]);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                $this->settings->add_error($e->getMessage());
                return;
            }
        }
    }

    public function validateOpenpayCurrencies(){
        $allowedCurrencies = $this->getCurrencies($this->country);
        if(!in_array(get_woocommerce_currency(), $allowedCurrencies)){
            $this->settings->add_error('Openpay Cards Plugin ' . Utils::getCountryName($this->country) .
                ' is only available for ' . implode(", ", $allowedCurrencies) . ' currencies.' );
        }
    }

    public static function getCurrencies($countryCode) {
        switch (strtoupper($countryCode)) {
            case 'MX':
                $currencies = ['MXN','USD'];
                break;
            case 'CO':
                $currencies = ['COP','USD'];
                break;
            case 'PE':
                $currencies = ['PEN','USD'];
                break;
            default:
                break;
        }
        return $currencies;
    }

}