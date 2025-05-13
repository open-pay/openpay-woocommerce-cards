jQuery(document).ready(function () {
    var country = jQuery('#woocommerce_openpay_cards_country').val();
    showOrHideElements(country)

    jQuery('#woocommerce_wc_openpay_gateway_country').change(function () {
        var country = jQuery(this).val();
        console.log('woocommerce_wc_openpay_gateway_country', country);        

        showOrHideElements(country)
    });

    function showOrHideElements (country){
        if(country == 'PE'){
            jQuery("#woocommerce_wc_openpay_gateway_save_card_mode option[value='2']").show();
        }
        if(country != 'PE'){
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