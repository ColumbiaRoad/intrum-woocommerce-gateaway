<?php
/**
* Plugin Name: Intrum Justitia Woocommerce Gateway
* Description: Intrum Justitia gateway for Woocommerce
* Version: 1.4
* Author: Intrum
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
	load_plugin_textdomain('intrum_wc_gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
	add_filter('woocommerce_payment_gateways', 'add_intrum_gateway' );
	add_action('woocommerce_checkout_update_order_meta', 'intrum_checkout_field_update_order_meta' );
 	add_action('woocommerce_checkout_process', 'intrum_checkout_person_id_process');
	add_action('woocommerce_checkout_process', 'intrum_checkout_company_id_process');
	add_action( 'woocommerce_after_checkout_form', 'add_intrum_js');
	add_action('rest_api_init', function() {
		register_rest_route( 'intrum-woocommerce-gateway/v1', '/payment', array(
			'methods'  => 'POST, GET',
			'callback' => 'payment_return_route',
			)
		);
	});

	function add_intrum_gateway($methods) {
		$methods[] = 'WC_Intrum_Gateway';
		return $methods;
	}

	class WC_Intrum_Gateway extends WC_Payment_Gateway {

		private static $instance = null;

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
		private $tax_included = true;
		// Debug server
		private $serveraddress = " https://maksu.intrum.com/Invoice_Test/Company?";

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
			$this->tax_included = $this->get_option('tax_included');
 			if(strtolower($this->get_option('ooenabled'))=="no")$this->ooenabled = false;
 			if(strtolower($this->get_option('pienabled'))=="yes")$this->pienabled = true;
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
			}
			if($this->pienabled){
				add_filter('woocommerce_checkout_fields' , 'add_person_and_company_id_fields' );
			}
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

			add_action('woocommerce_receipt_wc_intrum_gateway', array($this, 'receipt_page'));

			add_action('wp_enqueue_scripts', array($this, 'intrum_checkout_css'));
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
					'type' => 'password',
					'description' => __('Add your password', 'intrum_wc_gateway'),
					'default' => ''
				),
				'language' => array(
					'title' => __('Language', 'intrum_wc_gateway'),
					'type' => 'select',
					'options' => array(
						'automatic' => __('Automatic', 'intrum_wc_gateway'),
						'FI' => __('Finnish', 'intrum_wc_gateway'),
						'SE' => __('Swedish', 'intrum_wc_gateway'),
						'EN' => __('English', 'intrum_wc_gateway')
					),
					'description' => __('Payment language (Automatic gets language from Wordpress. If Wordpress language is not Finnish or Swedish, then uses English)', 'intrum_wc_gateway'),
					'default' => 'automatic'
				),
				'tax_included' => array(
					'title' => __('Are taxes included or excluded in prices?', 'intrum_wc_gateway'),
					'type' => 'select',
					'options' => array(
						true => __('Included', 'intrum_wc_gateway'),
						false => __('Excluded', 'intrum_wc_gateway'),
					),
					'description' => __('Intrum needs this information for bill. ', 'intrum_wc_gateway'),
					'default' => true
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

			$co_data = array();
			$coProdHash = "";
			$co_data['MerchantId'] = $this->merchant;
			$co_data['CompanyId'] = $order_companyID;
			$co_data['PersonId'] = $order_personID;
			$co_data['OrderNumber'] = $order_id;
			$co_data['ReturnAddress'] = create_return_url("success", $order_id);
			$co_data['CancelAddress'] = create_return_url("cancel", $order_id);
			$co_data['ErrorAddress'] = create_return_url("error", $order_id);
			$co_data['InvokeAddress'] = create_return_url("success", $order_id);
			$co_data['Language'] = $this->language;
			$co_data['ReceiverName'] = $order_lastname;
			$co_data['ReceiverFirstName'] = $order_firstname;
			$co_data['ReceiverExtraAddressRow'] = $order_adress2;
			$co_data['ReceiverStreetAddress'] = $order_adress;
			$co_data['ReceiverCity'] = $order_city;
			$co_data['ReceiverZipCode'] = $order_postcode;
			$co_data['ReceiverCountryCode'] = $order_countrycode;
			$co_data['InvoiceRowCount'] = count($woocommerce->cart->cart_contents);
			$co_data['SignatureMethod'] = 'SHA-512';
			$co_data['InstallmentCount'] = 1;
			$co_data['ProductList'] = array();

			if ( sizeof( $order->get_items() ) > 0 ) {
				foreach ( $order->get_items() as $item ) {
					$item_net = round($order->get_line_subtotal( $item, false ),2);
					$item_tax = round($order->get_line_tax( $item, false ),2);
					$item_vat_perc = round($item_tax * 100 / $item_net);
					$item_total = $order->get_item_total( $item, true );
					// Check for WooCommerce version
					if(version_check()) {
						$item_name = $item->get_name();
						$item_quantity = $item->get_quantity();
					} else {
						$item_name = $item['name'];
						$item_quantity = $item['item_meta']['_qty'][0];
					}
					//Convert tax percentage to Intrum's tax class id, eg one of (1,2,3,9,44,45,46)
					switch ($item_vat_perc) {
						case 10:
							$this->tax = $this->tax_included ? 46 : 3;
							break;
						case 14:
							$this->tax = $this->tax_included ? 45 : 2;
							break;
						case 24:
							$this->tax = $this->tax_included ? 44 : 1;
							break;
						default:
							$this->tax = 9;
							break;
					}

					array_push($co_data['ProductList'], array(
						"Product"=>$item_name,
						"VatCode"=>$this->tax,
						"UnitPrice"=>$item_total,
						"UnitAmount"=>$item_quantity));
				}
			}

			$co_data['SecretCode'] = $this->password;
			$co_query = $this->generate_checkout_query($co_data);
			$this->checkout($co_query);
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

    function checkout($query) {
			$this->device = "10";
			return $this->redirect_to_intrum($query);
    }

		/**
		 * Generate query that is used for the checkout process
		 */
    function generate_checkout_query($data) {
			$query = "MerchantId={$data['MerchantId']}" .
			"&OrderNumber={$data['OrderNumber']}" .
			"&ReturnAddress={$this->urlencode($data['ReturnAddress'])}" .
			"&CancelAddress={$this->urlencode($data['CancelAddress'])}" .
			"&ErrorAddress={$this->urlencode($data['ErrorAddress'])}" .
			"&InvokeAddress={$this->urlencode($data['InvokeAddress'])}" .
			"&Language={$data['Language']}" .
			"&InvoiceName={$data['ReceiverName']}" .
			"&InvoiceFirstName={$data['ReceiverFirstName']}" .
			"&InvoiceStreetAddress={$data['ReceiverStreetAddress']}" .
			"&InvoiceExtraAddressRow={$data['ReceiverExtraAddressRow']}" .
			"&InvoiceCity={$data['ReceiverCity']}" .
			"&InvoiceZipCode={$data['ReceiverZipCode']}";
			"&InvoiceCountryCode={$data['ReceiverCountryCode']}";
			if(!$this->ooenabled && !$this->pienabled) {
				$query .= "&CompanyId={$data['CompanyId']}";
			}
			if($this->pienabled) {
				$query .= "&SkipTupasAuthentication=true" .
				"&PersonId={$data['PersonId']}" .
				"&CompanyId={$data['CompanyId']}";
			}
			$query .= "&InvoiceRowCount={$data['InvoiceRowCount']}" .
			"&SignatureMethod={$data['SignatureMethod']}" .
			"&Signature={$this->generate_checkout_signature($data)}" .
			"&{$this->get_product_details($data)}";
			$file = plugin_dir_path( __FILE__ ). 'last_query.log';
			file_put_contents($file, $query);
			return $query;
    }

		/**
		 * Forward the user to Intrum's site
		 */
    function redirect_to_intrum($query) {
			header ("Location: ".$this->serveraddress . $query);
    }

		/**
		 * Generate signature for the checkout query which is validated
		 * by Intrum.
		 */
		private function generate_checkout_signature($data) {
			$sig = "{$data['MerchantId']}&" .
			"{$data['OrderNumber']}&" .
			"{$data['ReturnAddress']}&" .
			"{$data['CancelAddress']}&" .
			"{$data['ErrorAddress']}&";
			// Optional values
			if(!empty($data['InvokeAddress'])) $sig .= "{$data['InvokeAddress']}&";
			if(!empty($data['Language'])) $sig .= "{$data['Language']}&";

			$sig .= "{$data['InvoiceRowCount']}&" .
			"{$data['SignatureMethod']}&" .
			"{$this->get_product_detail_values($data)}&" .
			$data['SecretCode'];

			$algorithm = parse_signature_algorithm($data['SignatureMethod']);

			return hash($algorithm, str_replace(' ', '', $sig));
		}

		/**
		 * Generate part of signature string that contains the values of each
		 * product in the cart.
		 */
		private function get_product_detail_values($data) {
			$details = "";
			for ($i = 0; $i < $data['InvoiceRowCount']; $i++) {
				$details .= "{$data['ProductList'][$i]['Product']}&";
				$details .= "{$data['ProductList'][$i]['VatCode']}&";
				$details .= "{$data['ProductList'][$i]['UnitPrice']}&";
				$details .= "{$data['ProductList'][$i]['UnitAmount']}&";
			}
			// Remove trailing "&" so $detail string can be used as expected
			return rtrim($details, "&");
		}

		/**
		 * Generate part of checkout query that describes the details of each
		 * product in the cart.
		 */
		private function get_product_details($data) {
			$details = "";
			for ($i = 0; $i < $data['InvoiceRowCount']; $i++) {
				// Needed because PHP can't handle adding two integers in a string concatenation
				$nameIndex = $i + 1;
				$details .= "Product$nameIndex={$data['ProductList'][$i]['Product']}&";
				$details .= "VatCode$nameIndex={$data['ProductList'][$i]['VatCode']}&";
				$details .= "UnitPrice$nameIndex={$data['ProductList'][$i]['UnitPrice']}&";
				$details .= "UnitAmount$nameIndex={$data['ProductList'][$i]['UnitAmount']}&";
			}
			// Remove trailing "&" so $detail string can be used as expected
			return rtrim($details, "&");
		}

		/**
		 * Wrapper for built-in 'urlencode', so it can be neatly called within HEREDOC strings
		 */
		private function urlencode($str) {
			return urlencode($str);
		}

		/**
		 * Receive an instance of WC_Intrum_Gateway
		 */
		public static function get_instance() {
			if (self::$instance == null) {
				self::$instance = new self;
			}
			return self::$instance;
		}
  }

}

