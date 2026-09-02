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
    const BAD_REQUEST_RESPONSE = 400;
    const UNAUTHORIZED_RESPONSE = 401;
    const NOT_FOUND_RESPONSE = 404;
    const ERROR_RESPONSE = 500;

    const WEBHOOK_AUTH_USER_OPTION = 'openpay_cards_webhook_basic_user';
    const WEBHOOK_AUTH_PASS_OPTION = 'openpay_cards_webhook_basic_pass';

    private static $allowed_events = array(
        'verification',
        'charge.succeeded',
        'transaction.expired',
        'charge.cancelled',
        'charge.failed',
    );

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
     * Registers webhook in Openpay if it does not already exist.
     *
     * @param \WC_Openpay_Gateway $gateway Gateway settings holder.
     * @return void
     */
    public static function register_webhook_if_needed($gateway)
    {
        $service = new self();
        $service->register_webhook($gateway);
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

        if (is_null($data) && !$this->validate_basic_auth_request()) {
            status_header(self::UNAUTHORIZED_RESPONSE);
            wp_die('Unauthorized', 'Openpay Webhook', array('response' => self::UNAUTHORIZED_RESPONSE));
        }

        $json = is_null($data) ? file_get_contents('php://input') : $data;
        $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => payload ' . $json);

        $result = $this->process_webhook($json);

        if (!is_null($data)) {
            $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => end (test mode)');
            return $result['processed'];
        }

        status_header($result['status']);
        wp_die($result['message'], 'Openpay Webhook', array('response' => $result['status']));
    }

    /**
     * Process webhook payload.
     *
     * @param string $json Webhook payload.
     * @return array{processed: bool, status: int, message: string}
     */
    public function process_webhook($json)
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $this->logger->error('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => invalid json payload');
            return $this->response(false, self::BAD_REQUEST_RESPONSE, 'Invalid JSON payload');
        }

        $event_type = isset($data['type']) ? sanitize_text_field((string) $data['type']) : '';
        if ($event_type === '') {
            $this->logger->error('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => missing event type');
            return $this->response(false, self::BAD_REQUEST_RESPONSE, 'Event type not found');
        }

        if (!in_array($event_type, self::$allowed_events, true)) {
            $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => ignored event ' . $event_type);
            return $this->response(true, self::OK_RESPONSE, 'Ignored event');
        }

        if ($event_type === 'verification') {
            $verification_code = isset($data['verification_code']) ? $data['verification_code'] : '';
            $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => verification event received ' . $verification_code);
            return $this->response(true, self::OK_RESPONSE, 'OK');
        }

        $charge_id = $this->extract_charge_id($data);
        if (empty($charge_id)) {
            $this->logger->error('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => missing charge id');
            return $this->response(false, self::BAD_REQUEST_RESPONSE, 'Transaction id not found');
        }

        try {
            $gateway = new \WC_Openpay_Gateway();
            $openpay = OpenpayClient::getOpenpayInstance($gateway->sandbox, $gateway->merchant_id, $gateway->private_key, $gateway->country);
            $charge = $openpay->charges->get($charge_id);

            if (!isset($charge->order_id) || empty($charge->order_id)) {
                $this->logger->error('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => order_id not found for charge ' . $charge->id);
                return $this->response(false, self::NOT_FOUND_RESPONSE, 'order_id not found');
            }

            $order = wc_get_order($charge->order_id);
            if (!$order) {
                $this->logger->error('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => order not found for order_id ' . $charge->order_id);
                return $this->response(false, self::NOT_FOUND_RESPONSE, 'order_id not found');
            }

            $this->apply_charge_status($order, $charge);
            return $this->response(true, self::OK_RESPONSE, 'OK');
        } catch (Exception $e) {
            $this->logger->error('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => error ' . $e->getMessage());
            return $this->response(false, self::ERROR_RESPONSE, 'Openpay Webhook not valid.');
        }
    }

    /**
     * Extract Openpay transaction id from data.transaction.id.
     *
     * @param array $data Webhook payload.
     * @return string|null
     */
    private function extract_charge_id($data)
    {
        if (isset($data['transaction']) && is_array($data['transaction']) && isset($data['transaction']['id']) && is_scalar($data['transaction']['id'])) {
            return sanitize_text_field((string) $data['transaction']['id']);
        }

        return null;
    }

    /**
     * Apply charge status to order.
     *
     * @param \WC_Order $order WooCommerce order.
     * @param object $charge Openpay charge object.
     * @return void
     */
    private function apply_charge_status($order, $charge)
    {
        if ($order->is_paid() || $order->get_status() === 'processing') {
            $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => order already processed ' . $order->get_id());
            return;
        }

        if ($charge->status === 'completed') {
            $order->payment_complete();
            $order->add_order_note(sprintf("%s - Pago Completado via webhook: Transaction Id: '%s'", 'Openpay_Cards', $charge->id));
            $order->save();
            $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => set_status => payment_complete');
            return;
        }

        if (property_exists($charge, 'authorization') && ($charge->status === 'in_progress' && ($charge->id != $charge->authorization))) {
            $order->set_status('on-hold');
            $order->save();
            $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => set_status => on-hold');
            return;
        }

        if ($charge->status === 'expired' || $charge->status === 'cancelled') {
            $note_label = $charge->status === 'expired' ? 'Transacción Expirada' : 'Transacción Cancelada';
            $order->add_order_note(sprintf(" %s - %s.  : '%s'", 'Openpay Cards', $note_label, 'Status ' . $charge->status));
            $order->set_status('failed');
            $order->save();
            $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => set_status => failed_' . $charge->status);
            return;
        }

        $order->add_order_note(sprintf(" %s - Pago Fallido.  : '%s'", 'Openpay Cards', 'Status ' . $charge->status));
        $order->set_status('failed');
        $order->save();
        $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => set_status => failed');
    }

    private function register_webhook($gateway)
    {
        if (!$gateway || empty($gateway->merchant_id) || empty($gateway->private_key) || empty($gateway->country)) {
            return;
        }

        try {
            $openpay = OpenpayClient::getOpenpayInstance($gateway->sandbox, $gateway->merchant_id, $gateway->private_key, $gateway->country);
            $credentials = $this->get_or_create_webhook_credentials();
            $webhook_url = home_url('/wc-api/Openpay_Cards');
            $existing_webhooks = $openpay->webhooks->getList(array('limit' => 100));

            if ($this->webhook_exists($existing_webhooks, $webhook_url)) {
                $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => webhook already exists for ' . $webhook_url);
                return;
            }

            $openpay->webhooks->add(array(
                'url' => $webhook_url,
                'event_types' => self::$allowed_events,
                'username' => $credentials['user'],
                'password' => $credentials['pass'],
            ));

            $this->logger->info('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => webhook registered for ' . $webhook_url);
        } catch (Exception $e) {
            $this->logger->error('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => webhook register error ' . $e->getMessage());
        }
    }

    private function get_or_create_webhook_credentials()
    {
        $user = get_option(self::WEBHOOK_AUTH_USER_OPTION, '');
        $pass = get_option(self::WEBHOOK_AUTH_PASS_OPTION, '');

        if ($user === '' || $pass === '') {
            $user = 'openpay_webhook';
            $pass = wp_generate_password(32, false, false);
            update_option(self::WEBHOOK_AUTH_USER_OPTION, $user, false);
            update_option(self::WEBHOOK_AUTH_PASS_OPTION, $pass, false);
        }

        return array(
            'user' => $user,
            'pass' => $pass,
        );
    }

    private function validate_basic_auth_request()
    {
        $expected_user = get_option(self::WEBHOOK_AUTH_USER_OPTION, '');
        $expected_pass = get_option(self::WEBHOOK_AUTH_PASS_OPTION, '');

        if ($expected_user === '' || $expected_pass === '') {
            $this->logger->error('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => missing stored basic auth credentials');
            return false;
        }

        $provided_user = isset($_SERVER['PHP_AUTH_USER']) ? (string) $_SERVER['PHP_AUTH_USER'] : '';
        $provided_pass = isset($_SERVER['PHP_AUTH_PW']) ? (string) $_SERVER['PHP_AUTH_PW'] : '';

        if ($provided_user === '' && $provided_pass === '') {
            $header = isset($_SERVER['HTTP_AUTHORIZATION']) ? (string) $_SERVER['HTTP_AUTHORIZATION'] : '';
            if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            }

            if (stripos($header, 'basic ') === 0) {
                $decoded = base64_decode(substr($header, 6));
                if (is_string($decoded) && strpos($decoded, ':') !== false) {
                    list($provided_user, $provided_pass) = explode(':', $decoded, 2);
                }
            }
        }

        $valid = hash_equals($expected_user, $provided_user) && hash_equals($expected_pass, $provided_pass);
        if (!$valid) {
            $this->logger->error('[WC_Openpay_3d_secure.openpay_woocommerce_webhook] => invalid basic auth credentials');
            return false;
        }

        return true;
    }

    private function webhook_exists($webhooks, $target_url)
    {
        if (!is_array($webhooks)) {
            return false;
        }

        $target_url = untrailingslashit($target_url);
        foreach ($webhooks as $webhook) {
            if (!is_object($webhook) || !isset($webhook->url)) {
                continue;
            }

            if (untrailingslashit((string) $webhook->url) === $target_url) {
                return true;
            }
        }

        return false;
    }

    private function response($processed, $status, $message)
    {
        return array(
            'processed' => (bool) $processed,
            'status' => (int) $status,
            'message' => (string) $message,
        );
    }
}
