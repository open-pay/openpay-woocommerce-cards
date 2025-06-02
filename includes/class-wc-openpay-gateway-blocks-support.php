<?php
if(!class_exists('WC_Openpay_Cards_Service')) {
  require_once(dirname(__DIR__) . "/services/class-wc-openpay-cards-service.php");
}

if(!class_exists('WC_Openpay_MSI')) {
    require_once(dirname(__DIR__) . "/services/paymentSettings/class-wc-openpay-msi.php");
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

		return array(
			'title'        => $this->get_setting( 'title' ),
			'description'  => $this->get_setting( 'description' ),
            'merchantId' => $this->get_setting( 'merchant_id' ),
            'publicKey' => $this->get_setting( 'test_public_key' ),
            'country' => $this->get_setting('country'),
            'openpayAPI' => 'https://sandbox-api.openpay.'.strtolower($this->get_setting('country')).'/v1',
            'cardPoints' => 'yes' === $this->get_setting( 'card_points' ),
            'installments' => WC_Openpay_MSI::getMSI( $this->get_setting('msi'), $this->get_setting('minimum_amount_interest_free') ),
            'saveCardMode' => $this->get_setting( 'save_card_mode' ),
            'savedCardsList' => $cards_service->getCreditCardList(),
            'userLoggedIn' => is_user_logged_in()
		);
	}

}