function add_company_id_field( $fields ) {
	if(WC()->session->chosen_payment_method=='wc_intrum_gateway') {
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
		$fields["billing"] = $ordered_fields;
	}
	return $fields;
}

function add_person_and_company_id_fields( $fields ) {
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
	if(WC()->session->chosen_payment_method=='wc_intrum_gateway') {
    if(!empty( $_POST['billing_company_ID'])) update_post_meta( $order_id, '_billing_company_ID', sanitize_text_field( $_POST['billing_company_ID'] ) );
    if(!empty( $_POST['billing_person_ID'])) update_post_meta( $order_id, '_billing_person_ID', sanitize_text_field( $_POST['billing_person_ID'] ) );
	}
}

function intrum_checkout_company_id_process() {
	if(WC()->session->chosen_payment_method=='wc_intrum_gateway') {
		load_plugin_textdomain('intrum_wc_gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
	}

	if ( isset($_POST['billing_company_ID']) && !$_POST['billing_company_ID'] && ($_POST['payment_method'] == 'wc_intrum_gateway')) {
		wc_add_notice( __( 'Please enter value for Company ID.', 'intrum_wc_gateway' ), 'error' );
	}
}

function intrum_checkout_person_id_process() {
	if(WC()->session->chosen_payment_method=='wc_intrum_gateway') {
		load_plugin_textdomain('intrum_wc_gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
	}
	if ( isset($_POST['billing_person_ID']) && !$_POST['billing_person_ID'] && ($_POST['payment_method'] == 'wc_intrum_gateway')) {
		wc_add_notice( __( 'Please enter value for Person ID.','intrum_wc_gateway' ), 'error' );
	}
	if(WC_Intrum_Gateway::get_instance()->get_option('debug')=="no") { // enable bad user id in test mode
		if (isset($_POST['billing_person_ID']) && $_POST['billing_person_ID'] &&
			($_POST['payment_method'] == 'wc_intrum_gateway') && !person_id_ok($_POST['billing_person_ID'])) {
			wc_add_notice( __( 'Henkilötunnus on virheellinen. Tarkista henkilötunnus.','intrum_wc_gateway' ), 'error' );
		}
	}
}

/**
 * Print to error log.
 * Helper function for debugging
 */
function write_log ( $log )  {
      if ( is_array( $log ) || is_object( $log ) ) {
         error_log( print_r( $log, true ) );
      } else {
         error_log( $log );
      }
}

/**
 * Check signature of request coming from Intrum.
 */
function check_signature_after_payment( WP_REST_Request $request ) {
	//check signature here
	$secret = WC_Intrum_Gateway::get_instance()->get_option('password');
	$algorithm = parse_signature_algorithm($request['SignatureMethod']);

	if (empty($algorithm)) {
		return false; // cannot even check signature
	}

	$fields = array(
		'OrderNumber',
		'InvoiceReference',
		'InstallmentCount',
		'PayerName',
		'PayerExtraAddressRow',
		'PayerStreetAddress',
		'PayerCity',
		'PayerZipCode',
		'PayerCountryCode',
		'Version',
		'ErrorCode',
		'ErrorMessage',
		'SignatureMethod',
	);

	$sig_str = "";
	foreach($fields as $field) {
		$val = $request[$field];
		// PHP treats 'empty(0)' as true, therefore also check if value is numeric
		if(!empty($val) || is_numeric($val)) {
			$sig_str .= "$val&";
		}
	}

	for ($i = 1; $i <= $request['InstallmentCount']; $i++) {
		$sig_str .= "{$request["InstallmentDueDate$i"]}&" .
		"{$request["InstallmentAmount$i"]}&";
	}
	$sig_str .= $secret;

	$signature = hash($algorithm, str_replace(' ', '', $sig_str));
	return $request['Signature'] === $signature;
}

/**
 * Checks signature and if success, changes order status
 * according https://docs.woocommerce.com/document/managing-orders/
 */
function payment_return_route( WP_REST_Request $request ) {
	$valid_request = check_signature_after_payment($request);

	write_log($request);
	$order_id = $request->get_param("order-id");

	$order = new WC_Order($order_id);
	if($valid_request) {
		$status = $request->get_param("status");
		$redirect_url = "";

		if($status === "success" && $request['ErrorCode'] == 0) {
			$redirect_url = $order->get_checkout_order_received_url();
			$order->payment_complete();
		} else if($status === "cancel" && $request['ErrorCode'] == 4) {
			// when user goes to cancel url, order will be cancelled
			$redirect_url = $order->get_cancel_order_url_raw();
		} else {
			// when user goes to cancel url, order will be cancelled
			$redirect_url = $order->get_cancel_order_url_raw();
			//wc_add_notice( __( 'An error occurred while processing your payment.', 'intrum_wc_gateway' ), 'error' );
			write_log("[INTRUM WC] Error: {$request['ErrorMessage']}");
		}
		error_log("REDIRECT TO: " . $redirect_url);
		wp_redirect($redirect_url);
		exit;
	} else {
		write_log('[INTURM WC] ERROR: Signatures did not match');
		$redirect_url = $order->get_cancel_order_url_raw();
		//wc_add_notice( __( 'The communication with the payment service failed', 'intrum_wc_gateway' ), 'error' );
		wp_redirect($redirect_url);
		exit;
	}
}

function create_return_url($status, $order_id) {
	if (strpos(get_site_url(), "localhost") !== false || strpos(get_site_url(), "127.0.0.1") !== false) {
		$url = get_site_url() . "/wp-json/intrum-woocommerce-gateway/v1/payment?status=$status&order-id=$order_id";
	} else {
		//get_site_url might return site.com/path but we want only the domain site.com
		$parsed_url = parse_url(get_site_url()); //PHP built-in function
		$base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
		$url = $base_url . "/wp-json/intrum-woocommerce-gateway/v1/payment?status=$status&order-id=$order_id";
	}
	return $url;
}

/**
 * Remove hyphens so 'hash' function recognizes it.
 * Example SHA-512 -> SHA512
 */
function parse_signature_algorithm($str) {
	return str_replace('-', '', $str);
}

/**
 * Helper function to check WooCommerce version.
 * Default version for comparisoin is '3.0'.
 */
function version_check( $version = '3.0' ) {
	if ( class_exists( 'WooCommerce' ) ) {
		global $woocommerce;
		if ( version_compare( $woocommerce->version, $version, ">=" ) ) {
			return true;
		}
	}
	return false;
}

/**
* https://fi.wikipedia.org/wiki/Henkil%C3%B6tunnus
*/
function person_id_ok($person_id) {
	if (strlen($person_id) != 11) return false;
	if (!is_numeric(substr($person_id, 0, 6))) return false;
	if (substr($person_id, 0, 2) > 31) return false;
	if (substr($person_id, 2, 2) > 12) return false;
	if (!in_array(substr($person_id, 6, 1), array('+','-','A'))) return false;
	$modulo = (substr($person_id, 0, 6) . substr($person_id, 7, 3)) % 31;
	$check_char = substr($person_id, 10, 1);
	$check_chars =array(
		0,1,2,3,4,5,6,7,8,9,'A','B','C','D','E','F',
		'H','J','K','L','M','N','P','R','S','T','U','V','W','X','Y'
	);
	return $check_chars[$modulo] == $check_char;
}

function str_utf8_decode($str) {
  $utf8_ansi2 = array(
  "%u00c0" =>"À",
  "%u00c1" =>"Á",
  "%u00c2" =>"Â",
  "%u00c3" =>"Ã",
  "%u00c4" =>"Ä",
  "%u00c5" =>"Å",
  "%u00c6" =>"Æ",
  "%u00c7" =>"Ç",
  "%u00c8" =>"È",
  "%u00c9" =>"É",
  "%u00ca" =>"Ê",
  "%u00cb" =>"Ë",
  "%u00cc" =>"Ì",
  "%u00cd" =>"Í",
  "%u00ce" =>"Î",
  "%u00cf" =>"Ï",
  "%u00d1" =>"Ñ",
  "%u00d2" =>"Ò",
  "%u00d3" =>"Ó",
  "%u00d4" =>"Ô",
  "%u00d5" =>"Õ",
  "%u00d6" =>"Ö",
  "%u00d8" =>"Ø",
  "%u00d9" =>"Ù",
  "%u00da" =>"Ú",
  "%u00db" =>"Û",
  "%u00dc" =>"Ü",
  "%u00dd" =>"Ý",
  "%u00df" =>"ß",
  "%u00e0" =>"à",
  "%u00e1" =>"á",
  "%u00e2" =>"â",
  "%u00e3" =>"ã",
  "%u00e4" =>"ä",
  "%u00e5" =>"å",
  "%u00e6" =>"æ",
  "%u00e7" =>"ç",
  "%u00e8" =>"è",
  "%u00e9" =>"é",
  "%u00ea" =>"ê",
  "%u00eb" =>"ë",
  "%u00ec" =>"ì",
  "%u00ed" =>"í",
  "%u00ee" =>"î",
  "%u00ef" =>"ï",
  "%u00f0" =>"ð",
  "%u00f1" =>"ñ",
  "%u00f2" =>"ò",
  "%u00f3" =>"ó",
  "%u00f4" =>"ô",
  "%u00f5" =>"õ",
  "%u00f6" =>"ö",
  "%u00f8" =>"ø",
  "%u00f9" =>"ù",
  "%u00fa" =>"ú",
  "%u00fb" =>"û",
  "%u00fc" =>"ü",
  "%u00fd" =>"ý",
  "%u00ff" =>"ÿ");

  $utf8_len = 6; // UTF-8 chars like %u00e4 have length of six
  if(strlen($str) < $utf8_len) {
	return $str;
  }
  $splitted = preg_split("/[%]...../", $str); // Jyv%u00e4skyl%u00e4
  if(count($splitted) == 1) { // Contains no utf-8 encoded substrings
  	return $str;
  }
  $i = 0;
  $new_str = "";
  foreach ($splitted as $split) {
	$new_str .= $split;
	$i += strlen($split);
	$utf8_char = substr($str, $i, $utf8_len);
	$new_str .= $utf8_ansi2[$utf8_char];
	$i += $utf8_len;
  }
  return $new_str;
}
?>
