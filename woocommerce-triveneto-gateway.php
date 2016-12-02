<?php
/*
Plugin Name: WooCommerce Triveneto Bassilichi Gateway
Plugin URI: http://wass.es
Description: Extends WooCommerce with the TrivenetoBassilichi gateway.
Version: 1.0
Author: Edu Wass
Author URI: http://wass.es/
*/
add_action('plugins_loaded', 'woocommerce_gateway_tvb_init', 0);
function woocommerce_gateway_tvb_init() {
  if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
  
  /**
   * Localisation
   */
  load_plugin_textdomain('woocommerce_gateway_tvb', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
  
  // Load main class
  require( plugin_dir_path(__FILE__) . '/classes/WC_Triveneto_Payment_Gateway.php');
  
  /**
  * Add the Gateway to WooCommerce
  **/
  function woocommerce_add_gateway_tvb($methods) {
    $methods[] = 'WC_Triveneto_Payment_Gateway';
    return $methods;
  }

  // Setup the responseURL interface
  add_filter( 'query_vars', 'triveneto_add_query_vars');

  /**
  *   Add the 'triveneto_response_interface' query variable so WordPress
  *   won't remove it.
  */
  function triveneto_add_query_vars($vars){
      $vars[] = "triveneto_response_interface";
      return $vars;
  }

  /**
  *   check for  'triveneto_response_interface' query variable and do what you want if its there
  */
  add_action('template_redirect', 'triveneto_response_interface');

  function triveneto_response_interface($template) {
      global $wp_query;

      // If the 'triveneto_response_interface' query var isn't appended to the URL,
      // don't do anything and return default
      if(!isset( $wp_query->query['triveneto_response_interface'] ))
          return $template;

      // .. otherwise, 
      if($wp_query->query['triveneto_response_interface'] == '1'){

        // Load basics
        require_once('wp/wp-load.php');
        require_once( plugin_dir_path(__FILE__) . '/classes/PgConsTriv.php');

        // Check if we have the $_POST vars
        if(!isset($_POST) || empty($_POST)){
          // if not ... nothing to see here
          header('Location:' . get_home_url() );
        }

        // Log the $_POST vars received
        $postvars = print_r($_POST, true);
        PgConsTriv::triveneto_log('[PostVars] ' . $postvars);

        // Log Errors if any
        if(isset($_POST['Error']) && isset($_POST['ErrorText'])){
          // Get vars
          $Error     = $_POST['Error'];
          $ErrorText = $_POST['ErrorText'];
          // record to log
          PgConsTriv::triveneto_log('Detected error: ' . $Error . ' => ' . $ErrorText);
        }

        // Process the order
        if(isset($_POST['trackid'])){
          // Get vars
          $trackid = intval($_POST['trackid']);
          // Create the Order object
          $order = new WC_Order($trackid);
          // Mark as 'Processing'
          $order->update_status('processing', __( 'Received successful TrivenetoBassilichi payment', 'woocommerce_gateway_tvb' ));
          // log
          PgConsTriv::triveneto_log('Received successful TrivenetoBassilichi payment');
          // Order successful URL
          $url = $order->get_checkout_order_received_url();
          // Command the redirection to the ThankYou page
          echo "REDIRECT=" . $url;
        }
        exit;
      }

      return $template;
  }

  
  add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_tvb' );
} 
