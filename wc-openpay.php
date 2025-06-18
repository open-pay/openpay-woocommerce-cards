<?php
/**
* Plugin Name: Openpay Cards Payments
* Plugin URI: http://www.openpay.mx/docs/plugins/woocommerce.html
* Description: Provides a credit card payment method with Openpay for WooCommerce.
* Version: 1.0.0
* Author: Openpay
* Author URI: http://www.openpay.mx
* Developer: Openpay
* Text Domain: wc-openpay-payments
*
* WC requires at least: 3.0
* WC tested up to: 9.2.3
*
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
* 
* Openpay Docs: http://www.openpay.mx/docs/
*/

 /*
 * This action hook registers WC_Openpay_Gateway class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'openpay_add_gateway_class' );
/*
 * WC_Openpay_Gateway Class file is called by openpay_init_gateway function
 */
add_action( 'plugins_loaded', 'openpay_init_gateway' );
/*
 * This action registers WC_Openpay_Gateway_Blocks_Support class as a WC Payment Block
 */
add_action( 'woocommerce_blocks_loaded', 'openpay_blocks_support' );
/*
 * 
 */
add_action( 'before_woocommerce_init', 'openpay_checkout_blocks_compatibility' );

add_action('admin_enqueue_scripts', 'openpay_cards_admin_enqueue');
add_action('woocommerce_order_refunded', 'openpay_woocommerce_order_refunded', 10, 2);

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'openpay_settings_link' );

// Agrega un enlace de Ajustes del plugin
function openpay_settings_link ( $links ) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_openpay_gateway' ). '">' . __('Ajustes', 'wc-openpay-payments') . '</a>';
    array_push( $links, $settings_link );
    return $links;
}

function openpay_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Openpay_Gateway'; // Gateway Class Name
	return $gateways;
}

function openpay_init_gateway() {
    if (class_exists('WC_Payment_Gateway')) {
        require_once('class-wc-openpay-gateway.php');
    }
	if(!class_exists('WC_Openpay_Refund_Service')) {
    	require_once(dirname(__FILE__) . "/services/class-wc-openpay-refund-service.php");
	}
}

function openpay_cards_admin_enqueue($hook) {
	wp_enqueue_script('openpay_cards_admin_form', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '1.0.2' , true);
}

function openpay_blocks_support() {
	require_once __DIR__ . '/includes/class-wc-openpay-gateway-blocks-support.php';

	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
			$payment_method_registry->register( new WC_Openpay_Gateway_Blocks_Support );
		}
	);
}

function openpay_checkout_blocks_compatibility() {

    if( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				__FILE__,
				true // true (compatible, default) or false (not compatible)
			);
    }
		
}

function openpay_woocommerce_order_refunded($order_id, $refund_id) {
		$openpay_gateway = new WC_Openpay_Gateway();
		$openpayInstance = $openpay_gateway->getOpenpayInstance();
        $refund_service = new WC_Openpay_Refund_Service($openpay_gateway->settings['sandbox'], $openpay_gateway->settings['country'], $openpayInstance);
		$refund_service->refundOrder($order_id, $refund_id);
}