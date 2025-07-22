<?php
namespace OpenpayCards\Services;
use OpenpayCards\Includes\OpenpayUtils;

Class OpenpayCustomerService{
    private $logger;
    private $openpay;
    private $country;
    private $sandbox;
    
    public function __construct($openpay,$country,$sandbox){
        $this->logger = wc_get_logger(); 
        $this->openpay = $openpay;
        $this->country = $country;
        $this->sandbox = $sandbox;
        
    }

    public function retrieveCustomer($order){
        $customer_id = $this->getCustomerId();
        try { 
            if (OpenpayUtils::isNullOrEmptyString($customer_id)) {
                $this->logger->info('(retrieveCustomer) customer_id not exists - ' . $customer_id);
                if (is_user_logged_in()) {
                    return $this->create($order);
                }
            }else{
                $this->logger->info('(retrieveCustomer) customer_id exists - ' . $customer_id);
                return $this->openpay->customers->get($customer_id);
            } 
        } catch (Exception $e) {
            //$this->error($e);
            return false;
        }
    }

    public function getCustomerId() {
        $customer_id = null;
        $this->logger->info('(getCustomerId) sandbox? - ' . $this->sandbox);
        if (is_user_logged_in()) {
            if ($this->sandbox) {
                $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_test_id', true);
            } else {
                $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_live_id', true);
            }            
        }
        $this->logger->info('(getCustomerId) customer_id_test - ' . get_user_meta(get_current_user_id(), '_openpay_customer_test_id', true) );
        $this->logger->info('(getCustomerId) customer_id_live - ' . get_user_meta(get_current_user_id(), '_openpay_customer_live_id', true) );
        return $customer_id; 
    }

    public function updateCustomerId($customer_id){
        if (is_user_logged_in()) {
            if ($this->sandbox) {
                update_user_meta(get_current_user_id(), '_openpay_customer_test_id', $customer_id);
            } else {
                update_user_meta(get_current_user_id(), '_openpay_customer_live_id', $customer_id);
            }                
        }
    }


    public function create($order) {
        $customer_data = $this->collectCustomerData($order);
        $this->logger->info('(create) customer_data - ' . json_encode($customer_data));
        try {
            $customer = $this->openpay->customers->add($customer_data);
            $this->logger->info('customer_id - ' . $customer->id); 
            $this->updateCustomerId($customer->id);
           
            $this->logger->info('customer_data - ' . $customer_data); 
            $this->logger->info('customer_id - ' . $customer->id); 

            return $customer;

        } catch (Exception $e) {
            //$this->error($e);
            return false;
        }
    }

    

    public function collectCustomerData($order) {

        $customer_data = array(            
            'name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'requires_account' => false,
            'phone_number' => $order->get_billing_phone()            
        );

        if ($this->country === 'MX' || $this->country === 'PE') {
            $customer_data['address'] = array(
                'line1' => substr($order->get_billing_address_1(), 0, 200),
                'line2' => substr($order->get_billing_address_2(), 0, 50),
                'state' => $order->get_billing_state(),
                'city' => $order->get_billing_city(),
                'postal_code' => $order->get_billing_postcode(),
                'country_code' => $order->get_billing_country()
            );
        } else if ($this->country === 'CO' ) {
            $customer_data['customer_address'] = array(
                'department' => $order->get_billing_state(),
                'city' => $order->get_billing_city(),
                'additional' => substr($order->get_billing_address_1(), 0, 200).' '.substr($order->get_billing_address_2(), 0, 50)
            );
        }
        
        return $customer_data;
    }


}

