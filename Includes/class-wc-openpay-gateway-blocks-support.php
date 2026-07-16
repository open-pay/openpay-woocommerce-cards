<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use OpenpayCards\Services\OpenpayCardService;
use OpenpayCards\Services\PaymentSettings\OpenpayInstallments;
use OpenpayCards\Services\PaymentSettings\OpenpayIVA;

final class WC_Openpay_Gateway_Blocks_Support extends AbstractPaymentMethodType
{

    protected $name = 'wc_openpay_gateway';

    // Añadimos estas propiedades para guardar los servicios
    private $iva_service;
    private $cards_service;
    private $installments_service;
    private $openpay_gateway;

    // Permitimos inyectarlos opcionalmente en el constructor (para los tests)
    public function __construct($iva = null, $cards = null, $installments = null, $gateway = null)
    {
        $this->iva_service = $iva;
        $this->cards_service = $cards;
        $this->installments_service = $installments;
        $this->openpay_gateway = $gateway;
    }

    public function initialize()
    {
        // get payment gateway settings
        $this->settings = get_option("woocommerce_{$this->name}_settings", array());

        add_action('woocommerce_rest_checkout_process_payment_with_context', function ($context, $result) {
            if ($context->payment_method === 'wc-openpay-gateway') {
                $myGatewayCustomData = $context->payment_data['myGatewayCustomData'];
                $myGatewayCustomData = $context->payment_data['openpayHolderName'];
                // Here we would use the $myGatewayCustomData to process the payment
                var_dump($myGatewayCustomData);
            }
        }, 10, 2);
    }

    public function is_active()
    {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles()
    {
        $assets_path = plugin_dir_path(__DIR__) . 'blocks/checkout-form/build/index.asset.php';
        //var_dump($assets_file);
        $version = null;
        $dependencies = array();

        if (file_exists($assets_path)) {
            $asset = require $assets_path;
            $version = isset($asset['version']) ? $asset['version'] : $version;
            $dependencies = isset($asset['dependencies']) ? $asset['dependencies'] : $dependencies;
        }
        $extra_dependencies = array(
            'wp-element',
            'wp-plugins',
            'wc-blocks-registry',
            'wc-blocks-checkout',
            'wc-settings',
        );

        foreach ($extra_dependencies as $dependency) {
            if (wp_script_is($dependency, 'registered') || wp_script_is($dependency, 'enqueued')) {
                $dependencies[] = $dependency;
            }
        }
        $dependencies = array_values(array_unique($dependencies));

        wp_register_script(
            'wc-openpay-gateway-blocks-integration',
            plugin_dir_url(__DIR__) . '/blocks/checkout-form/build/index.js',
            $dependencies,
            $version,
            true
        );
        return array('wc-openpay-gateway-blocks-integration');
    }

    public function get_payment_method_data()
    {
        $cards_service = $this->cards_service ?? new OpenpayCardService();
        $installments = $this->installments_service ?? new OpenpayInstallments();
        $IVA = $this->iva_service ?? new OpenpayIVA();
        $openpay_gateway = $this->openpay_gateway ?? new WC_Openpay_Gateway();

        $sandboxUrlPrefix = 'yes' === $this->get_setting('sandbox') ? 'sandbox-' : '';

        return array(
            'merchantId' => $openpay_gateway->merchant_id,
            'publicKey' => $openpay_gateway->public_key,
            'country' => $openpay_gateway->country,
            'openpayAPI' => 'https://' . $sandboxUrlPrefix . 'api.openpay.' . strtolower($this->get_setting('country')) . '/v1',
            'cardPoints' => 'yes' === $this->get_setting('card_points'),
            'installments' => $installments->getInstallments(),
            'iva' => $IVA->getTotalIVA(),
            'saveCardMode' => $this->get_setting('save_card_mode'),
            'savedCardsList' => $cards_service->getCreditCardList(),
            'userLoggedIn' => is_user_logged_in(),
            'ajaxurl' => admin_url('admin-ajax.php'),
        );
    }

}