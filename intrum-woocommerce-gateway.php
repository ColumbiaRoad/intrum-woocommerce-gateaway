<?php
require 'plugin-update-checker/plugin-update-checker.php';
$MyUpdateChecker = PucFactory::buildUpdateChecker(
    'https://sivustonikkarit.com/plugin_updater/intrum-woocommerce-gateway.json',
    __FILE__,
	'intrum-woocommerce-gateway'
);
/**
* Plugin Name: Intrum Justitia Woocommerce Gateway
* Description: Intrum Justitia gateway for Woocommerce
* Version: 1.3
* Author: Sivustonikkari
*/
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_action( 'plugins_loaded', 'init_WC_Intrum_Gateway' );
function init_WC_Intrum_Gateway() {
    if (!class_exists( 'WC_Payment_Gateway')) {
        return;
    }
	$c_id = false;
	$p_id = false;
    load_plugin_textdomain('intrum_wc_gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

	add_filter('woocommerce_payment_gateways', 'add_intrum_gateway' );
	add_action('woocommerce_thankyou', 'intrum_thank_you');
	// add_action('woocommerce_payment_complete_order_status', 'complete_checkout');
	add_action('woocommerce_checkout_update_order_meta', 'intrum_checkout_field_update_order_meta' );
 	add_action('woocommerce_checkout_process', 'intrum_checkout_person_id_process');
	add_action('woocommerce_checkout_process', 'intrum_checkout_company_id_process');
	add_action( 'woocommerce_after_checkout_form', 'add_intrum_js');

    function add_intrum_gateway($methods) {
            $methods[] = 'WC_Intrum_Gateway';
            return $methods;
    }

    class WC_Intrum_Gateway extends WC_Payment_Gateway {

        private $version = "1.3";
		private $language = "FI";
		private $country = "FIN";
		private $currency = "EUR";
		private $device	= "1";
		private $content = "1";
		private $type = "0";
		private $algorithm = "3";
		private $merchant = "";
		private $password = "";
		private $stamp = 0;
		private $amount = 0;
		private $reference = "";
		private $message = "";
		private $return	= "";
		private $cancel	= "";
		private $reject	= "";
		private $delayed = "";
		private $delivery_date = "";
		private $firstname = "";
		private $familyname = "";
		private $address = "";
		private $postcode = "";
		private $postoffice = "";
		private $status = "";
		private $email = "";
		private $personID = "";
		private $companyID = "";
		private $rowcount = "";
		private $ooenabled = true;
		private $pienabled = false;
		private $debugmode = true;
		private $override_processing = false;
		// Debug server
		private $serveraddress = "http://localhost:9000/Invoice/Company?";

        function __construct() {

            global $woocommerce;

            $this->id = 'wc_intrum_gateway';

            $this->init_form_fields();
            $this->init_settings();

            $this->has_fields = false;

            $this->method_title = __('Intrum Justitia Yrityslasku', 'intrum_wc_gateway');
            $this->method_description = __('Pay all at once or in installments', 'intrum_wc_gateway');

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('title'). " - " . $this->get_option('description');
            $this->notify_url = WC()->api_request_url('WC_Intrum_Gateway');
            $this->merchant = $this->get_option('merchant');
			$this->merchant = str_replace(' ', '', $this->merchant);
            $this->password = $this->get_option('password');
			$this->password = str_replace(' ', '', $this->password);
 			if(strtolower($this->get_option('ooenabled'))=="no")$this->ooenabled = false;
 			if(strtolower($this->get_option('pienabled'))=="yes")$this->pienabled = true;
 			if(strtolower($this->get_option('override_processing'))=="yes")$this->override_processing = true;
 			if(strtolower($this->get_option('debug'))=="no")$this->serveraddress = "https://maksu.intrum.com/Invoice/Company?";
			$this->tax = 44;//default 24% VAT (calculation happens later, if you are checking my code...)

			if ($this->get_option('language') == 'automatic') {
				$wpLang = strtoupper(get_bloginfo('language'));
				if ($wpLang == 'FI' || $wpLang == 'FI_FI') {
					$this->language = 'FI';
				}
				else if ($wpLang == 'SV_SE') {
					$this->language = 'SE';
				}
				else {
					$this->language = 'EN';
				}
			}
			else {
				$this->language = $this->get_option('language');
			}

			if(!$this->ooenabled && !$this->pienabled){
				add_filter('woocommerce_checkout_fields' , 'add_company_id_field' );
				$c_id =true;
			}
			if($this->pienabled){
				add_filter('woocommerce_checkout_fields' , 'add_person__and_company_id_fields' );
				$c_id =true;
				$p_id =true;
			}
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            add_action('woocommerce_receipt_wc_intrum_gateway', array($this, 'receipt_page'));

			add_action('wp_enqueue_scripts', array($this, 'intrum_checkout_css'));
			if($this->override_processing) add_filter('woocommerce_payment_complete_order_status', 'override_processing');
			
			
        }
		public function get_icon() {
				$icon_html = "<img style='margin:0;width:150px;height:auto;' src='".plugins_url( 'yrityslasku_logopainike.png' , __FILE__ ). "' /><a style='float:right;font-size:.83em;' href='https://www.intrum.com/fi/fi/palvelut-yrityksille/verkkokauppa--ja-myymalaratkaisut/yrityslasku/'>".__('What is Yrityslasku?', 'intrum_wc_gateway'). "</a>";
				return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
			}
        function admin_options() {
			echo "<h3><img style='margin:0;width:300px;height:auto;' src='".plugins_url( 'yrityslasku_logopainike.png' , __FILE__ ). "' /><br>";
            echo  __('Intrum Justitia Yrityslasku for Woocommerce', 'intrum_wc_gateway') . '</h3>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }

		function intrum_checkout_css() {
			wp_register_style('style-site', plugins_url('/css/style.css', __FILE__));
			wp_enqueue_style('style-site');
		}

        function init_form_fields() {

			$this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'intrum_wc_gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable Intrum Justitia Woocommerce Payment Gateway', 'intrum_wc_gateway'),
                    'default' => 'yes'
                ),
				'debug' => array(
                    'title' => __('Test mode', 'intrum_wc_gateway'),
                    'type' => 'checkbox',
                    'label' => __('Use test mode only', 'intrum_wc_gateway'),
					'desc_tip' => __('Use Person ID: 010120-0120 and select Intrum Justitia for identification.', 'intrum_wc_gateway'),
                    'default' => 'yes'
                ),
				'override_processing' => array(
                    'title' => __('Override "processing" status', 'intrum_wc_gateway'),					
                    'type' => 'checkbox',
                    'label' => __('Set paid orders directly to "completed" status', 'intrum_wc_gateway'),					
					'desc_tip' => __('If enabled, paid orders are direclty set to "completed" instead of "processing", regardless of whether or not the product is digital', 'intrum_wc_gateway'),
                    'default' => 'no'
                ),
	            'ooenabled' => array(
                    'title' => __('Enable purchase restrictions checking', 'intrum_wc_gateway'),
                    'type' => 'checkbox',
                    'desc_tip' => __('Use Company ID (Y-tunnus) for checking purchase restrictions', 'intrum_wc_gateway'),
                    'default' => 'yes'
                ),
		        'pienabled' => array(
                    'title' => __('Bypass TUPAS -identification', 'intrum_wc_gateway'),
                    'type' => 'checkbox',
                    'desc_tip' => __('Use Person ID (henkilötunnus) for checking purchase restrictions', 'intrum_wc_gateway'),
                    'default' => 'no'
                ),
				'title' => array(
                    'title' => __('Title', 'intrum_wc_gateway'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'intrum_wc_gateway'),
                    'desc_tip' => false,
                    'default' => __('Intrum Justitia Yrityslasku', 'intrum_wc_gateway')
				),
                'description' => array(
                    'title' => __('Description', 'intrum_wc_gateway'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'intrum_wc_gateway'),
                    'default' => __('Pay in installments or all at once', 'intrum_wc_gateway')
				),
                'merchant' => array(
                    'title' => __('Merchant Id', 'intrum_wc_gateway'),
                    'type' => 'text',
                    'description' => __('Add your merchant Id', 'intrum_wc_gateway'),
                    'default' => ''
				),
                'password' => array(
                    'title' => __('Password', 'intrum_wc_gateway'),
                    'type' => 'text',
                    'description' => __('Add your password', 'intrum_wc_gateway'),
                    'default' => ''
                ),
				'language' => array(
                    'title' => __('Language', 'intrum_wc_gateway'),
                    'type' => 'select',
					'options' => array('automatic' => __('Automatic', 'intrum_wc_gateway'), 'FI' => __('Finnish', 'intrum_wc_gateway'), 'SE' => __('Swedish', 'intrum_wc_gateway'), 'EN' => __('English', 'intrum_wc_gateway')),
                    'description' => __('Payment language (Automatic gets language from Wordpress. If Wordpress language is not Finnish or Swedish, then uses English)', 'intrum_wc_gateway'),
                    'default' => 'automatic'
                ),

            );
        }

        function generate_payment_page($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);

            $total = $woocommerce->cart->total * 100;

			// Some WC plugins make cart->total to be zero or undefined, in those cases, we use order_total
			if (!$total || $total <= 0) {
				$total = $order->order_total * 100;
			}

			$password = $this->password;
            $order_data = get_post_meta($order_id);
            $order_firstname = $order_data["_billing_first_name"][0];
            $order_lastname = $order_data["_billing_last_name"][0];
            $order_companyID = $order_data["_billing_company_ID"][0];
            $order_personID = $order_data["_billing_person_ID"][0];
            $order_adress = $order_data["_billing_address_1"][0];
            $order_adress2 = $order_data["_billing_address_2"][0];
            $order_postcode = $order_data["_billing_postcode"][0];
            $order_city = $order_data["_billing_city"][0];
            $order_countrycode = $order_data["_billing_country"][0];
            $order_email = $order_data["_billing_email"][0];
            $order_phone = $order_data["_billing_phone"][0];

            $coData = array();
            $coProdHash = "";
            $coData['MerchantId'] = $this->merchant;
            $coData['CompanyId'] = $order_companyID;
            $coData['PersonId'] = $order_personID;
            $coData['OrderNumber'] = $order_id;
            $coData['ReturnAddress'] = $order->get_checkout_order_received_url();
            $coData['CancelAddress'] = $order->get_cancel_order_url_raw();
            $coData['ErrorAddress'] = $order->get_cancel_order_url_raw();
            $coData['InvokeAddress'] = $order->get_checkout_order_received_url();
            $coData['Language'] = $this->language;
            $coData['ReceiverName'] = $order_lastname;
            $coData['ReceiverFirstName'] = $order_firstname;
            $coData['ReceiverExtraAddressRow'] = $order_adress2;
            $coData['ReceiverStreetAddress'] = $order_adress;
            $coData['ReceiverCity'] = $order_city;
            $coData['ReceiverZipCode'] = $order_postcode;
            $coData['ReceiverCountryCode'] = $order_countrycode;
            $coData['InvoiceRowCount'] = count($woocommerce->cart->cart_contents);
			$coData['SignatureMethod'] = 'SHA-512';
			$coData['InstallmentCount'] = 1;

			$i=1;
			foreach($woocommerce->cart->cart_contents as $item){
				$product = $item['data'];
				$post = $item['data']->post;
				//tax calculation for Finland
				$price_before_tax = $product->get_price_excluding_tax();
				$line_tax = $item['line_tax'] / $item['quantity'];
				$tax_status = $item[data]->tax_status;
				$total = $item['data']->price;
				$tax_percentage = round((($total-$price_before_tax)/$price_before_tax)*100);
				$included = false;
				if($price_before_tax == $total){
					if($line_tax > 0 ){
						$tax_percentage = ($line_tax /$price_before_tax)*100;
						$included = true;
					}else{
						$this->tax = 9;
					}
				}
				if(!$line_subtotal==='taxable'){
					$this->tax = 9;
				}
				if ($tax_percentage <12 &&  $tax_percentage > 1) {//just in case... 10%
					if($included){
						$this->tax = 46;
					}else{
						$this->tax = 3;
					}
				}
				if ($tax_percentage >12 &&  $tax_percentage < 22) {//just in case... 14%
					if($included){
						$this->tax = 45;
					}else{
						$this->tax = 2;
					}
				}
				if ($tax_percentage >22 ) {//just in case... 24%
					if($included){
						$this->tax = 44;
					}else{
						$this->tax = 1;
					}
				}
				$coData['ProductsList'].= "&Product$i=" . $post->post_title;
				$coData['ProductsList'].= "&VatCode$i=" . $this->tax;
				$coData['ProductsList'].= "&UnitPrice$i=" . $item[data]->price;
				$coData['ProductsList'].= "&UnitAmount$i=" . $item['quantity'];

				$coProdHash .='&'.$post->post_title.'&'.$this->tax.'&'.$item[data]->price ."&" .$item['quantity'];
				$i++;
			}

			$coData['SecretCode'] = $this->password;
			$coData['ProductRimpsu'] = $coProdHash;

	  	$coObject = $this->getCheckoutObject($coData);
	    $response = $this->getCheckoutXML($coObject);

      $xml = simplexml_load_string($response);


        }

       function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);
			$order->update_status('pending', __( 'Yrityslasku is pending', 'intrum_wc_gateway' ));
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        function receipt_page($order) {
			$this->generate_payment_page($order);
		}

        function getCheckoutXML($d) {
			$this->device = "10";
			return $this->sendPost($d);
        }

        function getCheckoutObject($data) {
			$sig = "";
			$sig .= $data['MerchantId']."&";
			$sig .= $data['OrderNumber']."&";
			$sig .= $data['ReturnAddress']."&";
			$sig .= $data['CancelAddress']."&";
			$sig .= $data['ErrorAddress']."&";
			//$sig .= $data['InvokeAddress']."&";
			//$sig .= $data['Language']."&";
			//$sig .= $data['ReceiverName']."&";
			//$sig .= $data['ReceiverFirstName']."&";
			//$sig .= $data['ReceiverExtraAddressRow']."&";
			//$sig .= $data['ReceiverStreetAddress']."&";
			//$sig .= $data['ReceiverCity']."&";
			//$sig .= $data['ReceiverZipCode']."&";
			//$sig .= $data['ReceiverCountryCode']."&";
			$sig .= $data['InvoiceRowCount'];
			//$sig .= $data['SignatureMethod']."&";
			//$sig .= $data['InstallmentCount'];
			$sig .= $data['ProductRimpsu']."&";
			$sig .= $data['SecretCode'];
			$signature = hash('sha512',str_replace(' ', '', $sig));
			$p = "MerchantId=" . $data['MerchantId'] ;
			if(!$this->ooenabled && !$this->pienabled)$p .= "&CompanyId=".$data['CompanyId'];
			if($this->pienabled){
				$p .= "&CompanyId=".$data['CompanyId'];
				$p .= "&PersonId=".$data['PersonId'];
				$p .= "&SkipTupasAuthentication=true";
			}
			$p .= "&InvoiceName=".$data['ReceiverName'] .
			"&InvoiceFirstName=".$data['ReceiverFirstName'] .
			"&InvoiceStreetAddress=".$data['ReceiverStreetAddress'] .
			"&InvoiceCity=".$data['ReceiverCity'] .
			"&InvoiceZipCode=".$data['ReceiverZipCode'] .
			"&OrderNumber=" . $data['OrderNumber'].
			"&InvoiceRowCount=" . $data['InvoiceRowCount'].
			"&ReturnAddress=" . urlencode($data['ReturnAddress']).
			"&CancelAddress=" . urlencode($data['CancelAddress']).
			"&ErrorAddress=" . urlencode($data['ErrorAddress']).
			$data[ProductsList] . "&Signature=$signature";
			$file = plugin_dir_path( __FILE__ ). 'last_query.log';
			file_put_contents($file, $p);
			return $p;

        }

       function sendPost($post) {
			header ("Location: ".$this->serveraddress . $post);
        }

        function validateCheckout($data) {
            global $woocommerce;

            $sig = "";
			$sig .= $data['MerchantId']."&";
			$sig .= $data['OrderNumber']."&";
			$sig .= $data['ReturnAddress']."&";
			$sig .= $data['CancelAddress']."&";
			$sig .= $data['ErrorAddress']."&";
			$sig .= $data['InvoiceRowCount'];
			$sig .= $data['ProductRimpsu']."&";
			$sig .= $data['SecretCode'];
			$signature = hash('sha512',str_replace(' ', '', $sig));

            return $data['MAC'] === $signature;
        }

        function isPaid($status) {
			return($status == 0);
        }
    }

}

