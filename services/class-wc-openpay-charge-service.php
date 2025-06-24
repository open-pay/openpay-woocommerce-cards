<?php
// Ensure WordPress functions are available
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once ABSPATH . 'wp-load.php';
require_once ABSPATH . 'wp-includes/pluggable.php';
require_once ABSPATH . 'wp-includes/user.php'; // Ensure user-related functions are loaded

if(!class_exists('WC_Openpay_Card_Points')) {
    require_once(dirname(__FILE__) . "/payment-settings/class-wc-openpay-card-points.php");
}
class WC_Openpay_Charge_Service
{

    private $logger;
    private $order;
    private $customer_service;
    private $card_points;
    private $installments;

    private $capture;

    public function __construct($openpay, $order, $customer_service, $capture)
    {
        $this->logger = wc_get_logger();
        $this->order = $order;
        $this->customer_service = $customer_service;
        $this->openpay = $openpay;
        $this->card_points = new WC_Openpay_Card_Points();
        $this->installments = new WC_Openpay_Installments();
        $this->capture = $capture;
    }

    public function processOpenpayCharge($payment_settings)
    {

        $this->logger->info('processOpenpayCharge');

        $charge_request = $this->collectChargeData($payment_settings);
        $this->logger->info('processOpenpayCharge {Charge.TYPE} - ' .$payment_settings['openpay_charge_type']);
        $charge = $this->create($payment_settings['openpay_customer'], $charge_request,$payment_settings['openpay_charge_type']);
        $this->logger->info('processOpenpayCharge {Charge.id} - ' . $charge->id);
        $this->logger->info('processOpenpayCharge {Charge.description} - ' . $charge->description);
        if($charge != false ) {
            $this->order->update_meta_data('_transaction_id', $charge->id);
            $this->logger->info('processOpenpayCharge {Charge.id} - ' . $charge->id);
            $this->logger->info('processOpenpayCharge {Charge.description} - ' . $charge->description);

            if($payment_settings['sandbox'] && is_user_logged_in()){
                $this->order->update_meta_data('_openpay_customer_sandbox_id',$charge->customer_id);
                $this->logger->info('Update metadata customer Sandbox ' . $charge->customer_id);
            } else if (!$payment_settings['sandbox'] && is_user_logged_in()){
                $this->order->update_meta_data('_openpay_customer_id',$charge->customer_id);
                $this->logger->info('Update metadata customer Live ' . $charge->customer_id);
            }

            if($charge_request['capture'] === false && $charge->status == 'in_progress'){
                $captureString = ($this->capture) ? 'true' : 'false';
                $this->logger->info('Order:' . $this->order->get_id() . ' Set as preauthorized');
                $this->order->update_meta_data('_openpay_capture', $captureString);
            }
            $this->order->save();
        }

        return $charge;
    }

    public function create($openpay_customer, $charge_request, $charge_type) {
        $charge = null;
        try {
            $this->logger->info('wc-openpay-charge-service.create');

            if (is_user_logged_in()) {
                $charge = $openpay_customer->charges->create($charge_request);
                $this->logger->info('[wc-openpay-charge-service.create] => charge result=> ' . $charge->id);
            } else {
                $charge = $this->openpay->charges->create($charge_request);
                $this->logger->info('[wc-openpay-charge-service.create] => charge result=> ' . $charge->id);
            }
            if($charge !==false){
                if ($charge->payment_method && $charge->payment_method->type == 'redirect') {
                    $this->logger->info('[wc-openpay-charge-service.create] => UPDATE METADATA payment_method->url)');
                    $this->order->update_meta_data('_openpay_3d_secure_url', $charge->payment_method->url);
                    $this->order->set_status('on-hold');
                }else{
                    $this->order->delete_meta_data('_openpay_3d_secure_url');
                }
                $this->order->save();
            }

            return $charge;
        } catch (Exception $e) {
            $this->logger->error('[ERROR - wc-openpay-charge-service.create] Order => ' . $this->order->get_id());
            $this->logger->error('[ERROR - wc-openpay-charge-service.create] Error => '. $e->getMessage());

            $this->logger->error('[Charge type exception => '. $charge_type);
            $this->logger->error('[Code exception => '. $e->getCode());
            $this->logger->error('[Redirect url => '. $this->redirect_url_3d());

            // Si cuenta con autenticación selectiva y hay detección de fraude se envía por 3D Secure
            if ($charge_type == 'auth' && $e->getCode() == '3005') {
                $charge_request['use_3d_secure'] = true;
                $charge_request['redirect_url'] =$this->redirect_url_3d();
                $charge = $openpay_customer->charges->create($charge_request);

                $this->logger->info('createOpenpayCharge Auth Order => '.$this->order->get_id());

                if ($charge->payment_method && $charge->payment_method->type == 'redirect') {
                    $this->logger->info('createOpenpayCharge update_order_meta_data => '.$charge->payment_method->url);
                    $this->order->update_meta_data('_openpay_3d_secure_url', $charge->payment_method->url);
                    $this->order->save(); // ¡Importante! Guardar los cambios
                    $redirect_url_meta = $this->order->get_meta('_openpay_3d_secure_url');
                    $this->logger->info('get update_order_meta_data => ' . $redirect_url_meta);
                }else{
                    $this->order->delete_meta_data('_openpay_3d_secure_url');
                }
                return $charge;
            }

//            $this->error($e);
            return false;
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
        return $charge;
    }

    private function collectChargeData($payment_settings)
    {

        $charge_request = array(
            "method" => "card",
            "amount" => number_format((float)$this->order->get_total(), 2, '.', ''),
            "currency" => strtolower(get_woocommerce_currency()),
            "source_id" => $payment_settings['openpay_token'],
            "device_session_id" => $payment_settings['device_session_id'],
            "description" => sprintf("Items: %s", $this->getProductsDetail()),
            "order_id" => $this->order->get_id(),
            "capture" => $payment_settings['capture'],
            "origin_channel" => "PLUGIN_WOOCOMMERCE",
        );

        $this->logger->info("Charge type: ".$payment_settings['openpay_charge_type']);
        if ($payment_settings['openpay_charge_type'] == '3d') {
            $charge_request['use_3d_secure'] = true;
            $charge_request['redirect_url'] = $this->redirect_url_3d();
            $this->logger->info("RedirectUrl ".$this->redirect_url_3d());
        }

        $this->card_points->dataValidationAssignement($charge_request, $payment_settings['openpay_card_points_confirm']);
        $this->installments->dataValidationAssignement($charge_request, $payment_settings['openpay_payment_plan']);

        if (!is_user_logged_in()) {
            $charge_request["customer"] = $this->customer_service->collectCustomerData($this->order);
        }
        $this->logger->info(json_encode($charge_request));

        return $charge_request;
    }

    private function getProductsDetail()
    {
        $this->logger->info('wc-openpay-charge-service.getProductsDetail');
        $order = $this->order;
        $products = [];
        foreach ($order->get_items() as $item_product) {
            $product = $item_product->get_product();
            $products[] = $product->get_name();
        }
        return substr(implode(', ', $products), 0, 249);
    }
    /**
     * @return string
     */
    public function redirect_url_3d()
    {
        $protocol = (get_option('woocommerce_force_ssl_checkout') == 'no') ? 'http' : 'https';
        return site_url('/', $protocol) . '?wc-api=openpay_confirm';
    }

}









