<?php
/**
 * Output a single payment method
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/payment-method.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     2.3.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<li class="wc_payment_method payment_method_<?php echo $gateway->id; ?> update_totals_on_change">
	<input id="payment_method_<?php echo $gateway->id; ?>" type="radio" class="input-radio" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $gateway->chosen, true ); ?> data-order_button_text="<?php echo esc_attr( $gateway->order_button_text ); ?>" />
	<?php if ( $gateway->has_fields() || $gateway->get_description() ) : ?>
		<label for="payment_method_<?php echo $gateway->id; ?>">
            <?php $gateway->payment_fields(); ?>
            <div class="radiobutton-background"></div>
        </label>
	<?php endif; ?>
		<!-- DOWN TO INTRUM PLUGIN -->
    <?php if (($gateway->id == 'wc_intrum_gateway') && $gateway->chosen): ?>
        <div class="intrum-additional-fields">
            <p>Please give these extra informations</p>
            <div class="intrum-additional-fields-inner">
                <p class="form-row validate-required" id="billing_person_ID_field" style=""><label for="billing_person_ID" class="">Henkilötunnus <abbr class="required" title="vaaditaan">*</abbr></label><input type="text" name="billing_person_ID" id="billing_person_ID" placeholder="120380-123C" value=""></p>
                <p class="form-row validate-required" id="billing_company_ID_field" style=""><label for="billing_company_ID" class="">Y-tunnus <abbr class="required" title="vaaditaan">*</abbr></label><input type="text" name="billing_company_ID" id="billing_company_ID" placeholder="1234567-8" value=""></p>
            </div>
        </div>
    <?php endif; ?>
		<!-- UP TO INTRUM PLUGIN -->
</li>