function add_company_id_field( $fields ) {
	if(WC()->session->chosen_payment_method=='wc_intrum_gateway')

	 $fields['billing']['billing_company_ID']['placeholder'] = __('1234567-8', 'intrum_wc_gateway');
	 $fields['billing']['billing_company_ID']['label'] = __('Company ID', 'intrum_wc_gateway');
	 $fields['billing']['billing_company_ID']['required'] = true;
	 $ordered = array(
		"billing_first_name",
		"billing_last_name",
		"billing_company",
		"billing_company_ID",
		"billing_address_1",
		"billing_address_2",
		"billing_postcode",
		"billing_city",
		"billing_country",
		"billing_email",
		"billing_phone"

	);
	foreach($ordered as $field){
		$ordered_fields[$field] = $fields["billing"][$field];
	}

	$fields["billing"] = $ordered_fields;	 return $fields;
}

function add_person__and_company_id_fields( $fields ) {
	if(WC()->session->chosen_payment_method=='wc_intrum_gateway')
	 $fields['billing']['billing_person_ID'] = array(
		 'label' => __('Person ID', 'intrum_wc_gateway'),
		 'placeholder' => __('120380-123C', 'intrum_wc_gateway'),
		 'required' => true,
		 'class' => array('form-row-wide'),
		 'clear' => true
	 );

	 $fields['billing']['billing_company_ID'] = array(
		 'label' => __('Company ID', 'intrum_wc_gateway'),
		 'placeholder' => __('1234567-8', 'intrum_wc_gateway'),
		 'required' => true,
		 'class' => array('form-row-wide'),
		 'clear' => true
	 );

	 $ordered = array(
		"billing_first_name",
		"billing_last_name",
		"billing_person_ID",
		"billing_company",
		"billing_company_ID",
		"billing_address_1",
		"billing_address_2",
		"billing_postcode",
		"billing_city",
		"billing_country",
		"billing_email",
		"billing_phone"

	);
	foreach($ordered as $field){
		$ordered_fields[$field] = $fields["billing"][$field];
	}

	$fields["billing"] = $ordered_fields;
	return $fields;

}

