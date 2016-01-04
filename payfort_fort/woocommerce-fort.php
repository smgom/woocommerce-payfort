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
add_filter('auto_update_plugin', '__return_true');
/* Add a custom payment class to WC
------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_fort', 0);
function woocommerce_fort(){
    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class is not available, do nothing
    if(class_exists('WC_Gateway_Payfort'))
        return;
    class WC_Gateway_Payfort extends WC_Payment_Gateway{
        public function __construct(){
            $plugin_dir = plugin_dir_url(__FILE__);
            global $woocommerce;
            $this->id = 'payfort';
            $this->icon = apply_filters('woocommerce_FORT_icon', ''.$plugin_dir.'cards.png');
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
            $this->process_response();
        }
        function payment_scripts() {
            global $woocommerce;
            if ( ! is_checkout() ) {
                return;
            }
            
            wp_enqueue_script( 'fortjs-config',  plugins_url('payfort_fort/assets/js/config.js'), array(), WC_VERSION, true );
            wp_enqueue_script( 'fortjs-checkout',  plugins_url('payfort_fort/assets/js/checkout.js'), array(), WC_VERSION, true );

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
        <h3><?php _e( 'Payfort FORT', 'woocommerce' ); ?></h3>
        <p><?php _e( 'Please fill in the below section to start accepting payments on your site! You can find all the required information in your <a href="https://fort.payfort.com/" target="_blank">Payfort FORT Dashboard</a>.', 'woocommerce' ); ?></p>

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
					<input type="text" readonly="readonly" placeholder="" value="<?php echo get_site_url() . '/index.php?payfort_fort=woocommerce-fort&h2h=1';?>" style="" id="woocommerce_fort_host_to_host_url" name="woocommerce_fort_host_to_host_url" class="input-text regular-input ">
				</fieldset>
			</td>
		</tr>
        </table><!--/.form-table-->
        <?php else : ?>
        <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce' ); ?></strong>: <?php _e( 'Payfort FORT does not support your store currency at this time.', 'woocommerce' ); ?></p></div>
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
                    'title' => __( 'Enable/Disable', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable the FORT gateway', 'woocommerce' ),
                    'default' => 'yes'
                ),
                'description' => array(
                    'title' => __( 'Description', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'This is the description the user sees during checkout.', 'woocommerce' ),
                    'default' => __( 'Pay for your items with any Credit or Debit Card', 'woocommerce' )
                ),
                'language' => array(
                    'title' => __( 'Language', 'woocommerce' ),
                    'type'      => 'select',
                    'options'     => array(
                        'en' => __('English (en)', 'woocommerce' ),
                        'ar' => __('Arabic (ar)', 'woocommerce' )
                    ),
                    'description' => __( 'The language of the payment page.', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder' => ''
                ),
                'merchant_identifier' => array(
                    'title' => __( 'Merchant Identifier', 'woocommerce' ),
                    'type'      => 'text',
                    'description' => __( 'Your MID, you can find in your FORT account security settings.', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder' => ''
                ),
                'access_code' => array(
                    'title' => __( 'Access Code', 'woocommerce' ),
                    'type'      => 'text',
                    'description' => __( 'Your access code, you can find in your FORT account security settings.', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder' => ''
                ),
                'command' => array(
                    'title' => __( 'Command', 'woocommerce' ),
                    'type'      => 'select',
                    'options'     => array(
                        'AUTHORIZATION' => __('AUTHORIZATION', 'woocommerce' ),
                        'PURCHASE' => __('PURCHASE', 'woocommerce' )
                    ),
                    'description' => __( 'Order operation to be used in the payment page.', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder' => ''
                ),
                'hash_algorithm' => array(
                    'title' => __( 'SHA Algorithm', 'woocommerce' ),
                    'type'      => 'select',
                    'options'     => array(
                        'SHA1' => __('SHA1', 'woocommerce' ),
                        'SHA256' => __('SHA-256', 'woocommerce' ),
                        'SHA512' => __('SHA-512', 'woocommerce' )
                    ),
                    'description' => __( 'The hash algorithm used for the signature', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder' => ''
                ),
                'request_sha' => array(
                    'title' => __( 'Request SHA phrase', 'woocommerce' ),
                    'type'      => 'text',
                    'description' => __( 'Your request SHA phrase, you can find in your FORT account security settings.', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder' => ''
                ),
                'response_sha' => array(
                    'title' => __( 'Response SHA phrase', 'woocommerce' ),
                    'type'      => 'text',
                    'description' => __( 'Your response SHA phrase, you can find in your FORT account security settings.', 'woocommerce' ),
                    'default' => '',
                    'desc_tip'      => true,
                    'placeholder' => ''
                ),
                'enable_credit_card' => array(
                    'title' => __( 'Credit \ Debit Card', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable Credit \ Debit Card Payment Method', 'woocommerce' ),
                    'default' => 'yes'
                ),
                'enable_sadad' => array(
                    'title' => __( 'SADAD', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable SADAD Payment Method', 'woocommerce' ),
                    'default' => 'no'
                ),
                'enable_naps' => array(
                    'title' => __( 'NAPS', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable NAPS Payment Method', 'woocommerce' ),
                    'default' => 'no'
                ),
                'sandbox_mode' => array(
                    'title' => __( 'Sandbox mode', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable Sandbox mode', 'woocommerce' ),
                    'default' => 'no'
                )
            );
        }
        
        public function process_response(){
            global $woocommerce;
            $fortParams = array_merge($_GET,$_POST);
            if (isset($fortParams['response_code']) && !isset($fortParams['wc-ajax']) && isset($fortParams['merchant_reference'])){
                $order = new WC_Order($fortParams['merchant_reference']);
                $success = false;
                $params = $fortParams;
                $hashString = '';
                $signature = $fortParams['signature'];
                unset($params['signature']);
                unset($params['wc-ajax']);
                unset($params['h2h']);
                unset($params['payfort_fort']);
                ksort($params);
                
                foreach ($params as $k=>$v){
                    if ($v != ''){
                        $hashString .= strtolower($k).'='.$v;
                    }
                }

                $hashString = $this->response_sha . $hashString . $this->response_sha;
                $trueSignature = hash($this->hash_algorithm ,$hashString);
                
                if ($trueSignature != $signature){
                    //$message = __('Error: ', 'woothemes') . 'invalid signature';
                    $message = __('Error: ', 'woothemes') . 'Could not complete order, please check your payment details and try again.';

                }
                else{
                    $response_code      = $params['response_code'];
                    $response_message   = $params['response_message'];
                    $status             = $params['status'];
                    
                    if (substr($response_code, 2) != '000'){
                        $message = __('Error: ', 'woothemes') . 'Could not complete order, please check your payment details and try again.';
                        //$message.= '<br/>Message: '.$response_message;
                    }
                    else{
                        $success = true;
                        //$order->payment_complete();
                        $order->update_status('processing');
                        // return array(
                            // 'result' => 'success',
                            // 'redirect' => $this->get_return_url( $order )
                        // );
                        WC()->session->set( 'refresh_totals', true );
                        header('location:'.$this->get_return_url( $order ));
                    }
                }
                
                if (!$success){
                    $order->update_status('cancelled');
                    if(function_exists("wc_add_notice")) {
                        // Use the new version of the add_error method
                        wc_add_notice($message, 'error');
                    } else {
                        // Use the old version
                        $woocommerce->add_error($message);
                    }
                }
                
            }    
        }
        
        /**
         * Generate the credit card payment form
         *
         * @access public
         * @param none
         * @return string
         */
        function payment_fields() {
            $this->process_response();
            // Access the global object            
            if ($this->enable_sadad == "yes"){
                echo preg_replace('/^\s+|\n|\r|\s+$/m', '','<script>
                        jQuery(".payment_method_payfort").eq(0).after(\'
                        <li class="payment_method_payfort">
                            <input id="payment_method_payfort" data-method="SADAD" type="radio" class="input-radio" name="payment_method" value="payfort" data-order_button_text="">
                            <label onclick="setTimeout(function(){jQuery(\\\'[data-method=SADAD]\\\').click().focus();},100)" for="payment_method_payfort">
                                SADAD <img src="'. get_site_url(). '/wp-content/plugins/payfort_fort/SADAD-logo.png" alt="SADAD">
                            </label>
                            <div class="payment_box payment_method_payfort">
                                <p>Pay for your items with using SADAD payment method</p>
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
                                NAPS <img src="'. get_site_url(). '/wp-content/plugins/payfort_fort/qpay-logo.png" alt="NAPS">\
                            </label>\
                            <div class="payment_box payment_method_payfort">\
                                <p>Pay for your items with using NAPS payment method</p>\
                            </div>\
                        </li>\');
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
                $postData = array(
                    'amount'                => $order->get_total() * 100,
                    'currency'              => strtoupper(get_woocommerce_currency()),
                    'merchant_identifier'   => $this->merchant_identifier,
                    'access_code'           => $this->access_code,
                    'merchant_reference'    => $order_id,
                    'customer_email'        => $order->billing_email,
                    'command'               => $this->command,
                    'language'              => $this->language,
                    'return_url'            => get_site_url() . '/checkout',
                );

                $isSADAD = isset($_POST['SADAD']) ? $_POST['SADAD'] : false;
                $isNaps = isset($_POST['NAPS']) ? $_POST['NAPS'] : false;

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
                $shaString = '';
                ksort($postData);
                foreach ($postData as $k=>$v){
                    $shaString .= "$k=$v";
                }

                $shaString = $this->request_sha . $shaString . $this->request_sha;
                $signature = hash($this->hash_algorithm ,$shaString);
                
                if ($this->sandbox_mode == "yes"){
                    $gatewayUrl = 'https://sbcheckout.payfort.com/FortAPI/paymentPage';
                }
                else{
                    $gatewayUrl = 'https://checkout.payfort.com/FortAPI/paymentPage';
                }
                
                $form =  '<form style="display:none" name="payfortpaymentform" id="payfortpaymentform" method="post" action="'.$gatewayUrl.'">';
                
                foreach ($postData as $k => $v){
                    $form .= '<input type="hidden" name="'.$k.'" value="'.$v.'">';
                }
                
                $form .= '<input type="hidden" name="signature" value="'.$signature.'">';
                $form .= '<input type="submit" value="" id="submit" name="submit2">';
                
                return array('form' => $form, 'result' => 'success');
            }
            
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
    if (isset($_GET['h2h'])){
        $payfort_gateway = new WC_Gateway_Payfort();
        $payfort_gateway->process_response(true);
    }
}
