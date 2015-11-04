jQuery( 'form.checkout' ).on( 'checkout_place_order_payfort', function () {
	return fortFormHandler();
});

jQuery( 'form#order_review' ).on( 'submit', function () {
	return fortFormHandler();
});

function initPayment(){
    jQuery.ajax({
        'url' : '?wc-ajax=checkout',
        'type' : 'POST',
        'dataType' : 'json',
        'data' : jQuery( 'form.checkout, form#order_review' ).serialize(), 
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

	if ( jQuery( '#payment_method_payfort' ).is( ':checked' ) ) {
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