function add_intrum_js(){
	wp_register_script( 'intrum_wc_gateway_js', plugins_url('js/intrum_wc_checkout.js',__FILE__ ));
	wp_enqueue_script('intrum_wc_gateway_js');

}
function intrum_checkout_field_update_order_meta( $order_id ) {
	if(WC()->session->chosen_payment_method=='wc_intrum_gateway')
    if(!empty( $_POST['billing_company_ID']))update_post_meta( $order_id, '_billing_company_ID', sanitize_text_field( $_POST['billing_company_ID'] ) );
    if(!empty( $_POST['billing_person_ID']))update_post_meta( $order_id, '_billing_person_ID', sanitize_text_field( $_POST['billing_person_ID'] ) );
}

function intrum_checkout_company_id_process() {
	if(WC()->session->chosen_payment_method=='wc_intrum_gateway')

	load_plugin_textdomain('intrum_wc_gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
	if ( isset($_POST['billing_company_ID']) && !$_POST['billing_company_ID'] && ($_POST['payment_method'] == 'wc_intrum_gateway'))wc_add_notice( __( 'Please enter value for Company ID.', 'intrum_wc_gateway' ), 'error' );
}

function intrum_checkout_person_id_process() {
	if(WC()->session->chosen_payment_method=='wc_intrum_gateway')

	load_plugin_textdomain('intrum_wc_gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');

	if ( isset($_POST['billing_person_ID']) && !$_POST['billing_person_ID'] && ($_POST['payment_method'] == 'wc_intrum_gateway'))wc_add_notice( __( 'Please enter value for Person ID.','intrum_wc_gateway' ), 'error' );
}

function intrum_thank_you($order){
	$order = new WC_Order( $order);
	$payment_gateway = wc_get_payment_gateway_by_order( $order );
	if($payment_gateway->id == 'wc_intrum_gateway') {
		// $order->update_status('processing', __( 'Yrityslasku is approved', 'intrum_wc_gateway' ));
		// $order->reduce_order_stock();

		// Simply call 'payment_complete' and let WooCommerce handle stock and status
		$order->payment_complete();
	}
}

// Ignore status determined by WooCommerce and always set status to 'completed'
function override_processing($string) {
	return 'completed';
}

// Helper function for debugging
function write_log ( $log )  {
      if ( is_array( $log ) || is_object( $log ) ) {
         error_log( print_r( $log, true ) );
      } else {
         error_log( $log );
      }
}


?>
