jQuery(function(){
	jQuery( 'body' )
    .on( 'updated_checkout', function() {
        is_intrum(jQuery("#payment_method_wc_intrum_gateway").attr('checked'));

        jQuery('input[name="payment_method"]').change(function(){
            is_intrum(jQuery("#payment_method_wc_intrum_gateway").attr('checked'));


        });
    });
});

function is_intrum(isIt){
	if(isIt){
		jQuery("#billing_company_ID_field").show();
		jQuery("#billing_person_ID_field").show();
	}else{
		jQuery("#billing_company_ID_field").hide();
		jQuery("#billing_person_ID_field").hide();
		
	}
}