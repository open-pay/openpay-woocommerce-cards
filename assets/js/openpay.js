
OpenPay.setId(openpay_params.merchant_id);
OpenPay.setApiKey(openpay_params.public_key);
OpenPay.setSandboxMode(openpay_params.sandbox);
var deviceSessionId = OpenPay.deviceData.setup();

console.log("openpay_params: " + openpay_params);
console.log("Merchant_ID: " + openpay_params.merchant_id);
console.log("PublicKey: " + openpay_params.public_key);
console.log("sandbox: " + openpay_params.sandbox);
console.log("country: " + openpay_params.country);
console.log("deviceSessionId: " + deviceSessionId);
console.log("openpay_params.installments.payment_plan " + openpay_params.installments.paymentPlan);
console.log("openpay_params.installments.payments " + openpay_params.installments.payments);

jQuery(document).ready(function () {
    jQuery('#device_session_id').val(deviceSessionId);

    //console.log("jQuery v"+jQuery.fn.jquery);
    console.log("jQuery Migrate v"+jQuery.migrateVersion);
    console.log("jQuery v"+jQuery().jquery);

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

    jQuery( "body" ).append('<div class="modal fade" role="dialog" id="card-points-dialog"> <div class="modal-dialog modal-sm"> <div class="modal-content"> <div class="modal-header"> <h4 class="modal-title">Pagar con Puntos</h4> </div> <div class="modal-body"> <p>¿Desea usar los puntos de su tarjeta para realizar este pago?</p> </div> <div class="modal-footer"> <button type="button" class="btn btn-success" data-dismiss="modal" id="points-yes-button">Si</button> <button type="button" class="btn btn-default" data-dismiss="modal" id="points-no-button">No</button> </div> </div> </div></div>');
    var $form = jQuery('form.checkout,form#order_review');
    //var total = openpay_params.total;

    jQuery(document).on("change", "#openpay_month_interest_free", function() {
        var monthly_payment = 0;
        var months = parseInt(jQuery(this).val());
        console.log("months: " + months);
        let total = parseInt(jQuery("#total-monthly-payment div").text());

        if (months > 1) {
            jQuery("#total-monthly-payment").removeClass('hidden');
        } else {
            jQuery("#total-monthly-payment").addClass('hidden');
        }

        monthly_payment = total/months;
        monthly_payment = monthly_payment.toFixed(2);

        jQuery("#monthly-payment").text('$'+monthly_payment+' MXN');
    });

    jQuery(document).on("change", "#openpay_selected_card", function() {

        let country         = openpay_params.country;
        let save_cc_option  = openpay_params.save_cc_option;
        let selected_card   = jQuery('#openpay_selected_card option:selected').text();
        let splited_card    = selected_card.split(" ");
        let card_bin        = splited_card[1].substring(0, 6);


        if (jQuery('#openpay_selected_card').val() !== "new") {
            jQuery('#openpay_save_card_auth').prop('checked', false);
            jQuery('#openpay_save_card_auth').prop('disabled', true);

            jQuery('#openpay-holder-name').val("");
            jQuery('#openpay-card-number').val("");
            jQuery('#openpay-card-expiry').val("");
            jQuery('#openpay-card-cvc').val("");

            jQuery('.openpay-holder-name').hide();
            jQuery('.openpay-card-number').hide();
            jQuery('.openpay-card-expiry').hide();
            jQuery('.openpay_save_card_auth').hide();

            if(country === 'PE' && save_cc_option === '2') {
                jQuery('.openpay-card-cvc').hide();
            }

            jQuery('.openpay-card-cvc').css({ float:"inherit" });
            jQuery('#card_cvc_img').css({ right: "57%" });

            jQuery('#payment_form_openpay_cards').show();

            if(country === 'PE'){
                openpay_params.installments.paymentPlan ? getTypeCard(card_bin, country) : '';
            } else {
                getTypeCard(card_bin, country);
            }

        } else {
            jQuery('#payment_form_openpay_cards').show();
            jQuery('.openpay-holder-name').show();
            jQuery('.openpay-card-number').show();
            jQuery('.openpay-card-expiry').show();
            jQuery('.openpay-card-cvc').show();
            jQuery('.openpay_save_card_auth').show();
            jQuery('#card_cvc_img').css({ right: "5%" });
            jQuery('.openpay-card-cvc').css({ float:"right" });
            jQuery('#openpay_save_card_auth').prop('disabled', false);
        }
    });

    jQuery('.wc-credit-card-form-card-number').cardNumberInput();
    jQuery('.wc-credit-card-form-card-expiry').payment('formatCardExpiry');
    jQuery('.wc-credit-card-form-card-cvc').payment('formatCardCVC');

    jQuery('body').on('updated_checkout', function () {
        console.log("Openpay updated_checkout");
        //jQuery('.wc-credit-card-form-card-number').payment('formatCardNumber');
        jQuery('.wc-credit-card-form-card-number').cardNumberInput();
        jQuery('.wc-credit-card-form-card-expiry').payment('formatCardExpiry');
        jQuery('.wc-credit-card-form-card-cvc').payment('formatCardCVC');
    });

    jQuery('body').on('click', 'form.checkout button:submit', function () {
        let save_cc_option  = openpay_params.save_cc_option;
        console.log("woocommerce_error");
        let country = openpay_params.country;
        jQuery('.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message').remove();
        // Make sure there's not an old token on the form
        jQuery('form.checkout').find('[name=openpay_token]').remove();

        // Verify Card Data if openpay cards payment method is selected.
        if (jQuery('input[name=payment_method]:checked').val() == 'wc_openpay_gateway') {
            console.log("Verifying card data");
            return CardsErrorHandler(save_cc_option);
        }
    });

    function CardsErrorHandler (save_cc_option){
        // Check if holder name is not empty or has invalid format
        const pattern = new RegExp('^[A-ZÁÉÍÓÚÑ ]+$','i');
        if (jQuery('#openpay_selected_card').val() == "new" && (jQuery('#openpay-holder-name').val().length < 1 || !pattern.test(jQuery('#openpay-holder-name').val()))) {
            error_callback({data:{error_code:1}});
            console.log('Holder name is missing');
            return false;
        }
        // Check if cvv is not empty
        if (jQuery('#openpay_selected_card').val() !== "new" &&  jQuery('#openpay-card-cvc').val().length < 3 && save_cc_option === '1') {
            error_callback({data:{error_code:2006}});
            return false;
        }

    }

    jQuery('form#order_review').submit(function () {
        console.log("form#order_review");
        if (jQuery('input[name=payment_method]:checked').val() !== 'wc_openpay_gateway') {
            return true;
        }
        $form.find('.payment-errors').html('');
        //$form.block({message: null, overlayCSS: {background: "#fff url(" + woocommerce_params.ajax_loader_url + ") no-repeat center", backgroundSize: "16px 16px", opacity: 0.6}});

        if (jQuery('#openpay_selected_card').val() !== 'new') {
            $form.append('<input type="hidden" name="openpay_token" value="' + jQuery('#openpay_selected_card').val() + '" />');
            return true;
        }

        // Pass if we have a token
        if ($form.find('[name=openpay_token]').length){
            console.log("openpay_token = true");
            return true;
        }else{
            console.log("openpay_token = false");
        }
        openpayFormHandler();
        return false;
    });

    // Bind to the checkout_place_order event to add the token
    jQuery('form.checkout').bind('checkout_place_order', function (e) {
        console.log("form.checkout");
        if (jQuery('input[name=payment_method]:checked').val() !== 'wc_openpay_gateway') {
            return true;
        }
        console.log("checkout_place_order");
        $form.find('.payment-errors').html('');
        //$form.block({message: null, overlayCSS: {background: "#fff url(" + woocommerce_params.ajax_loader_url + ") no-repeat center", backgroundSize: "16px 16px", opacity: 0.6}});

        if (jQuery('#openpay_selected_card').val() !== 'new') {
            $form.append('<input type="hidden" name="openpay_token" value="' + jQuery('#openpay_selected_card').val() + '" />');
            return true;
        }

        // Pass if we have a token
        if ($form.find('[name=openpay_token]').length){
            console.log("openpay_token = true");
            return true;
        }else{
            console.log("openpay_token = false");
        }

        openpayFormHandler();
        // Prevent the form from submitting with the default action
        return false;
    });

    function openpayFormHandler() {
        var holder_name = jQuery('#openpay-holder-name').val();
        var card = jQuery('#openpay-card-number').val();
        var cvc = jQuery('#openpay-card-cvc').val();
        var expires = jQuery('#openpay-card-expiry').payment('cardExpiryVal');

        var str = expires['year'];
        var year = str.toString().substring(2, 4);


        var data = {
            holder_name: holder_name,
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
                data.address.country_code = openpay_params.country;
            }
        }

        OpenPay.token.create(data, success_callback, error_callback);
    }


    function success_callback(response) {
        //var $form = jQuery("form.checkout, form#order_review");
        var token = response.data.id;
        var card_number = response.data.card.card_number;
        $form.append('<input type="hidden" name="openpay_token" value="' + token + '" />');
        $form.append('<input type="hidden" name="openpay_tokenized_card" value="' + card_number + '" />');
        console.log("card points: " + response.data.card.points_card);
        if (openpay_params.country == "MX" && response.data.card.points_card && openpay_params.use_card_points) {
            // Si la tarjeta permite usar puntos, mostrar el cuadro de diálogo
            jQuery("#card-points-dialog").modal("show");
        } else {
            // De otra forma, realizar el pago inmediatamente
            $form.submit();
        }
    }

    jQuery("#points-yes-button").on('click', function () {
        jQuery('#openpay_card_points_confirm').val('true');
        $form.submit();
    });



    jQuery("#points-no-button").on('click', function () {
        jQuery('#openpay_card_points_confirm').val('false');
        $form.submit();
    });

    function error_callback(response) {
        //var $form = jQuery("form.checkout, form#order_review");
        var msg = "";
        switch (response.data.error_code) {
            case 1000:
                msg = "Servicio no disponible.";
                break;

            case 1001:
                msg = "Los campos no tienen el formato correcto, o la petición no tiene campos que son requeridos.";
                break;

            case 1004:
                msg = "Servicio no disponible.";
                break;

            case 1005:
                msg = "Servicio no disponible.";
                break;

            case 2004:
                msg = "El dígito verificador del número de tarjeta es inválido de acuerdo al algoritmo Luhn.";
                break;

            case 2005:
                msg = "La fecha de expiración de la tarjeta es anterior a la fecha actual.";
                break;

            case 2006:
                msg = "El código de seguridad de la tarjeta (CVV2) no fue proporcionado o es incorrecto";
                break;

            case 1:
                msg = "El nombre del titular de la tarjeta no fue proporcionado o tiene un formato inválido.";
                break;

            default: //Demás errores 400
                msg = "La petición no pudo ser procesada.";
                break;
        }

        // show the errors on the form
        jQuery('.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message').remove();
        jQuery('#openpay_selected_card').closest('div').before('<ul style="background-color: #e2401c; color: #fff;" class="woocommerce_error woocommerce-error"><li> ERROR ' + response.data.error_code + '. ' + msg + '</li></ul>');
        $form.unblock();
    }

    var card_old;
    jQuery('body').on("keyup", "#openpay-card-number", function() {
        let card = jQuery(this).val();
        let country = openpay_params.country;
        let card_without_space = card.replace(/\s+/g, '')
        if(card_without_space.length == 8) {
            if ((country == 'MX' && !openpay_params.installments.payments) || (country == 'PE' && !openpay_params.installments.paymentPlan)) {
                console.log("Openpay Params without installments");
                return;
            }

            var card_bin = card_without_space.substring(0, 8);
            if(card_bin != card_old) {
                getTypeCard(card_bin, country);
                card_old = card_bin;
            }
        }
    });

    function getTypeCard(cardBin, country) {
        jQuery.ajax({
            type : "post",
            url : openpay_params.ajaxurl,
            data : {
                action: "get_type_card_openpay",
                card_bin : cardBin,
            },
            beforeSend: function () {
                jQuery("#openpay_cards").addClass("opacity");
                jQuery(".ajax-loader").addClass("is-active");
            },
            error: function(response){
                console.log(response);
            },
            success: function(response) {
                console.log(response);
                if(response.status == 'success') {
                    if(response.card_type === 'CREDIT'){
                        if (country == 'MX') jQuery("#openpay_month_interest_free").closest(".form-row").show(); else jQuery('#openpay_installments').closest(".form-row").show();
                    } else if(response.installments && response.installments.length > 0 && openpay_params.installments.paymentPlan) {
                        jQuery('#openpay_installments_pe').empty();

                        jQuery('#openpay_installments_pe').append(jQuery('<option>', {
                            value: 1,
                            text : 'Solo una cuota'
                        }));

                        if (response.withInterest || response.withInterest === null ){
                            jQuery("#installments_title").text("Cuotas con Interés");
                            jQuery('#openpay_has_interest_pe').val(true);
                        }else{
                            jQuery("#installments_title").text("Cuotas sin Interés");
                            jQuery('#openpay_has_interest_pe').remove();
                        }
                        jQuery('#openpay_installments_pe').closest(".form-row").show();

                        jQuery.each( response.installments, function( i, val ) {
                            if (val == 1) {return}
                            jQuery('#openpay_installments_pe').append(jQuery('<option>', {
                                value: val,
                                text : val + ' coutas'
                            }));
                        });

                    } else {
                        if (country == 'MX') {
                            jQuery("#openpay_month_interest_free").closest(".form-row").hide();
                            jQuery('#openpay_month_interest_free option[value="1"]').attr("selected",true);
                            jQuery("#total-monthly-payment").hide();
                        } else {
                            jQuery("#openpay_installments").closest(".form-row").hide();
                            jQuery('#openpay_installments option[value="1"]').attr("selected",true);
                            jQuery('#openpay_installments_pe').closest(".form-row").hide();
                            jQuery('#openpay_installments_pe option[value="1"]').attr("selected",true);

                        }
                    }
                } else {
                    jQuery("#openpay_month_interest_free").closest(".form-row").hide();
                    jQuery("#openpay_installments").closest(".form-row").hide();
                    jQuery('#openpay_installments_pe').closest(".form-row").hide();
                }
            },
            complete: function () {
                jQuery("#openpay_cards").removeClass("opacity");
                jQuery(".ajax-loader").removeClass("is-active");
            }
        })
    }
});