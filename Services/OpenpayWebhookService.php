<?php
namespace OpenpayCards\Services;

use Exception;
use OpenpayCards\Includes\OpenpayClient;

if (!defined('ABSPATH')) {
    exit;
}

class OpenpayWebhookService
{
    const OK_RESPONSE = 200;
    const ERROR_RESPONSE = 500;

    private $logger;

    public function __construct()
    {
        $this->logger = wc_get_logger();
    }

    /**
     * Listener for Openpay server-to-server webhook.
     *
     * @param string|null $data Webhook payload for testing purposes.
     * @return bool|null
     */
    public static function listener($data = null)
    {
        $service = new self();
        return $service->handle($data);
    }

    /**
     * Handles webhook processing and HTTP response.
     *
     * @param string|null $data Webhook payload for testing purposes.
     * @return bool|null
     */
    private function handle($data = null)
    {
        $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => start');

        $json = is_null($data) ? file_get_contents('php://input') : $data;

        $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => payload ' . $json);

        $processed = $this->process_webhook($json);

        if (!is_null($data)) {
            $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => end (test mode)');
            return $processed;
        }

        if ($processed) {
            status_header(self::OK_RESPONSE);
            wp_die('OK', 'Openpay Webhook', array('response' => self::OK_RESPONSE));
        }

        status_header(self::ERROR_RESPONSE);
        wp_die('Openpay Webhook not valid.', 'Openpay Webhook', array('response' => self::ERROR_RESPONSE));
    }

    /**
     * Process webhook payload.
     *
     * @param string $json Webhook payload.
     * @return bool
     */
    public function process_webhook($json)
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $this->logger->error('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => invalid json payload');
            return false;
        }

        if (isset($data['type']) && $data['type'] === 'verification') {
            $verification_code = isset($data['verification_code']) ? $data['verification_code'] : '';
            $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => verification event received ' . $verification_code);
            return true;
        }

        $charge_id = $this->extract_charge_id($data);

        if (empty($charge_id)) {
            $this->logger->error('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => missing charge id');
            return false;
        }

        $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => charge_id ' . $charge_id);

        try {
            $gateway = new \WC_Openpay_Gateway();
            $openpay = OpenpayClient::getOpenpayInstance($gateway->sandbox, $gateway->merchant_id, $gateway->private_key, $gateway->country);
            $charge = $openpay->charges->get($charge_id);

            $order = null;
            if (isset($charge->order_id) && !empty($charge->order_id)) {
                $order = wc_get_order($charge->order_id);
            } else {
                $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => charge without order_id, trying fallback by _transaction_id');
                $order = $this->find_order_by_transaction_id($charge->id);
            }

            if (!$order) {
                $this->logger->error('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => order not found for charge ' . $charge->id);
                return false;
            }

            $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => charge status ' . $charge->status);

            return $this->apply_charge_status($order, $charge);
        } catch (Exception $e) {
            $this->logger->error('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => error ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Finds an order by Openpay transaction id.
     *
     * @param string $transaction_id Openpay charge id.
     * @return \WC_Order|false
     */
    private function find_order_by_transaction_id($transaction_id)
    {
        $orders = wc_get_orders(array(
            'limit' => 1,
            'meta_key' => '_transaction_id',
            'meta_value' => $transaction_id,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ));

        if (is_array($orders) && !empty($orders)) {
            return $orders[0];
        }

        // Fallback for stores where transaction id may be persisted differently.
        $recent_orders = wc_get_orders(array(
            'limit' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ));

        if (is_array($recent_orders)) {
            foreach ($recent_orders as $order) {
                if (!is_a($order, 'WC_Order')) {
                    continue;
                }

                $meta_transaction_id = (string) $order->get_meta('_transaction_id', true);
                $wc_transaction_id = (string) $order->get_transaction_id();

                if ($meta_transaction_id === (string) $transaction_id || $wc_transaction_id === (string) $transaction_id) {
                    return $order;
                }
            }
        }

        return false;
    }

    /**
     * Extracts charge id from webhook payload.
     *
     * @param array $data Webhook payload.
     * @return string|null
     */
    private function extract_charge_id($data)
    {
        $candidate_paths = array(
            array('id'),
            array('transaction', 'id'),
            array('data', 'id'),
            array('resource', 'id')
        );

        foreach ($candidate_paths as $path) {
            $value = $this->read_array_path($data, $path);
            if (!empty($value) && is_scalar($value)) {
                return sanitize_text_field((string) $value);
            }
        }

        return null;
    }

    /**
     * Reads a nested value from array path.
     *
     * @param array $data Source array.
     * @param array $path Path tokens.
     * @return mixed|null
     */
    private function read_array_path($data, $path)
    {
        $current = $data;

        foreach ($path as $token) {
            if (!is_array($current) || !array_key_exists($token, $current)) {
                return null;
            }
            $current = $current[$token];
        }

        return $current;
    }

    /**
     * Applies charge status to order using current plugin rules.
     *
     * @param \WC_Order $order  WooCommerce order.
     * @param object     $charge Openpay charge object.
     * @return bool
     */
    private function apply_charge_status($order, $charge)
    {
        if ($order->is_paid() || $order->get_status() === 'processing') {
            $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => order already processed ' . $order->get_id());
            return true;
        }

        if ($charge->status == 'completed') {
            $order->payment_complete();
            $order->add_order_note(sprintf("%s - Pago Completado via webhook: Transaction Id: '%s'", 'Openpay_Cards', $charge->id));
            $order->save();
            $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => set_status => payment_complete');
            return true;
        }

        if (property_exists($charge, 'authorization') && ($charge->status == 'in_progress' && ($charge->id != $charge->authorization))) {
            $order->set_status('on-hold');
            $order->save();
            $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => set_status => on-hold');
            return true;
        }

        $order->add_order_note(sprintf(" %s - Pago Fallido.  : '%s'", 'Openpay Cards', 'Status ' . $charge->status));
        $order->set_status('failed');
        $order->save();
        $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => set_status => Failed');

        return true;
    }
}
