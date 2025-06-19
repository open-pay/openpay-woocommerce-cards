/*
OpenPay.setId(openpay_params.merchant_id);
OpenPay.setApiKey(openpay_params.public_key);
OpenPay.setSandboxMode(openpay_params.sandbox);
var deviceSessionId = OpenPay.deviceData.setup();

console.log("openpay_params: " + openpay_params);
console.log("Merchant_ID: " + openpay_params.merchant_id);
console.log("PublicKey: " + openpay_params.public_key);
console.log("sandbox: " + openpay_params.sandbox);
console.log("deviceSessionId: " + deviceSessionId);
 */

/*
jQuery(document).ready(function () {
    var $form = jQuery("form.checkout, form#order_review");

    //  BOOTSTRAP JS WITH LOCAL FALLBACK
    if(typeof(jQuery.fn.modal) === 'undefined') {        
        var bootstrap_script = document.createElement('script');
        bootstrap_script.setAttribute('type', 'text/javascript');
        bootstrap_script.setAttribute('src', openpay_params.bootstrap_js);
        document.body.appendChild(bootstrap_script);
        jQuery("head").prepend('<link rel="stylesheet" href="'+openpay_params.bootstrap_css+'" type="text/css" media="screen">');
    } else {
        console.log('Bootstrap loaded');
    }

    jQuery('body').on('updated_checkout', function () {
        console.log("Openpay updated_checkout");
        //jQuery('.wc-credit-card-form-card-number').payment('formatCardNumber');
        jQuery('.wc-credit-card-form-card-number').cardNumberInput();
        jQuery('.wc-credit-card-form-card-expiry').payment('formatCardExpiry');
        jQuery('.wc-credit-card-form-card-cvc').payment('formatCardCVC');
    });

    function tokenRequest() {
        //var holder_name = jQuery('#openpay-holder-name').val();
        var card = jQuery('#openpay-card-number').val();
        var cvc = jQuery('#openpay-card-cvc').val();
        var expires = jQuery('#openpay-card-expiry').payment('cardExpiryVal');        

        var str = expires['year'];
        var year = str.toString().substring(2, 4);


        var data = {
            holder_name: "Openpay", // UNHARDCODE
            card_number: card.replace(/ /g, ''),
            cvv2: cvc,
            expiration_month: expires['month'] || 0,
            expiration_year: year || 0        
        };
    
        if (jQuery('#billing_address_1').length) {                                
            if(jQuery('#billing_address_1').val() && jQuery('#billing_state').val() && jQuery('#billing_city').val() && jQuery('#billing_postcode').val()) {
                data.address = {};
                data.address.line1 = jQuery('#billing_address_1').val();
                data.address.line2 = jQuery('#billing_address_2').val();
                data.address.state = jQuery('#billing_state').val();
                data.address.city = jQuery('#billing_city').val();
                data.address.postal_code = jQuery('#billing_postcode').val();
                data.address.country_code = 'MX';
            }                                 
        } 
        console.log(data);
        OpenPay.token.create(data, success_callback, error_callback);
    }

    function success_callback(response) {
        var token = response.data.id;
        var card_number = response.data.card.card_number;
        $form.append('<input type="hidden" name="openpay_token" value="' + token + '" />');
        $form.append('<input type="hidden" name="openpay_card_number" value="' + card_number + '" />');
        $form.submit();       
    }

    function error_callback(response) {
        console.log("ERROR CALLBACK");    
    }

    jQuery('form.checkout').on('checkout_place_order', function (e) {
        console.log("form.checkout");   

        // Pass if we have a token
        if ($form.find('[name=openpay_token]').length){
            console.log("openpay_token = true");
            return true;
        }else{
            console.log("openpay_token = false");
        }
        
        tokenRequest();
        return false;
       
    });



});

 */
