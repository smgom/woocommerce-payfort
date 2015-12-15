jQuery( 'form.checkout' ).on( 'checkout_place_order_payfort', function () {
	return fortFormHandler();
});

jQuery( 'form#order_review' ).on( 'submit', function () {
	return fortFormHandler();
});

function initPayment(){
    var data = jQuery( 'form.checkout, form#order_review' ).serialize();
    data += '&SADAD=' + jQuery('[data-method=SADAD]').is(':checked');
    data += '&NAPS=' + jQuery('[data-method=NAPS]').is(':checked');
    jQuery.ajax({
        'url' : '?wc-ajax=checkout',
        'type' : 'POST',
        'dataType' : 'json',
        'data' : data,
        'async':false
    }).done(function(data){
        if (data.form){
            window.success = true;
            jQuery('body').append(data.form);
            jQuery('#payfortpaymentform #submit').click();           
        }

    });
}

function fortFormHandler() {

	var form = jQuery( 'form.checkout, form#order_review' );

	if ( jQuery( '#payment_method_payfort' ).is( ':checked' ) ||  jQuery('[data-method=SADAD]').is(':checked') || jQuery('[data-method=NAPS]').is(':checked')) {
		if ( 0 === jQuery( 'input[name=payfortToken]' ).size() ) {
			initPayment();
			if (window.success){
                return false; //prevent showing error from default wordpress behaviour
            }
            else{
                return true;
            }
		}
	}

	return true;
}
