<?php

Class WC_Openpay_Charge_Service{

    private $logger;
    private $order;
    private $customer_service;
    
    public function __construct($openpay,$order,$customer_service, $capture){ // agregue al constructor el parametro capture
        $this->logger = wc_get_logger(); 
        $this->order = $order;
        $this->customer_service = $customer_service;
        $this->openpay = $openpay;
        $this->capture = $capture;  // se agrega la referencia
    }

    public function processOpenpayCharge($payment_settings) {
        
        $this->logger->info('processOpenpayCharge');
                
        $charge_request = $this->collectChargeData($payment_settings);

        $charge = $this->create($payment_settings['openpay_customer'], $charge_request);
        $this->logger->info('processOpenpayCharge {Charge.id} - ' . $charge->id);
        $this->logger->info('processOpenpayCharge {Charge.description} - ' . $charge->description);
    }

    public function create($openpay_customer, $charge_request) {

        try {
            $this->logger->info('wc-openpay-charge-service.create');
            if (is_user_logged_in()) {
                $charge = $openpay_customer->charges->create($charge_request);
            }else{
                $charge = $this->openpay->charges->create($charge_request);
            }
        }catch (Exception $e){
            $this->logger->error('[ERROR - wc-openpay-charge-service.create] Order => '.$this->order->get_id()); 
            // $this->logger->error('[ERROR - wc-openpay-charge-service.create] Error => '. json_encode($e)); is not working.
            $this->logger->error('[ERROR - wc-openpay-charge-service.create] Error => '. $e->getMessage());
            //throw new Exception('Oopsie');
        }



       /* $this->logger->info('wc-openpay-charge-service.create'); 
        try {
            $charge = $openpay_customer->charges->create($charge_request);
            return $charge;
        }/*catch (OpenpayApiRequestError $e){
            $this->logger->error('[ERROR - wc-openpay-charge-service.create] Error => '. $e);
        }*/
        /*catch (Exception $e){
            $this->logger->error('[ERROR - wc-openpay-charge-service.create] Order => '.$this->order->get_id()); 
            // $this->logger->error('[ERROR - wc-openpay-charge-service.create] Error => '. json_encode($e)); is not working.
            $this->logger->error('[ERROR - wc-openpay-charge-service.create] Error => '. $e->getMessage());
            //throw new Exception('Oopsie');
        }
        */
        
    }

    private function collectChargeData($payment_settings) {
        
        $charge_request = array(
            "method" => "card",
            "amount" => number_format((float)$this->order->get_total(), 2, '.', ''),
            "currency" => strtolower(get_woocommerce_currency()),
            "source_id" => $payment_settings['openpay_token'],
            "device_session_id" => $payment_settings['device_session_id'],
            "description" => sprintf("Items: %s", $this->getProductsDetail()),            
            "order_id" => $this->order->get_id(),
            "capture" => $payment_settings['capture'],  // Se agrega al reques el valor capture tomado desde los ajustes
            'use_card_points' => $payment_settings['openpay_card_points_confirm'],
            "origin_channel" => "PLUGIN_WOOCOMMERCE",
            "payment_plan" => $payment_settings['openpay_payment_plan']
        );

        if (!is_user_logged_in()) {
            $charge_request["customer"] = $this->customer_service->collectCustomerData($this->order);
        }
        $this->logger->info(json_encode($charge_request));

        return $charge_request;
    }

      private function getProductsDetail() {
        $this->logger->info('wc-openpay-charge-service.getProductsDetail'); 
        $order = $this->order;
        $products = [];
        foreach( $order->get_items() as $item_product ){                        
            $product = $item_product->get_product();                        
            $products[] = $product->get_name();
        }
        return substr(implode(', ', $products), 0, 249);
    }

}




