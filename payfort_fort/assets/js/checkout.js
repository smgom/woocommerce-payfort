/*jQuery('form.checkout').on('submit', function (e){
   if(jQuery('input[name=payment_method]:checked').val() == 'payfort') {
       e.preventDefault();
       return fortFormHandler();
   }
});*/
jQuery('form.checkout').on('checkout_place_order_payfort', function () {
    return fortFormHandler();
});

jQuery('form#order_review').on('submit', function () {
    return fortFormHandler();
});

function initPayfortFortPayment() {
    var data = jQuery('form.checkout, form#order_review').serialize();
    data += '&SADAD=' + jQuery('[data-method=SADAD]').is(':checked');
    data += '&NAPS=' + jQuery('[data-method=NAPS]').is(':checked');
    var ajaxUrl = wc_checkout_params.checkout_url;
//    if(jQuery('form#order_review').size() == 0){
//        ajaxUrl = '?wc-ajax=checkout';
//    }
    jQuery.ajax({
        'url': ajaxUrl,
        'type': 'POST',
        'dataType': 'json',
        'data': data,
        'async': false
    }).complete(function (response) {
        data = '';
        if(response.form) {
            data = response;
        }
        else{
            var code = response.responseText;
            var newstring = code.replace(/<script[^>]*>(.*)<\/script>/, "");
            if (newstring.indexOf("<!--WC_START-->") >= 0) {
                    newstring = newstring.split("<!--WC_START-->")[1];
            }
            if (newstring.indexOf("<!--WC_END-->") >= 0) {
                    newstring = newstring.split("<!--WC_END-->")[0];
            }
            try {
                data = jQuery.parseJSON( newstring );
            }
            catch(e) {}
        }
        if (data.form) {
            jQuery('#payfort_payment_form').remove();
            jQuery('body').append(data.form);
            window.success = true;
            payfortPaymentSuccess = true;
            if(!isMerchantPageMethod()) {
                jQuery( "#payfort_payment_form" ).submit();
            }
            else{
                showMerchantPage(jQuery('#payfort_payment_form').attr('action'));
            }
        }

    });
}

function fortFormHandler() {

    var form = jQuery('form.checkout, form#order_review');
    if (jQuery('#payment_method_payfort').is(':checked') || jQuery('[data-method=SADAD]').is(':checked') || jQuery('[data-method=NAPS]').is(':checked')) {
        if (0 === jQuery('input[name=payfortToken]').size()) {
            initPayfortFortPayment();
            if (window.success) {
                return false; //prevent showing error from default wordpress behaviour
            }
            else {
                return true;
            }
        }
    }

    return true;
}

function isMerchantPageMethod() {
    if(!jQuery('[data-method=NAPS]').is(':checked') && !jQuery('[data-method=SADAD]').is(':checked')
            && jQuery('#payfort_integration_type').val() == 'merchantPage') {
        return true;
    }
    return false;
}

function showMerchantPage(merchantPageUrl) {
    if(jQuery("#payfort_merchant_page").size()) {
        jQuery( "#payfort_merchant_page" ).remove();
    }
    jQuery("#review-buttons-container .btn-checkout").hide();
    jQuery("#review-please-wait").show();
    
    jQuery('<iframe  name="payfort_merchant_page" id="payfort_merchant_page"height="550px" frameborder="0" scrolling="no" onload="pfIframeLoaded(this)" style="display:none"></iframe>').appendTo('#pf_iframe_content');
    jQuery('.pf-iframe-spin').show();
    jQuery('.pf-iframe-close').hide();
    jQuery( "#payfort_merchant_page" ).attr("src", merchantPageUrl);
    jQuery( "#payfort_payment_form" ).attr("action", merchantPageUrl);
    jQuery( "#payfort_payment_form" ).attr("target","payfort_merchant_page");
    jQuery( "#payfort_payment_form" ).submit();
    jQuery( "#div-pf-iframe" ).show();
}

function pfClosePopup() {
    jQuery( "#div-pf-iframe" ).hide();
    jQuery( "#payfort_merchant_page" ).remove();
    window.location = jQuery( "#payfort_cancel_url" ).val();
}
function pfIframeLoaded(ele) {
    jQuery('.pf-iframe-spin').hide();
    jQuery('.pf-iframe-close').show();
    jQuery('#payfort_merchant_page').show();
}