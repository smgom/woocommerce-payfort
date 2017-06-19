<?php
/* Plugin Name: Payfort (FORT)
 * Plugin URI:  https://github.com/payfort/woocommerce-payfort
 * Description: Payfort makes it really easy to start accepting online payments (credit &amp; debit cards) in the Middle East. Sign up is instant, at https://www.payfort.com/
 * Version:     0.1.3
 * Author:      Payfort
 * Author URI:  https://www.payfort.com/
 * License:     Under GPL2
 */
$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if(in_array('woocommerce/woocommerce.php', $active_plugins)){
    
        if ( ! defined( 'PAYFORT_FORT' ) ) {
            define( 'PAYFORT_FORT', true );
        }

        if ( defined( 'PAYFORT_FORT_VERSION' ) ) {
            return;
        }else{
            define( 'PAYFORT_FORT_VERSION', '1.2.0' );
        }

        if ( ! defined( 'PAYFORT_FORT_DIR' ) ) {
            define( 'PAYFORT_FORT_DIR', plugin_dir_path( __FILE__ ) );
        }
        
        if ( ! defined( 'PAYFORT_FORT_URL' ) ) {
            define( 'PAYFORT_FORT_URL', plugin_dir_url( __FILE__ ) );
        }

	add_filter('woocommerce_payment_gateways', 'add_payfort_fort_gateway');
	function add_payfort_fort_gateway( $gateways ){
		$gateways[] = 'WC_Gateway_Payfort';
		return $gateways; 
	}

	add_action('plugins_loaded', 'init_payfort_fort_payment_gateway');
	function init_payfort_fort_payment_gateway(){
		require 'classes/class-woocommerce-fort.php';
	}

	add_action( 'plugins_loaded', 'payfort_fort_load_plugin_textdomain' );
	function payfort_fort_load_plugin_textdomain() {
	  load_plugin_textdomain( 'woocommerce-other-payment-gateway', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

        
       function woocommerce_payfort_fort_actions() {
           if ( ! empty( $_GET['wc-api'] ) ) {
               if($_GET['wc-api'] == 'wc_gateway_payfort_process_response') {
                   WC()->payment_gateways();
                   do_action( 'woocommerce_wc_gateway_payfort_fort_process_response' );
               }
               if($_GET['wc-api'] == 'wc_gateway_payfort_fort_merchantPageResponse') {
                   WC()->payment_gateways();
                   do_action( 'woocommerce_wc_gateway_payfort_fort_merchantPageResponse' );
               }
               
               if($_GET['wc-api'] == 'wc_gateway_payfort_fort_responseOnline') {
                   WC()->payment_gateways();
                   do_action( 'woocommerce_wc_gateway_payfort_fort_responseOnline' );
               }

               if($_GET['wc-api'] == 'wc_gateway_payfort_fort_merchantPageCancel') {
                   WC()->payment_gateways();
                   do_action( 'woocommerce_wc_gateway_payfort_fort_merchantPageCancel' );
               }

           }
       }
       add_action( 'init', 'woocommerce_payfort_fort_actions', 500 );
}