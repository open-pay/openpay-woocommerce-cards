<?php

class WC_Openpay_Cards_Service extends WC_Openpay_Gateway
{

    /*
    private $logger;
    private $openpay;
    private $order;
    private $country;
    private $sandbox;
    */

    public function __construct(/*$openpay,$order,$country,$sandbox*/)
    {
        parent::__construct();
        /*
        $this->logger = wc_get_logger();   
        $this->openpay = $openpay;
        $this->order = $order;
        $this->country = $country;
        $this->sandbox = $sandbox;
        */
    }

    public function getCreditCardList()
    {
        if (!is_user_logged_in()) {
            return array(array('value' => 'new', 'name' => 'Nueva tarjeta'));
        }

        if ($this->sandbox) {
            //delete_user_meta(get_current_user_id(), '_openpay_customer_test_id');
            $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_test_id', true);
        } else {
            $customer_id = get_user_meta(get_current_user_id(), '_openpay_customer_live_id', true);
        }
        $this->logger->info('WC_Openpay_Cards_Service.getCreditCardList - customer_id ' . $customer_id);


        if (Openpay_Utils::isNullOrEmptyString($customer_id)) {
            return array(array('value' => 'new', 'name' => 'Nueva tarjeta'));
        }

        $list = array(array('value' => 'new', 'name' => 'Nueva tarjeta'));
        $this->logger->info('WC_Openpay_Cards_Service.getCreditCardList - cards_list ' . Json_encode($list));
        try {
            $customer = $this->openpay->customers->get($customer_id);
            $cards = $this->getCreditCards($customer);
            $this->logger->info('WC_Openpay_Cards_Service.getCreditCardList - cards_list_from_api ' . Json_encode($cards));
            foreach ($cards as $card) {
                array_push($list, array('value' => $card->id, 'name' => strtoupper($card->brand) . ' ' . $card->card_number));
            }
            return $list;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            return $list;
        }
    }

    private function getCreditCards($customer)
    {
        try {
            return $customer->cards->getList(array(
                'offset' => 0,
                'limit' => 10
            ));
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw $e;
        }
    }

    public function validateNewCard($openpay_customer, $token, $device_session_id, $card_number, $save_card_mode)
    {
        global $woocommerce;
        $this->logger->info('validateNewCard', array('#INFO validateNewCard() => ' => $card_number));
        $cards = $this->getCreditCards($openpay_customer);
        $card_number_bin = substr($card_number, 0, 8);
        $card_number_complement = substr($card_number, -4);
        foreach ($cards as $card) {
            if ($card_number_bin == substr($card->card_number, 0, 8) && $card_number_complement == substr($card->card_number, -4)) {
                $errorMsg = "La tarjeta ya se encuentra registrada, seleccionala de la lista de tarjetas.";
                $this->logger->error('validateNewCard', array('#ERROR validateNewCard() => ' => $errorMsg));
                if (function_exists('wc_add_notice')) {
                    wc_add_notice($errorMsg, $notice_type = 'error');
                } else {
                    $woocommerce->add_error(__('Payment error:', 'woothemes') . $errorMsg);
                    throw new Exception("La tarjeta ya se encuentra registrada, seleccionala de la lista de tarjetas.");
                }
                return false;
            }
        }

        $card_data = array(
            'token_id' => $token,
            'device_session_id' => $device_session_id
        );

        if ($save_card_mode === '2' && $this->country === 'PE') {
            $card_data['register_frequent'] = true;
        }

        $card = $this->createCreditCard($openpay_customer, $card_data);

        return $card->id;
    }

    private function createCreditCard($customer, $data)
    {
        try {
            return $customer->cards->add($data);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw $e;
        }
    }

    /*
    public function getCardsList() {
        if (!is_user_logged_in()) {            
            return array(array('value' => 'new', 'name' => 'Nueva tarjeta'));
        } 

        $customer_service = new WC_Openpay_Customer_Service($this->openpay,$this->country,$this->sandbox);
        $customer_id = $customer_service->getCustomerId();
        if (Utils::isNullOrEmptyString($customer_id)) {
            return array(array('value' => 'new', 'name' => 'Nueva tarjeta'));
        }

        $openpay_customer = $customer_service->retrieveCustomer($this->order);

        try {
            $cards = $openpay_customer->cards->getList(array('offset' => 0,'limit' => 10));         
            $list = array(array('value' => 'new', 'name' => 'Nueva tarjeta'));
            foreach ($cards as $card) {                
                array_push($list, array('value' => $card->id, 'name' => strtoupper($card->brand).' '.$card->card_number));
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());     
            throw $e;
        } 
        return $list;  
    }
    */

}