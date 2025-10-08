<?php
namespace OpenpayCards\Services;

use Exception;
use OpenpayCards\Handlers\OpenpayChargeHandlerCo;
use OpenpayCards\Handlers\OpenpayChargeHandlerMx;
use OpenpayCards\Handlers\OpenpayChargeHandlerPe;
use OpenpayCards\Includes\OpenpayErrorHandler;
use OpenpayCards\Services\PaymentSettings\Openpay3dSecure;


// Ensure WordPress functions are available
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class OpenpayChargeService
{

    private $logger;
    private $order;
    private $customer_service;
    private $capture;
    private $openpay;

    public function __construct($openpay, $order, $customer_service, $capture)
    {
        $this->logger = wc_get_logger();
        $this->order = $order;
        $this->customer_service = $customer_service;
        $this->openpay = $openpay;
        $this->capture = $capture;
    }

    public function processOpenpayCharge($payment_settings)
    {

        $this->logger->info('[OpenpayChargeService.processOpenpayCharge start]');

        $charge_request = $this->collectChargeData($payment_settings);
        $this->logger->info('[OpenpayChargeService.processOpenpayCharge] {Charge.TYPE} - ' .$payment_settings['openpay_charge_type']);
        $charge = $this->create($payment_settings['openpay_customer'], $charge_request,$payment_settings['openpay_charge_type']);
        $this->logger->info('[OpenpayChargeService.processOpenpayCharge] {Charge body} - ' . json_encode($charge));
        $this->logger->info('[OpenpayChargeService.processOpenpayCharge] {Charge.id} - ' . $charge->id);
        $this->logger->info('[OpenpayChargeService.processOpenpayCharge] {Charge.description} - ' . $charge->description);
        if($charge != false ) {
            $this->order->update_meta_data('_transaction_id', $charge->id);
            $this->logger->info('[OpenpayChargeService.processOpenpayCharge] {Charge.id} - ' . $charge->id);
            $this->logger->info('[OpenpayChargeService.processOpenpayCharge] {Charge.description} - ' . $charge->description);

            if($payment_settings['sandbox'] && is_user_logged_in()){
                $this->order->update_meta_data('_openpay_customer_sandbox_id',$charge->customer_id);
                $this->logger->info('[OpenpayChargeService.processOpenpayCharge] => Update metadata customer Sandbox ' . $charge->customer_id);
            } else if (!$payment_settings['sandbox'] && is_user_logged_in()){
                $this->order->update_meta_data('_openpay_customer_id',$charge->customer_id);
                $this->logger->info('[OpenpayChargeService.processOpenpayCharge] => Update metadata customer Live ' . $charge->customer_id);
            }

            if($charge_request['capture'] === false && $charge->status == 'in_progress'){
                $captureString = ($this->capture) ? 'true' : 'false';
                $this->logger->info('[OpenpayChargeService.processOpenpayCharge] => Order:' . $this->order->get_id() . ' Set as preauthorized');
                $this->order->update_meta_data('_openpay_capture', $captureString);
            }
            $this->order->save();
        }
        $this->logger->info('[OpenpayChargeService.processOpenpayCharge end]');
        return $charge;
    }

    public function create($openpay_customer, $charge_request, $charge_type) {
        try {
            $this->logger->info('[OpenpayChargeService.create] start');
            $this->logger->info("[OpenpayChargeService.create - CHARGE_REQUEST] => " . json_encode($charge_request) );
            $order_id = $this->order->get_id();

            if (is_user_logged_in()) {
                $customer_id = $this->order->get_customer_id();
                $charge = OpenpayErrorHandler::catchOpenpayError(function () use($openpay_customer, $charge_request, $order_id, $customer_id) {
                   return $openpay_customer->charges->create($charge_request);
                }, $order_id, $customer_id);
            } else {
                $openpay = $this->openpay;
                $charge = OpenpayErrorHandler::catchOpenpayError(function () use ($openpay, $charge_request, $order_id) {
                    return $openpay->charges->create($charge_request);
                }, $order_id);
            }

            if($charge == 3005) throw new Exception("Fraud risk detected by anti-fraud system --- Found in blacklist", 3005);
            $this->logger->info('[OpenpayChargeService.create] => charge result ' . $charge->id);

            if($charge !==false){
                if ($charge->payment_method && $charge->payment_method->type == 'redirect') {
                    $this->logger->info('[OpenpayChargeService.create] => UPDATE METADATA payment_method->url)');
                    $this->order->update_meta_data('_openpay_3d_secure_url', $charge->payment_method->url);
                    $this->order->set_status('on-hold');
                }else{
                    $this->order->delete_meta_data('_openpay_3d_secure_url');
                }
                $this->logger->info('[OpenpayChargeService.create] => UpdateStatus on-hold');
                $this->order->save();
            }
            return $charge;

        } catch (Exception $e) {
            $this->logger->error('[ERROR - OpenpayChargeService.create] Order => ' . $this->order->get_id());
            $this->logger->error('[ERROR - OpenpayChargeService.create] Error => '. $e->getMessage());

            $this->logger->error('[Charge type exception => '. $charge_type);
            $this->logger->error('[Code exception => '. $e->getCode());

            // Si cuenta con autenticación selectiva y hay detección de fraude se envía por 3D Secure
            if ($charge_type == 'auth' && $e->getCode() == '3005') {
                $openpay3ds = new Openpay3dSecure();
                $redirect_url = $openpay3ds->redirect_url_3d();
                $charge_request['use_3d_secure'] = true;
                $charge_request['redirect_url'] = $redirect_url;

                $this->logger->error('[[OpenpayChargeService.create] Redirect url => '. $redirect_url);

                $this->logger->error('[[OpenpayChargeService.create] => 3DSecure Selectivo start');
                if (is_user_logged_in()) {
                    $charge = OpenpayErrorHandler::catchOpenpayError(function () use($openpay_customer, $charge_request, $order_id, $customer_id) {
                        return $openpay_customer->charges->create($charge_request);
                    }, $order_id, $customer_id);
                } else {
                    $openpay = $this->openpay;
                    $charge = OpenpayErrorHandler::catchOpenpayError(function () use ($openpay, $charge_request, $order_id) {
                        return $openpay->charges->create($charge_request);
                    }, $order_id);
                }
                $this->logger->error('[[OpenpayChargeService.create] => 3DSecure Selectivo end');
                
                if ($charge == 3005) return false;
                $this->logger->info('[OpenpayChargeService.create] => Auth Order => '.$this->order->get_id());

                if ($charge->payment_method && $charge->payment_method->type == 'redirect') {
                    $this->logger->info('[OpenpayChargeService.create] => update_order_meta_data '.$charge->payment_method->url);
                    $this->order->update_meta_data('_openpay_3d_secure_url', $charge->payment_method->url);
                    $this->order->save(); // ¡Importante! Guardar los cambios
                    $redirect_url_meta = $this->order->get_meta('_openpay_3d_secure_url');
                    $this->logger->info('[OpenpayChargeService.create] => get update_order_meta_data ' . $redirect_url_meta);
                }else{
                    $this->order->delete_meta_data('_openpay_3d_secure_url');
                }
                return $charge;
            }

//            $this->error($e);
            return false;
        }

        $this->logger->info('[OpenpayChargeService.processOpenpayCharge.create] end');
        return $charge;
    }

    private function collectChargeData($payment_settings)
    {
        $this->logger->info('[OpenpayChargeService.collectChargeData] start');
        $charge_request = array(
            "method" => "card",
            "amount" => number_format((float)$this->order->get_total(), 2, '.', ''),
            "currency" => strtolower(get_woocommerce_currency()),
            "source_id" => $payment_settings['openpay_token'],
            "device_session_id" => $payment_settings['device_session_id'],
            "description" => sprintf("Items: %s", $this->getProductsDetail()),
            "order_id" => $this->order->get_id(),
            "origin_channel" => "PLUGIN_WOOCOMMERCE",
        );

        switch ($payment_settings['country']) {
            case 'MX':
                $charge_request = (new OpenpayChargeHandlerMx)->applyPaymentSettings($charge_request,$payment_settings);
                break;
            case 'CO':
                $charge_request = (new OpenpayChargeHandlerCo)->applyPaymentSettings($charge_request,$payment_settings);
                break;
            case 'PE':
                $charge_request = (new OpenpayChargeHandlerPe)->applyPaymentSettings($charge_request,$payment_settings);
                break;
        }

        if (!is_user_logged_in()) {
            $charge_request["customer"] = $this->customer_service->collectCustomerData($this->order);
        }

        $this->logger->info('[OpenpayChargeService.collectChargeData]' . json_encode($charge_request));
        $this->logger->info('[OpenpayChargeService.collectChargeData] end');
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

}









