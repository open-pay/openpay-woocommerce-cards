<?php
if(!class_exists('WC_Openpay_Cards_Service')) {
  require_once(dirname(__DIR__) . "/services/class-wc-openpay-cards-service.php");
}

if(!class_exists('WC_Openpay_Installments')) {
    require_once(dirname(__DIR__) . "/services/payment-settings/class-wc-openpay-installments.php");
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Openpay_Gateway_Blocks_Support extends AbstractPaymentMethodType {
    
    protected $name = 'wc_openpay_gateway'; 

    public function initialize() {
		// get payment gateway settings
		$this->settings = get_option( "woocommerce_{$this->name}_settings", array() );

        add_action( 'woocommerce_rest_checkout_process_payment_with_context', function( $context, $result ) {
            if ( $context->payment_method === 'wc-openpay-gateway' ) {
              $myGatewayCustomData = $context->payment_data['myGatewayCustomData'];
              $myGatewayCustomData = $context->payment_data['openpayHolderName'];
              // Here we would use the $myGatewayCustomData to process the payment
              var_dump($myGatewayCustomData);
            }
          }, 10, 2 );
	}

    public function is_active() {
		return ! empty( $this->settings[ 'enabled' ] ) && 'yes' === $this->settings[ 'enabled' ];
	}

    public function get_payment_method_script_handles() {
        $assets_path = plugin_dir_path( __DIR__ ) . 'blocks/checkout-form/build/index.asset.php';
        //var_dump($assets_file);
        $version      = null;
	    $dependencies = array();

        if( file_exists( $assets_path ) ) {
            $asset        = require $assets_path;
            $version      = isset( $asset[ 'version' ] ) ? $asset[ 'version' ] : $version;
            $dependencies = isset( $asset[ 'dependencies' ] ) ? $asset[ 'dependencies' ] : $dependencies;
        }

		wp_register_script(
			'wc-openpay-gateway-blocks-integration',
			plugin_dir_url( __DIR__ ) . '/blocks/checkout-form/build/index.js',
			$dependencies, 
		    $version, 
			true
		);
		return array( 'wc-openpay-gateway-blocks-integration' );
	}

    public function get_payment_method_data() {
      $cards_service = new WC_Openpay_Cards_Service();
      $installments = new WC_Openpay_Installments();
      $openpay_gateway = new WC_Openpay_Gateway();
      $sandboxUrlPrefix = 'yes' === $this->get_setting( 'sandbox' ) ? 'sandbox-' :'';

		return array(
            'merchantId' => $openpay_gateway->merchant_id,
            'publicKey' => $openpay_gateway->public_key,
            'country' => $openpay_gateway->country,
            'openpayAPI' =>  'https://'.$sandboxUrlPrefix.'api.openpay.'.strtolower($this->get_setting('country')).'/v1',
            'cardPoints' => 'yes' === $this->get_setting( 'card_points' ),
            'installments' => $installments->getInstallments(),
            'saveCardMode' => $this->get_setting( 'save_card_mode' ),
            'savedCardsList' => $cards_service->getCreditCardList(),
            'userLoggedIn' => is_user_logged_in(),
            'ajaxurl' => admin_url('admin-ajax.php'),
		);
	}

}