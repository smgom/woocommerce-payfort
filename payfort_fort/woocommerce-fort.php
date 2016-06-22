<?php
/*
Plugin Name: Payfort (FORT)
Description: Payfort makes it really easy to start accepting online payments (credit &amp; debit cards) in the Middle East. Sign up is instant, at https://www.payfort.com/
Version: 0.1.1
Plugin URI: https://www.payfort.com
Author: Payfort
Author URI: https://www.payfort.com
License: Under GPL2
 */
if ( ! defined( 'ABSPATH' ) ) exit;    

/* Enable automatic updates to this plugin
----------------------------------------------------------- */
//add_filter('auto_update_plugin', '__return_true');
/* Add a custom payment class to WC
------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_fort', 0);

function woocommerce_fort(){
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class is not available, do nothing
    if(class_exists('WC_Gateway_Payfort'))
        return;
    class WC_Gateway_Payfort extends WC_Payment_Gateway{
        private $_gatewayHost        = 'https://checkout.payfort.com/';
        private $_gatewaySandboxHost = 'https://sbcheckout.payfort.com/';
        
        public function __construct(){
            $plugin_dir = plugin_dir_url(__FILE__);
            global $woocommerce;
            
            $this->load_plugin_textdomain();
            
            $this->id = 'payfort';
            $this->icon = apply_filters('woocommerce_FORT_icon', ''.$plugin_dir.'assets/images/cards.png');
            $this->has_fields = true;
            // Load the settings
            $this->init_form_fields();
            $this->init_settings();
            // Define user set variables
            $this->title = "Credit / Debit Card";
            $this->description = $this->get_option('description');
            $this->request_sha = $this->get_option('request_sha');
            $this->response_sha = $this->get_option('response_sha');
            $this->sandbox_mode = $this->get_option('sandbox_mode');
            $this->merchant_identifier = $this->get_option('merchant_identifier');
            $this->command = $this->get_option('command');
            $this->access_code = $this->get_option('access_code');
            $this->language = $this->get_option('language');
            $this->hash_algorithm = $this->get_option('hash_algorithm');
            $this->enable_sadad = $this->get_option('enable_sadad');
            $this->enable_naps = $this->get_option('enable_naps');
            $this->enable_credit_card = $this->get_option('enable_credit_card');
            $this->integration_type = $this->get_option('integration_type');
            
            // Logs
            if ($this->sandbox_mode == 'yes'){
                //$this->log = $woocommerce->logger();
            }
            // Actions
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
            // Save options
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            if (!$this->is_valid_for_use()){
                $this->enabled = false;
            }
            
            //add_action('woocommerce_api_'.strtolower(get_class($this).'_process_response'), array(&$this, 'process_response'));
            //add_action('woocommerce_api_'.strtolower(get_class($this).'_merchantPageResponse'), array(&$this, 'merchantPageResponse'));
            //add_action('woocommerce_api_'.strtolower(get_class($this).'_merchantPageCancel'), array(&$this, 'merchantPageCancel'));
            
            add_action('woocommerce_wc_gateway_payfort_process_response', array(&$this, 'process_response'));
            add_action('woocommerce_wc_gateway_payfort_merchantpageresponse', array(&$this, 'merchantPageResponse'));
            add_action('woocommerce_wc_gateway_payfort_merchantpagecancel', array(&$this, 'merchantPageCancel'));
            
        }
        
        function payment_scripts() {
            global $woocommerce;
            if ( ! is_checkout() ) {
                return;
            }
            
            wp_enqueue_script( 'fortjs-config',  plugins_url('payfort_fort/assets/js/config.js'), array(), WC_VERSION, true );
            wp_enqueue_script( 'fortjs-checkout',  plugins_url('payfort_fort/assets/js/checkout.js'), array(), WC_VERSION, true );
            if($this->integration_type == 'merchantPage') {
                wp_enqueue_style( 'font-awesome', 'https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css' );
                wp_enqueue_style( 'fortcss-checkout', plugins_url('payfort_fort/assets/css/checkout.css') );
            }
        }
        /**
         * Check if this gateway is enabled and available in the user's currency
         *
         * @access public
         * @return bool
         */
        function is_valid_for_use() {
            // Skip currency check
            return true;
        }
        /**
         * Admin Panel Options
         * - Options for bits like 'api keys' and availability on a country-by-country basis
         *
         * @since 1.0.0
         */
        public function admin_options() {
?>
        <h3><?php _e( 'Payfort FORT', 'payfort_fort' ); ?></h3>
        <p><?php _e( 'Please fill in the below section to start accepting payments on your site! You can find all the required information in your <a href="https://fort.payfort.com/" target="_blank">Payfort FORT Dashboard</a>.', 'payfort_fort' ); ?></p>

        <?php if ( $this->is_valid_for_use() ) : ?>

        <table class="form-table">
<?php
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
?>
        <script>
            jQuery(document).ready(function(){
                jQuery('[name=save]').click(function(){
                    if (!jQuery('#woocommerce_payfort_enable_credit_card').is(':checked') && !jQuery('#woocommerce_payfort_enable_sadad').is(':checked') && !jQuery('#woocommerce_payfort_enable_naps').is(':checked')){
                        alert('Please enable at least 1 payment method!');
                        return false;
                    }
                })
            });
        </script>
        <tr valign="top">
			<th class="titledesc" scope="row">
				<label for="woocommerce_fort_host_to_host_url">Host to Host URL</label>
				<!--<img width="16" height="16" src="http://localhost/wordpress/wp-content/plugins/woocommerce/assets/images/help.png" class="help_tip">-->
            </th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span>Host to Host URL</span></legend>
					<input type="text" readonly="readonly" placeholder="" value="<?php echo get_site_url().'?wc-api=wc_gateway_payfort_process_response';?>" style="" id="woocommerce_fort_host_to_host_url" name="woocommerce_fort_host_to_host_url" class="input-text regular-input ">
				</fieldset>
			</td>
		</tr>
        </table><!--/.form-table-->
        <?php else : ?>
        <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'payfort_fort' ); ?></strong>: <?php _e( 'Payfort FORT does not support your store currency at this time.', 'payfort_fort' ); ?></p></div>
