<?php

if(!class_exists('WC_Openpay_Cards_Service')) {
  require_once(dirname(__DIR__) . "/services/class-wc-openpay-cards-service.php");
}

class WC_Openpay_Refund_Service {
    private $logger;
    private $sandbox;
    private $country;
    private $openpayInstance;
    
    public function __construct($sandbox, $country, $openpayInstance){
        $this->logger = wc_get_logger(); 
        $this->sandbox = $sandbox;
        $this->country = $country;
        $this->openpayInstance = $openpayInstance;
    }

    public function refundOrder($order_id, $refund_id) {
        $this->logger->info("Entrando al hook de refund");
        $this->logger->info('ORDER: '.$order_id);             
        $this->logger->info('REFUND: '.$refund_id); 

        $order  = wc_get_order($order_id);
        $refund = wc_get_order($refund_id);

        if ($order->get_payment_method() != 'wc_openpay_gateway') {
            $this->logger->info('get_payment_method: '.$order->get_payment_method());             
            return;
        }

        $this->logger->info('is sandbox'.$this->sandbox);
        if(!strcmp($this->sandbox, 'yes')){
            $customer_id = $order->get_meta('_openpay_customer_sandbox_id');
            $this->logger->info('customer id: '.$customer_id);
        } else {
            $customer_id = $order->get_meta('_openpay_customer_id');
        }

        $transaction_id = $order->get_meta('_transaction_id');


        $reason = $refund->get_reason() ? $refund->get_reason() : 'Refund ID: '.$refund_id;
        $amount = floatval($refund->get_amount());
        $this->logger->info("Cantidada a reembolsar: " . $amount);

        $this->logger->info('_openpay_customer_id: '.$customer_id);             
        $this->logger->info('_transaction_id: '.$transaction_id);

        try {
            if ($this->country == 'CO') {             
                throw new Error("Openpay plugin does not support refunds");
            }

            if (!strlen($customer_id)) {
                $this->logger->info('No existe el customerId');
                $charge = $this->openpayInstance->charges->get($transaction_id);
            } else {
                $customer = $this->openpayInstance->customers->get($customer_id);
                $charge = $customer->charges->get($transaction_id);
                 $this->logger->info('El id del customer es: ' . $customer->id);
            }
            
            
            $this->logger->info('Obtenemos el charge');
            $charge->refund(array(
                'description' => $reason,
                'amount' => $amount                
            ));
            $this->logger->info('Se hace el fefund');
            $this->logger->info('Stataus ' . $order->get_status());

            $status = $order->get_status();
            $capture = $order->get_meta('_openpay_capture');

            if ($capture == 'false' && $status == 'refunded') {
            $this->logger->info('Es una orden preautorizada');
            $order->add_order_note("La orden fue cancelada por Openpay");
            $order->update_status('cancelled');
        }

            $order->add_order_note('Payment was also refunded in Openpay');
        } catch(Exception $e) {
            $this->logger->error($e->getMessage());
            $refunds = $order->get_refunds();
            if(!empty($refunds)) {
                $last_refund = end($refunds);
                $refund_id = $last_refund->get_id();

                wp_delete_post($refund_id, true);
            }
            if($order->get_status() === 'refunded') {
                $order->update_status('processing');
            }
            $order->add_order_note('There was an error refunding charge in Openpay: '.$e->getMessage());
        }

        return;
    }
}