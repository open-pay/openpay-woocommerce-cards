<?php
/**
* Plugin Name: Openpay Cards Plugin
* Plugin URI: http://www.openpay.mx/docs/plugins/woocommerce.html
* Description: Provides a credit card payment method with Openpay for WooCommerce.
* Version: 3.0.0
* Author: Openpay
* Author URI: http://www.openpay.mx
* Developer: Openpay
* Text Domain: openpay-cards
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

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Openpay\Resources\OpenpayCard;

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

// Hook para usuarios no logueados
add_action('wp_ajax_nopriv_get_type_card_openpay', 'get_type_card_openpay');

// Hook para usuarios logueados
add_action('wp_ajax_get_type_card_openpay', 'get_type_card_openpay');

add_action('woocommerce_order_status_changed','openpay_woocommerce_order_status_change_custom', 10, 3);

add_action('woocommerce_order_item_add_action_buttons','add_partial_capture_toggle', 10, 1 );

add_action('wp_ajax_wc_openpay_admin_order_capture','ajax_capture_handler');

add_action('plugins_loaded', function () {
    \OpenpayCards\Includes\OpenpayErrorHandler::init();
});

//Hooks para llamar servicio 3Dsecure
//add_action('woocommerce_api_openpay_confirm', 'openpay_woocommerce_confirm', 10, 0);
//add_action('template_redirect', 'wc_custom_redirect_after_purchase',0);

// Agrega un enlace de Ajustes del plugin
function openpay_settings_link ( $links ) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_openpay_gateway' ). '">' . __('Ajustes', 'openpay-cards') . '</a>';
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
    	require_once(dirname(__FILE__) . "/Services/class-wc-openpay-refund-service.php");
	}
	if(!class_exists('WC_Openpay_Capture_Service')) {
    	require_once(dirname(__FILE__) . "/Services/class-wc-openpay-capture-service.php");
	}
	/*if(!class_exists('Openpay3dSecure')) {
    	require_once(dirname(__FILE__) . "/Services/PaymentSettings/Openpay3dSecure.php");
	}*/
}

function openpay_cards_admin_enqueue($hook) {
	global $post, $post_type;
    $order_id = ! empty( $post ) ? $post->ID : false;

	$screen_id = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
        ? wc_get_page_screen_id( 'shop-order' )
        : 'shop_order';

	wp_enqueue_script('openpay_cards_admin_form', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '1.0.2' , true);

	 if ( ($order_id && 'shop_order' === $post_type && 'post.php' === $hook) || ($order_id && $screen_id === 'woocommerce_page_wc-orders') ) {
        $order = wc_get_order( $order_id );

        wp_enqueue_script(
            'woo-openpay-admin-order',
            plugins_url(
                'assets/js/openpay-admin-order.js',
                __FILE__
            ),
            array( 'jquery' )
        );

        wp_localize_script(
            'woo-openpay-admin-order',
            'wc_openpay_admin_order',
            array(
                'ajax_url'      => admin_url( 'admin-ajax.php' ),
                'capture_nonce' => wp_create_nonce( 'wc_openpay_admin_order_capture-' . $order_id ),
                'action'        => 'wc_openpay_admin_order_capture',
                'order_id'      => $order_id,
            )
        );

    }
}

function openpay_blocks_support() {
	require_once __DIR__ . '/Includes/class-wc-openpay-gateway-blocks-support.php';

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


function get_type_card_openpay() {
	if(!class_exists('WC_Openpay_Bines_Consult')) {
    	require_once(dirname(__FILE__) . "/Includes/class-wc-openpay-bines-consult.php");
	}

	$openpayBinesConsult = new WC_Openpay_Bines_Consult();
	$openpayBinesConsult->getTypeCardOpenpay();
}

function openpay_woocommerce_order_status_change_custom($order_id, $old_status, $new_status) {
	$openpay_gateway = new WC_Openpay_Gateway();
	$openpayInstance = $openpay_gateway->getOpenpayInstance();
    $capture_service = new WC_Openpay_Capture_Service($openpay_gateway->settings['sandbox'], $openpay_gateway->settings['country'], $openpayInstance);
	$capture_service->openpayWoocommerceOrderStatusChangeCustom( $order_id, $old_status, $new_status );
}

function add_partial_capture_toggle( $order ) {
	$openpay_gateway = new WC_Openpay_Gateway();
	$openpayInstance = $openpay_gateway->getOpenpayInstance();
    $capture_service = new WC_Openpay_Capture_Service($openpay_gateway->settings['sandbox'], $openpay_gateway->settings['country'], $openpayInstance);
	$capture_service->addPartialCaptureToggle( $order );
}

function ajax_capture_handler() {
	$openpay_gateway = new WC_Openpay_Gateway();
	$openpayInstance = $openpay_gateway->getOpenpayInstance();
    $capture_service = new WC_Openpay_Capture_Service($openpay_gateway->settings['sandbox'], $openpay_gateway->settings['country'], $openpayInstance);
	$capture_service->ajaxCaptureHandler();
}

/*function openpay_woocommerce_confirm() {
	$openpay3dSecure = new Openpay3dSecure();
	$openpay3dSecure->openpay_woocommerce_confirm();
}*/