<?php
endif;
        }
        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         */
        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'payfort_fort' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable the FORT gateway', 'payfort_fort' ),
                    'default' => 'yes'
                ),
                'description' => array(
                    'title' => __( 'Description', 'payfort_fort' ),
                    'type' => 'text',
                    'description' => __( 'This is the description the user sees during checkout.', 'payfort_fort' ),
                    'default' => __( 'Pay for your items with any Credit or Debit Card', 'payfort_fort' )
                ),
                'language' => array(
                    'title' => __( 'Language', 'payfort_fort' ),
                    'type'      => 'select',
                    'options'     => array(
                        'en' => __('English (en)', 'payfort_fort' ),
                        'ar' => __('Arabic (ar)', 'payfort_fort' )
                    ),
                    'description' => __( 'The language of the payment page.', 'payfort_fort' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder' => ''
                ),
                'merchant_identifier' => array(
                    'title' => __( 'Merchant Identifier', 'payfort_fort' ),
                    'type'      => 'text',
                    'description' => __( 'Your MID, you can find in your FORT account security settings.', 'payfort_fort' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder' => ''
                ),
                'access_code' => array(
                    'title' => __( 'Access Code', 'payfort_fort' ),
                    'type'      => 'text',
                    'description' => __( 'Your access code, you can find in your FORT account security settings.', 'payfort_fort' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder' => ''
                ),
                'command' => array(
                    'title' => __( 'Command', 'payfort_fort' ),
                    'type'      => 'select',
                    'options'     => array(
                        'AUTHORIZATION' => __('AUTHORIZATION', 'payfort_fort' ),
                        'PURCHASE' => __('PURCHASE', 'payfort_fort' )
                    ),
                    'description' => __( 'Order operation to be used in the payment page.', 'payfort_fort' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder' => ''
                ),
                'hash_algorithm' => array(
                    'title' => __( 'SHA Algorithm', 'payfort_fort' ),
                    'type'      => 'select',
                    'options'     => array(
                        'SHA1' => __('SHA1', 'payfort_fort' ),
                        'SHA256' => __('SHA-256', 'payfort_fort' ),
                        'SHA512' => __('SHA-512', 'payfort_fort' )
                    ),
                    'description' => __( 'The hash algorithm used for the signature', 'payfort_fort' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder' => ''
                ),
                'request_sha' => array(
                    'title' => __( 'Request SHA phrase', 'payfort_fort' ),
                    'type'      => 'text',
                    'description' => __( 'Your request SHA phrase, you can find in your FORT account security settings.', 'payfort_fort' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder' => ''
                ),
                'response_sha' => array(
                    'title' => __( 'Response SHA phrase', 'payfort_fort' ),
                    'type'      => 'text',
                    'description' => __( 'Your response SHA phrase, you can find in your FORT account security settings.', 'payfort_fort' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder' => ''
                ),
                'enable_credit_card' => array(
                    'title' => __( 'Credit \ Debit Card', 'payfort_fort' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable Credit \ Debit Card Payment Method', 'payfort_fort' ),
                    'default' => 'yes'
                ),
                'integration_type' => array(
                    'title' => __( 'Integration Type', 'payfort_fort' ),
                    'type'      => 'select',
                    'options'     => array(
                        'redirection' => __('Redirection', 'payfort_fort' ),
                        'merchantPage' => __('Merchant Page', 'payfort_fort' ),
                    ),
                    'description' => __( 'Credit \ Debit Card Integration Type', 'payfort_fort' ),
                    'default'     => 'redirection',
                    'desc_tip'    => true,
                    'placeholder' => __( 'Integration Type', 'payfort_fort' )
                ),
                'enable_sadad' => array(
                    'title' => __( 'SADAD', 'payfort_fort' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable SADAD Payment Method', 'payfort_fort' ),
                    'default' => 'no'
                ),
                'enable_naps' => array(
                    'title' => __( 'NAPS', 'payfort_fort' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable NAPS Payment Method', 'payfort_fort' ),
                    'default' => 'no'
                ),
                'sandbox_mode' => array(
                    'title' => __( 'Sandbox mode', 'payfort_fort' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable Sandbox mode', 'payfort_fort' ),
                    'default' => 'no'
                )
            );
        }
        
        public function process_response(){
            global $woocommerce;
            $fortParams = array_merge($_GET,$_POST);
            if (isset($fortParams['response_code']) && isset($fortParams['merchant_reference'])){
                $order = new WC_Order($fortParams['merchant_reference']);
                $success = false;
                $params = $fortParams;
                $signature = $fortParams['signature'];
                $response_integration_type = isset($fortParams['integration_type']) ? $fortParams['integration_type'] : '';
                unset($params['signature']);
                unset($params['wc-ajax']);
                unset($params['payfort_fort']);
                unset($params['wc-api']);
                unset($params['integration_type']);
                
                $trueSignature = $this->_calculateSignature($params, 'response');
                
                if ($trueSignature != $signature){
                    //$message = __('Error: ', 'payfort_fort') . 'invalid signature';
                    $message = __('Error: ', 'payfort_fort') . __( 'Could not complete order, please check your payment details and try again.', 'payfort_fort' );

                }
                else{
                    $response_code      = $params['response_code'];
                    $response_message   = $params['response_message'];
                    $status             = $params['status'];
                    
                    if (substr($response_code, 2) != '000'){
                        $message = __('Error: ', 'payfort_fort') . __( 'Could not complete order, please check your payment details and try again.', 'payfort_fort' );
                        //$message.= '<br/>Message: '.$response_message;
                    }
                    else{
                        $success = true;
                        $order->payment_complete();
                        //$order->update_status('processing');
                        // return array(
                            // 'result' => 'success',
                            // 'redirect' => $this->get_return_url( $order )
                        // );
                        WC()->session->set( 'refresh_totals', true );
                        echo '<script>window.top.location.href = "'.  $this->get_return_url( $order ) .'"</script>';
                        exit;
                        
                    }
                }
                if (!$success){
                    $order->cancel_order();
                    //$order->update_status('cancelled');
                    if(function_exists("wc_add_notice")) {
                        // Use the new version of the add_error method
                        wc_add_notice($message, 'error');
                    } else {
                        // Use the old version
                        $woocommerce->add_error($message);
                    }
                    echo '<script>window.top.location.href = "'.  esc_url($woocommerce->cart->get_checkout_url()) .'"</script>';
                    exit; 
                }
                
            }    
        }
        
        function merchantPageResponse() {
            global $woocommerce;
            $fortParams = array_merge($_GET,$_POST);
            if (isset($fortParams['response_code'])  && isset($fortParams['merchant_reference'])){
                $order = new WC_Order($fortParams['merchant_reference']);
                $success = false;
                $params = $fortParams;
                $signature = $fortParams['signature'];

                unset($params['signature']);
                unset($params['wc-ajax']);
                unset($params['target']);
                unset($params['payfort_fort']);
                unset($params['wc-api']);
                
                $trueSignature = $this->_calculateSignature($params, 'response');
                
                $success = TRUE;
                if ($trueSignature != $signature){
                    //$message = __('Error: ', 'payfort_fort') . 'invalid signature';
                    $message = __('Error: ', 'payfort_fort') . __( 'Could not complete order, please check your payment details and try again.', 'payfort_fort' );
                    $success = FALSE;
                }
                else{
                    
                    $response_code      = $params['response_code'];
                    $response_message   = $params['response_message'];
                    $status             = $params['status'];
                    
                    if (substr($response_code, 2) != '000'){
                        $success = FALSE;
                        $message = __('Error: ', 'payfort_fort') . __( 'Could not complete order, please check your payment details and try again.', 'payfort_fort' );
                        //$message.= '<br/>Message: '.$response_message;
                    }
                    else{
                        $success = true;
                        $host2HostParams = $this->_merchantPageNotifyFort($fortParams);
                        if(!$host2HostParams) {
                            $message = __('Error: ', 'payfort_fort') . __( 'Could not complete order, please check your payment details and try again.', 'payfort_fort' );
                            $success = false;
                        }
                        else{
                            $params = $host2HostParams;
                            $signature = $host2HostParams['signature'];
                            unset($params['signature']);
                            unset($params['route']);
                            $trueSignature = $this->_calculateSignature($params, 'response');
                            if ($trueSignature != $signature){
                                $message = __('Error: ', 'payfort_fort') . __( 'Could not complete order, please check your payment details and try again.', 'payfort_fort' );
                                $success = false;
                            }
                            else{
                                $response_code      = $host2HostParams['response_code'];
                                if($response_code == '20064' && isset($host2HostParams['3ds_url'])) {
                                    //redirect to 3ds page
                                    $success = true;
                                    header('location:'.$host2HostParams['3ds_url']);
                                    //echo '<script>window.location.href = "'.$host2HostParams['3ds_url'].'"</script>';
                                    exit;
                                }
                                else{
                                    if (substr($response_code, 2) != '000'){
                                        $message = __('Error: ', 'payfort_fort') . __( 'Could not complete order, please check your payment details and try again.', 'payfort_fort' );
                                        $success = false;
                                    }
                                    else {
                                        $success = true;
                                        $order->payment_complete();
                                        
                                        //$order->update_status('processing');
                                        WC()->session->set( 'refresh_totals', true );
                                        echo '<script>window.top.location.href = "'.$this->get_return_url( $order ).'"</script>';
                                        exit;
                                    }
                                }
                            }
                        }
                    }
                }
                
                if (!$success){
                    $order->cancel_order();
                    //$order->update_status('cancelled');
                    if(function_exists("wc_add_notice")) {
                        // Use the new version of the add_error method
                        wc_add_notice($message, 'error');
                    } else {
                        // Use the old version
                        $woocommerce->add_error($message);
                    }
                    echo '<script>window.top.location.href = "'.  esc_url($woocommerce->cart->get_checkout_url()) .'"</script>';
                    exit;
                }
            }
        }
        
        private function _merchantPageNotifyFort($fortParams) {
            //send host to host
            $order_id = $fortParams['merchant_reference'];
            $order = new WC_Order($fortParams['merchant_reference']);
            
            $postData = array(
                'merchant_reference'    => $fortParams['merchant_reference'],
                'access_code'           => $this->access_code,
                'command'               => $this->command,
                'merchant_identifier'   => $this->merchant_identifier,
                'customer_ip'           => WC_Geolocation::get_ip_address(),
                'amount'                => $this->_convertFortAmount($order->get_total(), 1, get_woocommerce_currency()),
                'currency'              => strtoupper(get_woocommerce_currency()),
                'customer_email'        => $order->billing_email,
                'customer_name'         => trim($order->billing_first_name.' '.$order->billing_last_name),
                'token_name'            => $fortParams['token_name'],
                'language'              => $this->language,
                'return_url'            => get_site_url().'?wc-api=wc_gateway_payfort_process_response&integration_type=merchantPage',
            );
            //calculate request signature
            $signature = $this->_calculateSignature($postData, 'request');
            $postData['signature'] = $signature;

            $gatewayUrl = $this->sandbox_mode == "yes" ? $this->_gatewaySandboxHost.'FortAPI/paymentApi' : $this->_gatewayHost.'FortAPI/paymentApi';

            //open connection
            $ch = curl_init();

            //set the url, number of POST vars, POST data
            $useragent = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:20.0) Gecko/20100101 Firefox/20.0";
            curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json;charset=UTF-8',
                    //'Accept: application/json, application/*+json',
                    //'Connection:keep-alive'
            ));
            curl_setopt($ch, CURLOPT_URL, $gatewayUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_FAILONERROR, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_ENCODING, "compress, gzip");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // allow redirects		
            //curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return into a variable
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0); // The number of seconds to wait while trying to connect
            //curl_setopt($ch, CURLOPT_TIMEOUT, Yii::app()->params['apiCallTimeout']); // timeout in seconds
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

            $response = curl_exec($ch);

            $response_data = array();

            //parse_str($response, $response_data);
            curl_close($ch);

            $array_result    = json_decode($response, true);

            if(!$response || empty($array_result)) {
                return false;
            }
            return $array_result;
        }

        /**
         * Generate the credit card payment form
         *
         * @access public
         * @param none
         * @return string
         */
        function payment_fields() {
            // Access the global object
            echo "<input type='hidden' id='payfort_integration_type' value='".$this->integration_type."'>";
            echo "<input type='hidden' id='payfort_cancel_url' value='".get_site_url().'?wc-api=wc_gateway_payfort_merchantpagecancel'."'>";
            
            if ($this->enable_sadad == "yes"){
                echo preg_replace('/^\s+|\n|\r|\s+$/m', '','<script>
                        jQuery(".payment_method_payfort").eq(0).after(\'
                        <li class="payment_method_payfort">
                            <input id="payment_method_payfort" data-method="SADAD" type="radio" class="input-radio" name="payment_method" value="payfort" data-order_button_text="">
                            <label onclick="setTimeout(function(){jQuery(\\\'[data-method=SADAD]\\\').click().focus();},100)" for="payment_method_payfort">
                                '.__( 'SADAD', 'payfort_fort').' <img src="'. get_site_url(). '/wp-content/plugins/payfort_fort/assets/images/SADAD-logo.png" alt="SADAD">
                            </label>
                            <div class="payment_box payment_method_payfort">
                                <p>'.__( 'Pay for your items with using SADAD payment method', 'payfort_fort').'</p>
                            </div>
                        </li>\');
                    </script>');
            }

            if ($this->enable_naps == "yes"){
                echo preg_replace('/^\s+|\n|\r|\s+$/m', '','<script>
                        jQuery(".payment_method_payfort").eq(0).after(\'\
                        <li class="payment_method_payfort_naps">\
                            <input id="payment_method_payfort" data-method="NAPS" type="radio" class="input-radio" name="payment_method" value="payfort" data-order_button_text="">\
                            <label onclick="setTimeout(function(){jQuery(\\\'[data-method=NAPS]\\\').click().focus();},100)" for="payment_method_payfort">\
                                '.__( 'NAPS', 'payfort_fort').' <img src="'. get_site_url(). '/wp-content/plugins/payfort_fort/assets/images/qpay-logo.png" alt="NAPS">\
                            </label>\
                            <div class="payment_box payment_method_payfort">\
                                <p>'.__( 'Pay for your items with using NAPS payment method', 'payfort_fort').'</p>\
                            </div>\
                        </li>\');
                    </script>');
            }
            if($this->integration_type == 'merchantPage') {
                echo preg_replace('/^\s+|\n|\r|\s+$/m', '','<script>
                        jQuery(".payment_method_payfort").eq(0).after(\'\
                        <div class="pf-iframe-background" id="div-pf-iframe" style="display:none">
                            <div class="pf-iframe-container">
                                <span class="pf-close-container">
                                    <i class="fa fa-times-circle pf-iframe-close" onclick="pfClosePopup()"></i>
                                </span>
                                <i class="fa fa-spinner fa-spin pf-iframe-spin"></i>
                                <div class="pf-iframe" id="pf_iframe_content"></div>
                            </div>
                        </div>\');
                    </script>');
            }
            if ($this->enable_credit_card != "yes"){
                echo preg_replace('/^\s+|\n|\r|\s+$/m', '','<script>
                        jQuery(".payment_method_payfort").eq(0).remove();
                    </script>');
            }
            if ($this->description) {
                echo "<p>".$this->description."</p>";
            }
            
        }
        
        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        function process_payment( $order_id ) {
            global $woocommerce;
            $order = new WC_Order($order_id);
            
            if (!isset($_GET['response_code'])){
                $postData = array();
                $gatewayUrl = '#';
                $isSADAD = isset($_POST['SADAD']) ? $_POST['SADAD'] : false;
                $isNaps = isset($_POST['NAPS']) ? $_POST['NAPS'] : false;
                if($isSADAD == 'true' || $isNaps == 'true' || $this->integration_type == 'redirection') {
                    $postData = array(
                        'amount'                => $this->_convertFortAmount($order->get_total(), 1, get_woocommerce_currency()),
                        'currency'              => strtoupper(get_woocommerce_currency()),
                        'merchant_identifier'   => $this->merchant_identifier,
                        'access_code'           => $this->access_code,
                        'merchant_reference'    => $order_id,
                        'customer_email'        => $order->billing_email,
                        'command'               => $this->command,
                        'language'              => $this->language,
                        'return_url'            => get_site_url().'?wc-api=wc_gateway_payfort_process_response',
                    );
                    if ($isSADAD == "true"){
                        $postData['payment_option'] = 'SADAD';
                        update_post_meta($order->id, '_payment_method_title', 'SADAD');
                        update_post_meta($order->id, '_payment_method', 'SADAD');
                    }
                    else if ($isNaps == "true"){
                        $postData['payment_option'] = 'NAPS';
                        $postData['order_description'] = $order_id;;
                        update_post_meta($order->id, '_payment_method_title', 'NAPS');
                        update_post_meta($order->id, '_payment_method', 'NAPS');
                    }
                    //calculate request signature
                    $signature = $this->_calculateSignature($postData, 'request');
                    $postData['signature'] = $signature;
                    
                    $gatewayUrl = $this->sandbox_mode == "yes" ? $this->_gatewaySandboxHost.'FortAPI/paymentPage' : $this->_gatewayHost.'FortAPI/paymentPage';
                }
                elseif($this->integration_type == 'merchantPage'){
                    $merchantPageData = $this->_getMerchantPageData($order);
                    $postData = $merchantPageData['params'];
                    $gatewayUrl = $merchantPageData['url'];
                }
                
                
                $form =  '<form style="display:none" name="payfort_payment_form" id="payfort_payment_form" method="post" action="'.$gatewayUrl.'">';
                
                foreach ($postData as $k => $v){
                    $form .= '<input type="hidden" name="'.$k.'" value="'.$v.'">';
                }
                
                $form .= '<input type="submit" value="" id="payfort_payment_form_submit" name="submit2">';
                $result = array('result' => 'success', 'form' => $form);
                if ( isset( $_POST['woocommerce_pay'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'woocommerce-pay' ) ) {
                    wp_send_json( $result );
                    exit;
                }
                else{
                   return $result; 
                }
                
            }
            
        }
        
        function merchantPageCancel() {
            global $woocommerce;
            
            $order_id = WC()->session->get('order_awaiting_payment');
            if(!empty($order_id)) {
                $order = new WC_Order($order_id);
                $order->cancel_order();
                //$order->update_status('cancelled');
            }
            $message = __('Error: ', 'payfort_fort') . __( 'you have canceled the payment, please try agian.', 'payfort_fort');
            if(function_exists("wc_add_notice")) {
                // Use the new version of the add_error method
                wc_add_notice($message, 'error');
            } else {
                // Use the old version
                $woocommerce->add_error($message);
            }
            echo '<script>window.top.location.href = "'.  esc_url($woocommerce->cart->get_checkout_url()) .'"</script>';
            exit;
        }
        
        /**
         * calculate fort signature
         * @param array $arr_data
         * @param sting $sign_type request or response
         * @return string fort signature
         */
        private function _calculateSignature($arr_data, $sign_type = 'request') {

            $shaString = '';

            ksort($arr_data);
            foreach ($arr_data as $k=>$v){
                $shaString .= "$k=$v";
            }
            
            if($sign_type == 'request') {
                $shaString = $this->request_sha . $shaString . $this->request_sha;
            }
            else{
                $shaString = $this->response_sha . $shaString . $this->response_sha;
            }
            $signature = hash($this->hash_algorithm ,$shaString);

            return $signature;
        }
        
        /**
         * Convert Amount with dicemal points
         * @param decimal $amount
         * @param decimal $currency_value
         * @param string  $currency_code
         * @return decimal
         */
        private function _convertFortAmount($amount, $currency_value, $currency_code) {
            $new_amount = 0;
            //$decimal_points = get_option( 'woocommerce_price_num_decimals' );
            $decimal_points = $this->_getCurrencyDecimalPoint($currency_code);
            $new_amount = round($amount * $currency_value, $decimal_points) * (pow(10, $decimal_points));
            return $new_amount;
        }
        
        /**
         * 
         * @param string $currency
         * @param integer 
         */
        private function _getCurrencyDecimalPoint($currency)
        {
            $decimalPoint  = 2;
            $arrCurrencies = array(
                'JOD' => 3,
                'KWD' => 3,
                'OMR' => 3,
                'TND' => 3,
                'BHD' => 3,
                'LYD' => 3,
                'IQD' => 3,
            );
            if (isset($arrCurrencies[$currency])) {
                $decimalPoint = $arrCurrencies[$currency];
            }
            return $decimalPoint;
        }
        
        private function _isMerchantPageMethod() {
            if($this->integration_type == 'merchantPage') {
                return true;
            }
            return false;
        }
        
        private function _getMerchantPageData($order) {
            $order_id = $order->id;

            $iframe_params = array(
                'merchant_identifier'   => $this->merchant_identifier,
                'access_code'           => $this->access_code,
                'merchant_reference'    => $order_id,
                'service_command'       => 'TOKENIZATION',
                'language'              => $this->language,
                'return_url'            => get_site_url().'?wc-api=wc_gateway_payfort_merchantpageresponse',//get_site_url() . '/checkout/merchantPageResponse',
            );

            //calculate request signature
            $signature = $this->_calculateSignature($iframe_params, 'request');
            $iframe_params['signature'] = $signature;

            $gatewayUrl = $this->sandbox_mode == "yes" ? $this->_gatewaySandboxHost.'FortAPI/paymentPage' : $this->_gatewayHost.'FortAPI/paymentPage';
            
            return array('url' => $gatewayUrl, 'params' => $iframe_params);
        }

        /**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
	 *
	 * Locales found in:
	 *      - WP_LANG_DIR/payfort_fort/payfort_fort-LOCALE.mo
	 *      - WP_LANG_DIR/plugins/payfort_fort-LOCALE.mo
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'payfort_fort' );
                
                load_textdomain( 'payfort_fort', dirname( __FILE__ ) . '/languages/payfort_fort-' . $locale . '.mo' );
		load_plugin_textdomain( 'payfort_fort', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
	}
    }
    /**
     * Add the gateway to WooCommerce
     **/
    function add_payfort_gateway($methods){
        $methods[] = 'WC_Gateway_Payfort';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'add_payfort_gateway');
    
    function woocommerce_payfort_fort_actions() {
	if ( ! empty( $_GET['wc-api'] ) ) {
            if($_GET['wc-api'] == 'wc_gateway_payfort_process_response') {
                WC()->payment_gateways();
                do_action( 'woocommerce_wc_gateway_payfort_process_response' );
            }
            if($_GET['wc-api'] == 'wc_gateway_payfort_merchantpageresponse') {
                WC()->payment_gateways();
                do_action( 'woocommerce_wc_gateway_payfort_merchantpageresponse' );
            }
            
            if($_GET['wc-api'] == 'wc_gateway_payfort_merchantpagecancel') {
                WC()->payment_gateways();
                do_action( 'woocommerce_wc_gateway_payfort_merchantpagecancel' );
            }
            
        }
    }
    
    add_action( 'init', 'woocommerce_payfort_fort_actions' );
}
