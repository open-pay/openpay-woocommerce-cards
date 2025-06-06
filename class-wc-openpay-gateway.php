<?php
if(!class_exists('Utils')) {
    require_once(dirname(__FILE__) . "/includes/class-wc-openpay-utils.php");
}

if(!class_exists('WC_Openpay_Client')) {
    require_once(dirname(__FILE__) . "/includes/class-wc-openpay-client.php");
}

if(!class_exists('WC_Openpay_Customer_Service')) {
    require_once(dirname(__FILE__) . "/services/class-wc-openpay-customer-service.php");
}

if(!class_exists('WC_Openpay_Charge_Service')) {
    require_once(dirname(__FILE__) . "/services/class-wc-openpay-charge-service.php");
}

if(!class_exists('WC_Openpay_Cards_Service')) {
    require_once(dirname(__FILE__) . "/services/class-wc-openpay-cards-service.php");
}

 Class WC_Openpay_Gateway extends WC_Payment_Gateway{
    /**
     * Class constructor
     */
    protected $sandbox;
    protected $country;
    protected $merchant_id; 
    protected $private_key; 
    protected $public_key;
    protected $card_points;
    protected $order;
    protected $logger;
    protected $openpay;
    protected $save_card_mode;

    protected $save_cc = false;
    protected $save_cc_option = '';
    protected $cc_options;
    protected $can_save_cc;

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
        $this->card_points = 'yes' === $this->get_option( 'card_points' );
        $this->openpay = WC_Openpay_Client::getOpenpayInstance($this->sandbox, $this->merchant_id, $this->private_key, $this->country);
        $this->save_card_mode = $this->get_option( 'save_card_mode' );

        $save_cc = isset($this->settings['save_cc']) ? (strcmp($this->settings['save_cc'], '0') != 0) : false;
        $this->save_cc = $save_cc;
        $this->save_cc_option = $this->settings['save_cc'];
        $this->can_save_cc = $this->save_cc && is_user_logged_in();

        // This action hook saves the settings
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // We need custom JavaScript to obtain a token
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
        }
    
    /**
    * Plugin options
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
                'title'       => 'ID de comercio',
                'type'        => 'text'
            ),
            'live_public_key' => array(
                'title'       => 'Llave publica (Producción)',
                'type'        => 'text'
            ),
            'live_private_key' => array(
                'title'       => 'Llave secreta (Producción)',
                'type'        => 'password'
            ),
            'test_public_key' => array(
                'title'       => 'Llave publica (Sandbox)',
                'type'        => 'text'
            ),
            'test_private_key' => array(
                'title'       => 'Llave secreta (Sandbox)',
                'type'        => 'password',
            ),
            'card_points' => array(
                'type' => 'checkbox',
                'title' => __('Pago con puntos', 'woothemes'),
                'label' => __('Habilitar', 'woothemes'),
                'description' => __('Recibe pagos con puntos BBVA habilitando esta opción. Esta opción no se puede combinar con pre-autorizaciones o MSI.', 'woothemes'),
                'desc_tip' => true,
                'default' => 'no'
            ),
            'save_card_mode' => array(
                'title' => __('Guardar tarjetas', 'woothemes'),
                'type' => 'select',
                'description' => __('Permite a los usuarios registrar tarjetas para agilizar futuras compras.<br><br>La opción "Guardar y no Solicitar CVV" requiere una configuración adicional de Openpay, contacte a nuestro equipo de soporte para activarlo.', 'woothemes'),
                'default' => '0',
                'desc_tip' => true,
                'options' => array(
                    '0' => __('No guardar', 'woocommerce'),
                    '1' => __('Guardar y solicitar CVV para futuras compras', 'woocommerce'),
                    '2' => __('Guardar y no solicitar CVV para futuras compras', 'woocommerce')
                ),
            ),
            'msi' => array(
            'title' => __('Meses sin intereses', 'woocommerce'),
            'type' => 'multiselect',
            'class' => 'wc-enhanced-select',
            'css' => 'width: 400px;',
            'default' => '',
            'options' => array('3' => '3 meses', '6' => '6 meses', '9' => '9 meses', '12' => '12 meses', '18' => '18 meses'),
                'custom_attributes' => array(
                    'data-placeholder' => __('Opciones', 'woocommerce'),
                ),
            ),
            'minimum_amount_interest_free' => array(
                'type' => 'number',
                'title' => __('Monto mínimo MSI', 'woothemes'),
                'description' => __('Monto mínimo para aceptar meses sin intereses.', 'woothemes'),
                'default' => __('1', 'woothemes')
            )
        );
    }
    
    /**
     * You will need it if you want your custom credit card form
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
    * Load CSS and JS scripts
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
    * Fields validation
    */
    public function validate_fields() {

        $this->logger->debug('validate_fields - ' . json_encode($_POST));
        if( empty( $_POST[ 'openpay_token' ] || $_POST[ 'openpay_selected_card' ] ) ) {
            wc_add_notice( 'Openpay token missing', 'error' );
            return false;
        }
        return true;
    }

    /*
    * Processing the payments 
    */
    public function process_payment( $order_id ) { 

        $cvv = $_POST['openpay_card_cvc'];
        $openpay_token = $_POST[ 'openpay_token' ];
        $device_session_id = $_POST[ 'device_session_id' ];
        $openpay_tokenized_card = $_POST[ 'openpay_tokenized_card' ];
        $openpay_save_card_auth = $_POST[ 'openpay_save_card_auth' ];
        $openpay_selected_card = $_POST[ 'openpay_selected_card' ];
        $openpay_card_points_confirm = $_POST[ 'openpay_card_points_confirm' ];
        $openpay_payment_plan = $_POST['openpay_selected_installment'];



        $this->logger->info('$openpay_tokenized_card ' . json_encode($openpay_tokenized_card)); 
        
        // we need it to get any order detailes
        $this->order = new WC_Order($order_id);
        $order = wc_get_order( $order_id );

        $customer_service = new WC_Openpay_Customer_Service($this->openpay,$this->country,$this->sandbox);
        $openpay_customer = $customer_service->retrieveCustomer($this->order);

        if (is_user_logged_in()) {
            if ($openpay_selected_card !== 'new' && $this->save_card_mode === '1'){
                $this->logger->info(' cvvValidation ');
                $this->cvvValidation($openpay_selected_card,$openpay_customer,$cvv);
                $openpay_token = $openpay_selected_card;
            }elseif($openpay_selected_card !== 'new' && $this->save_card_mode === '2' && $this->country === 'PE'){
                $openpay_token = $openpay_selected_card;
            }

            if ($openpay_save_card_auth === '1' && $openpay_selected_card == 'new') {
                $cards_service = new WC_Openpay_Cards_Service($this->openpay,$this->order,$this->country,$this->sandbox);
                $openpay_token = $cards_service->validateNewCard($openpay_customer, $openpay_token, $device_session_id, $openpay_tokenized_card, $this->save_card_mode);
                $this->logger->info(' $openpay_token ' . json_encode($openpay_token));
                if ($openpay_token){
                    $this->order->update_meta_data('_openpay_card_saved_flag',true); // Used for notice confirmation 
                }
            }
        }

        $payment_settings = Array(
            'openpay_token' => $openpay_token,
            'device_session_id' => $openpay_tokenized_card,
            'openpay_customer' => $openpay_customer,
            'openpay_card_points_confirm' => $openpay_card_points_confirm,
            'openpay_payment_plan' => $openpay_payment_plan
        );

        $charge_service = new WC_Openpay_Charge_Service($this->openpay,$order,$customer_service);
        $charge_service->processOpenpayCharge($payment_settings);


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
        // webhhok     
        }

    private function cvvValidation($openpay_token,$openpay_customer,$cvv){
        if (is_numeric($cvv) && (strlen($cvv) == 3 || strlen($cvv) == 4) ){
            $path       = sprintf('/%s/customers/%s/cards/%s', $this->merchant_id, $openpay_customer->id, $openpay_token);
            $params     = array('cvv2' => $cvv);
            $auth       = $this->private_key;
            $cardInfo = Utils::requestOpenpay($path, $this->country, $this->sandbox,'PUT',$params,$auth);
            if (isset($cardInfo->error_code)){
                $this->logger->error('CVV update has failed.');
                throw new Exception("Error en la transacción: No se pudo completar tu pago.");
            }
        }elseif(!is_numeric($cvv)){
            $this->logger->error('CVV is not valid: Not numeric value');
            throw new Exception("Error en la transacción: No se pudo completar tu pago. El cvv es incorrecto");
        }elseif(!(strlen($cvv) == 3 || strlen($cvv) == 4)){
            $this->logger->error('CVV is not valid: Incorrect number of digits');
            throw new Exception("Error en la transacción: No se pudo completar tu pago. El cvv es incorrecto");
        }else{
            $this->logger->error('CVV is not valid');
            throw new Exception("Error en la transacción: No se pudo completar tu pago.");
        }
    }

 }

