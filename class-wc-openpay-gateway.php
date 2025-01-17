<?php
if (file_exists(dirname(__FILE__) . '/lib/openpay/Openpay.php')) {
    require_once(dirname(__FILE__) . '/lib/openpay/Openpay.php');
}

if(!class_exists('Utils')) {
    require_once(dirname(__FILE__) . "/includes/class-wc-openpay-utils.php");
}

use Openpay\Data\Openpay as Openpay;

 Class WC_Openpay_Gateway extends WC_Payment_Gateway{
    /**
     * Class constructor
     */
    protected $sandbox;
    protected $country;
    protected $merchant_id; 
    protected $private_key; 
    protected $public_key;
    protected $order;
    protected $logger;

 	public function __construct() {
        $this->id = 'wc_openpay_gateway'; // payment gateway plugin ID
	    $this->icon = 'https://img.openpay.mx/plugins/openpay_logo.svg'; // URL of the icon that will be displayed on checkout page near your gateway name
	    $this->has_fields = true; // in case you need a custom credit card form
	    $this->method_title = 'Openpay';
	    $this->method_description = 'Openpay Payments Description'; // will be displayed on the options page

        // Method with all the options fields
	    $this->init_form_fields();
        // Load the settings.
        $this->init_settings();
        // Load WC logger
        $this->logger = wc_get_logger(); 

        $this->enabled = $this->get_option( 'enabled' );
        $this->country = $this->get_option( 'country' );
        $this->sandbox = 'yes' === $this->get_option( 'sandbox' );
        $this->merchant_id = $this->get_option( 'merchant_id' );
        $this->private_key = $this->sandbox ? $this->get_option( 'test_private_key' ) : $this->get_option( 'live_private_key' );
        $this->public_key = $this->sandbox ? $this->get_option( 'test_public_key' ) : $this->get_option( 'live_public_key' );

        // This action hook saves the settings
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // We need custom JavaScript to obtain a token
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
        }
    
    /**
    * Plugin options, we deal with it in Step 3 too
    */
    public function init_form_fields(){
        $this->form_fields = array(
            'enabled' => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable Openpay Payments',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'country' => array(
                'type' => 'select',
                'title' => __('País', 'woothemes'),                             
                'default' => 'MX',
                'options' => array(
                    'MX' => 'México',
                    'CO' => 'Colombia',
                    'PE' => 'Perú'
                )
            ),
            'sandbox' => array(
                'title'       => 'Sandbox',
                'label'       => 'Enable Test Mode (Sandbox)',
                'type'        => 'checkbox',
                'description' => 'Place the payment gateway in test mode using test API keys.',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'merchant_id' => array(
                'title'       => 'Merchant ID',
                'type'        => 'text'
            ),
            'live_public_key' => array(
                'title'       => 'Live Public Key',
                'type'        => 'text'
            ),
            'live_private_key' => array(
                'title'       => 'Live Private Key',
                'type'        => 'password'
            ),
            'test_public_key' => array(
                'title'       => 'Sandbox Public Key',
                'type'        => 'text'
            ),
            'test_private_key' => array(
                'title'       => 'Sandbox Private Key',
                'type'        => 'password',
            )
        );
    }
    
    /**
     * You will need it if you want your custom credit card form, Step 4 is about it
     */
    public function payment_fields() {   
           
        // ok, let's display some description before the payment form
        if( $this->description ) {
            // you can instructions for test mode, I mean test card numbers etc.
            if( $this->sandbox ) {
                $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#">documentation</a>.';
                $this->description  = trim( $this->description );
            }
            // display the description with <p> tags etc.
            echo wpautop( wp_kses_post( $this->description ) );
        }

        // I will echo() the form, but you can close PHP tags and print it directly in HTML
        echo '<fieldset id=' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

        // Add this action hook if you want your custom payment gateway to support it
        do_action( 'woocommerce_credit_card_form_start', $this->id );

        // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
        echo '<div class="form-row form-row-wide">
                <label>Card Number <span class="required">*</span></label>
                <input id="openpay-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" data-openpay-card="card_number" />
            </div>
            <div class="form-row form-row-first">
                <label>Expiry Date <span class="required">*</span></label>
                <input id="openpay-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="MM / AA" data-openpay-card="expiration_year" />
            </div>
            <div class="form-row form-row-last">
                <label>Card Code (CVC) <span class="required">*</span></label>
                <input id="openpay-card-cvc" name="openpay-card-cvc" class="input-text wc-credit-card-form-card-cvc openpay-card-input-cvc" type="password" autocomplete="off" placeholder="CVC" data-openpay-card="cvv2" />
            </div>
            <div class="clear"></div>';

        do_action( 'woocommerce_credit_card_form_end', $this->id );

        echo '<div class="clear"></div></fieldset>';         
    }
    
    /*
    * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
    */
    public function payment_scripts() {
        /*
        wp_enqueue_script('payment', plugins_url('assets/js/jquery.payment.js', __FILE__), array( 'jquery' ), '', true);
        wp_enqueue_script('openpay_js_mx', plugins_url('assets/js/mx-openpay.v1.min.js', __FILE__), '', '', true);
        wp_enqueue_script('openpay_fraud_js', 'https://openpay.s3.amazonaws.com/openpay-data.v1.min.js', '', '', true);
        wp_enqueue_script(   'openpay'   , plugins_url('assets/js/openpay.js', __FILE__), array( 'jquery' ), '', true);  
        */

        $scripts = Utils::getUrlScripts($this->country);
        $openpayFraud = 'openpay_fraud_js';

        wp_enqueue_script($scripts['openpay_js']['tag'], plugins_url($scripts['openpay_js']['script'], __FILE__), '', '', true);
        wp_enqueue_script($openpayFraud, $scripts[$openpayFraud], '', '', true);      
        wp_enqueue_script('payment', plugins_url('assets/js/jquery.payment.js', __FILE__), array( 'jquery' ), '', true);
        wp_enqueue_script('openpay', plugins_url('assets/js/openpay.js', __FILE__), array( 'jquery' ), '', true); 

        $openpay_params = array(
            'merchant_id' => $this->merchant_id,
            'public_key' => $this->public_key,
            'sandbox' => $this->sandbox,
            'bootstrap_css' => plugins_url('assets/css/bootstrap.css', __FILE__),
            'bootstrap_js' => plugins_url('assets/js/bootstrap.js', __FILE__),
        );
        
        wp_localize_script('openpay', 'openpay_params', $openpay_params);
    }

    /*
    * Fields validation, more in Step 5
    */
    public function validate_fields() {

        $this->logger->debug('validate_fields - ' . json_encode($_POST));
        if( empty( $_POST[ 'openpay_token' ] ) ) {
            wc_add_notice( 'Openpay token missing', 'error' );
            return false;
        }
        return true;
    }

    /*
    * We're processing the payments here, everything about it is in Step 5
    */
    public function process_payment( $order_id ) {  
        
        // we need it to get any order detailes
        $this->order = new WC_Order($order_id);
        $order = wc_get_order( $order_id );

        $this->processOpenpayCharge($_POST[ 'device_session_id' ] , $_POST[ 'openpay_token' ], $_POST[ 'card_number' ]);


        // we received the payment
        $this->logger->info('Completing Payment');
        $this->order->payment_complete();
        $this->order->reduce_order_stock();

        // some notes to customer (replace true with false to make it private)
        $this->order->add_order_note( 'Orden Pagada', true );

        // Empty cart
        WC()->cart->empty_cart();

        // Redirect to the thank you page
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url( $this->order ),
        );
    }

    /*
        * In case you need a webhook, like PayPal IPN etc
        */
    public function webhook() {        
        }

    public function processOpenpayCharge($device_session_id, $openpay_token, $card_number) {
        $this->logger->info('processOpenpayCharge');

        $amount = number_format((float)$this->order->get_total(), 2, '.', '');
        $openpay_customer = $this->createOpenpayCustomer();
                
        $charge_request = array(
            "method" => "card",
            "amount" => $amount,
            "currency" => strtolower(get_woocommerce_currency()),
            "source_id" => $openpay_token,
            "device_session_id" => $device_session_id,
            "description" => sprintf("Items: %s", $this->getProductsDetail()),            
            "order_id" => $this->order->get_id(),
            'capture' => true,
            'origin_channel' => "PLUGIN_WOOCOMMERCE"
        );

        $charge = $this->createOpenpayCharge($openpay_customer, $charge_request);
        $this->logger->info('processOpenpayCharge {Charge.id} - ' . $charge->id);
        $this->logger->info('processOpenpayCharge {Charge.description} - ' . $charge->description);
    }

    public function createOpenpayCharge($customer, $charge_request) {
        $this->logger->info('createOpenpayCharge'); 
        try {
            $charge = $customer->charges->create($charge_request);
            return $charge;
        } catch (Exception $e) {
            $this->logger->error('[ERROR in createOpenpayCharge] Order => '.$this->order->get_id()); 
            $this->logger->error('[ERROR in createOpenpayCharge] Error => '. json_encode($e));
            $this->logger->error('[ERROR in createOpenpayCharge] Error => '. $e->description);
        }
    }

    public function createOpenpayCustomer() {
        $this->logger->info('createOpenpayCustomer'); 
        $customer_data = array(            
            'name' => $this->order->get_billing_first_name(),
            'last_name' => $this->order->get_billing_last_name(),
            'email' => $this->order->get_billing_email(),
            'requires_account' => false,
            'phone_number' => $this->order->get_billing_phone()            
        );
        
        if ($this->hasAddress($this->order)) {
            $customer_data = $this->formatAddress($customer_data, $this->order);
        }                

        $openpay = $this->getOpenpayInstance();

        try {
            $customer = $openpay->customers->add($customer_data);

            if (is_user_logged_in()) {
                if ($this->is_sandbox) {
                    update_user_meta(get_current_user_id(), '_openpay_customer_sandbox_id', $customer->id);
                } else {
                    update_user_meta(get_current_user_id(), '_openpay_customer_id', $customer->id);
                }                
            }

            return $customer;
        } catch (Exception $e) {
            $this->logger->error('createOpenpayCustomer Order => '.$this->order->get_id()); 
            return false;
        }
    }

    private function formatAddress($customer_data, $order) {
        if ($this->country === 'MX' || $this->country === 'PE') {
            $customer_data['address'] = array(
                'line1' => substr($order->get_billing_address_1(), 0, 200),
                'line2' => substr($order->get_billing_address_2(), 0, 50),
                'state' => $order->get_billing_state(),
                'city' => $order->get_billing_city(),
                'postal_code' => $order->get_billing_postcode(),
                'country_code' => $order->get_billing_country()
            );
        } else if ($this->country === 'CO' ) {
            $customer_data['customer_address'] = array(
                'department' => $order->get_billing_state(),
                'city' => $order->get_billing_city(),
                'additional' => substr($order->get_billing_address_1(), 0, 200).' '.substr($order->get_billing_address_2(), 0, 50)
            );
        }
        return $customer_data;
    }

    public function hasAddress($order) {
        $this->logger->info('hasAddress'); 
        if($order->get_billing_address_1() && $order->get_billing_state() && $order->get_billing_postcode() && $order->get_billing_country() && $order->get_billing_city()) {
            return true;
        }
        return false;    
    }

    public function getOpenpayInstance() {
        $this->logger->info('getOpenpayInstance'); 
        Openpay::setClassificationMerchant('general');
        Openpay::setProductionMode($this->sandbox ? false : true);
        $openpay = Openpay::getInstance($this->merchant_id, $this->private_key, $this->country, $this->getClientIp());
        $userAgent = "Openpay-WOOC".$this->country."/v2";
        return $openpay;
    }

    function getClientIp() {
        $this->logger->info('getClientIp'); 
        // Recogemos la IP de la cabecera de la conexión
        if (!empty($_SERVER['HTTP_CLIENT_IP']))   
        {
          $ipAdress = $_SERVER['HTTP_CLIENT_IP'];
        }
        // Caso en que la IP llega a través de un Proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))  
        {
          $ipAdress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        // Caso en que la IP lleva a través de la cabecera de conexión remota
        else
        {
          $ipAdress = $_SERVER['REMOTE_ADDR'];
        }
        $this->logger->debug('IP IN HEADER: ' . $ipAdress);  
        $ipAdress = trim(explode(",", $ipAdress)[0]);
        return $ipAdress;
      }

      private function getProductsDetail() {
        $this->logger->info('getProductsDetail'); 
        $order = $this->order;
        $products = [];
        foreach( $order->get_items() as $item_product ){                        
            $product = $item_product->get_product();                        
            $products[] = $product->get_name();
        }
        return substr(implode(', ', $products), 0, 249);
    }

 }

