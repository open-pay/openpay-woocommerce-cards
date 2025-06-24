jQuery(document).ready(function () {
    var country = jQuery('#woocommerce_openpay_cards_country').val();
    showOrHideElements(country)

    jQuery('#woocommerce_wc_openpay_gateway_country').change(function () {
        var country = jQuery(this).val();
        console.log('woocommerce_wc_openpay_gateway_country', country);        

        showOrHideElements(country)
    });

    function showOrHideElements (country){

        var chargeMX = jQuery('#woocommerce_wc_openpay_gateway_charge_type').closest('tr');
        var chargeCOPE = jQuery('#woocommerce_wc_openpay_gateway_charge_type_co_pe').closest('tr');
        if (country === 'MX'|| country === undefined) {
            chargeMX.show();
            chargeCOPE.hide();
        } else {
            chargeMX.hide();
            chargeCOPE.show();
        }


        if(country == 'PE' || country == 'CO'){
            if(country == 'PE') {
                jQuery("#woocommerce_wc_openpay_gateway_save_card_mode option[value='2']").show();
                jQuery("#woocommerce_wc_openpay_gateway_capture").closest("tr").show();
            }
            if(country == 'CO') {
                jQuery("#woocommerce_wc_openpay_gateway_save_card_mode option[value='2']").hide();
                jQuery("#woocommerce_wc_openpay_gateway_capture").closest("tr").hide();
            }
        } else if (country == 'MX'){
            jQuery("#woocommerce_wc_openpay_gateway_save_card_mode option[value='2']").hide();
        }
    }
    

    if(jQuery("#woocommerce_wc_openpay_gateway_sandbox").length){
        is_sandbox();

        jQuery("#woocommerce_wc_openpay_gateway_sandbox").on("change", function(e){
            is_sandbox();
        });
    }

    function is_sandbox(){
        sandbox = jQuery("#woocommerce_wc_openpay_gateway_sandbox").is(':checked');
        if(sandbox){
            jQuery("input[name*='live']").parent().parent().parent().hide();
            jQuery("input[name*='test']").parent().parent().parent().show();
        }else{
            jQuery("input[name*='test']").parent().parent().parent().hide();
            jQuery("input[name*='live']").parent().parent().parent().show();
        }
    }
});