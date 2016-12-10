<?php

  /*********************************************************************************
   * Project:     WC Triveneto Payment Gateway (Consorzio Triveneto S.p.A.)
   * File:        WC_Triveneto_Payment_Gateway
   * Description: Implementation of the Triveneto PG in WooCommerce
   *
   * This library is free software; you can redistribute it and/or
   * modify it under the terms of the GNU Lesser General Public
   * License as published by the Free Software Foundation.
   *
   * This library is distributed in the hope that it will be useful,
   * but WITHOUT ANY WARRANTY; without even the implied warranty of
   * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
   * Lesser General Public License for more details.
   *
   * @author Edu Wass
   * @version 1.0 (05/08/2015) 
   *
   *********************************************************************************/
  class WC_Triveneto_Payment_Gateway extends WC_Payment_Gateway {
    
    /**
     * Gateway definition
     */
    public function __construct(){

      // Unique ID for your gateway. e.g. ‘your_gateway’
      $this->id = 'woocommerce_gateway_tvb';
      // If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image.
      $this->icon = plugin_dir_url( __FILE__ ) . '../assets/img/credit_card.png';
      // Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration).
      $this->has_fields = false;
      // Title of the payment method shown on the admin page.
      $this->method_title = __( 'TrivenetoBassilichi Gateway', 'woocommerce_gateway_tvb' );
      // Description for the payment method shown on the admin page.
      $this->method_description = __('Configuration parameters of the TrivenetoBassilichi Payment Method', 'woocommerce_gateway_tvb');
      // Add Log Checker in the end of description to check latest 100 lines
      $this->method_description.= $this->log_checker_html(100);
      
      // Load the form fields
      $this->init_form_fields();

      // Load the settings
      $this->init_settings();

      // Update Settings
      $settings_array = array(
        'enabled',
        '_PG_Title',
        '_PG_Description',
        '_PG_System_Environment',
        '_PG_ID_Merchant_Test',
        '_PG_Password_Test',
        '_PG_ID_Merchant_Production',
        '_PG_Password_Production',
        '_PG_CurrencyCode',
        '_PG_Default_LangId',
        '_PG_URL_base',
        '_PG_responseURL',
        '_PG_errorURL',
        '_PG_goodURL',
      );

      // Load all the settings
      foreach($settings_array as $setting){
        // Load the saved option or it's default:
        $this->$setting = $this->get_option($setting, $this->form_fields[$setting]['default']);
      }

      // title and description for the object gets set here
      // because we want them to be translatable:
      $this->title       = __('TrivenetoBassilichi Gateway', 'woocommerce_gateway_tvb');
      $this->description = __('Our trusted payment Gateway', 'woocommerce_gateway_tvb');

      // Hooks
      // (save options to DB)
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      
    }

    /**
     * Gets latest n lines of the log
     * @param  integer $nlines
     * @return string
     */
    public function get_log_lines($nlines){
      $upload_dir = wp_upload_dir();
      $path = $upload_dir['basedir'].'/wc-logs/';
      $filepath = $path.'woocommerce-triveneto-gateway.log';
      $log = '';
      if(file_exists($filepath)) {
        $file = file($filepath);
        for ($i = count($file); $i >= ((count($file)>$nlines)?(count($file)-$nlines):0); $i--) {
          $log.= $file[$i] . "\n";
        }
      } else {
        $log = 'No logged activity yet!';
      }
      return $log;
    }

    /**
     * Renders HTML code for log checker in the WP Backend
     * @param  integer $nlines
     * @return string (HTML)
     */
    public function log_checker_html($nlines){
      $html = '<br>
      <a href="#" class="button-primary" onclick="jQuery(\'#tvblog\').toggle();return false;">Check latest 100 Log lines</a>
      <div id="tvblog" style="padding:30px;background-color:#000;color:#fff;border:1px solid #fff;display:none;height:200px;overflow-y:scroll;">
        <pre>'
        .$this->get_log_lines($nlines).
        '</pre>
      </div>';
      return $html;
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields(){
      $this->form_fields = array(

        // enabled/disabled checkbox
        'enabled' => array(
          'title'   => __( 'Enable/Disable', 'woocommerce_gateway_tvb' ),
          'type'    => 'checkbox',
          'label'   => __( 'Enable TrivenetoBassilichi TVB Payment Method', 'woocommerce_gateway_tvb' ),
          'default' => 'yes'
        ),

        // Title
        '_PG_Title' => array(
          'title' => __( 'Title', 'woocommerce' ),
          'type' => 'text',
          'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
          'default' => __( 'TrivenetoBassilichi', 'woocommerce' )
        ),

        // Description
        '_PG_Description' => array(
          'title' => __( 'Description', 'woocommerce' ),
          'type' => 'textarea',
          'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
          'default' => __("Pay via TrivenetoBassilichi, our trusted Gateway provider", 'woocommerce')
        ),

        // _PG_System_Environment
        '_PG_System_Environment' => array(
          'title'       => __( 'Gateway Environment', 'woocommerce_gateway_tvb' ),
          'type'        => 'select',
          'default'     => 'Test',
          'class'       => 'system_environment wc-enhanced-select',
          'options'     => array( 
            'Test'       => 'Test', 
            'Production' => 'Production'
          )
        ),

        // _PG_ID_Merchant_Test
        '_PG_ID_Merchant_Test' => array(
          'title' => __( '[Test] ID Merchant', 'woocommerce_gateway_tvb' ),
          'type' => 'text',
          'default' => '',
          'desc_tip'    => true,
          'description' => 'This info should be provided by your Bank'
        ),

        // _PG_Password_Test
        '_PG_Password_Test' => array(
          'title' => __( '[Test] Password', 'woocommerce_gateway_tvb' ),
          'type' => 'password',
          'default' => '',
          'desc_tip'    => true,
          'description' => 'This info should be provided by your Bank'
        ),

        // _PG_ID_Merchant_Production
        '_PG_ID_Merchant_Production' => array(
          'title' => __( '[Production] ID Merchant', 'woocommerce_gateway_tvb' ),
          'type' => 'text',
          'default' => '',
          'desc_tip'    => true,
          'description' => 'This info should be provided by your Bank'
        ),

        // _PG_Password_Production
        '_PG_Password_Production' => array(
          'title' => __( '[Production] Password', 'woocommerce_gateway_tvb' ),
          'type' => 'password',
          'default' => '',
          'desc_tip'    => true,
          'description' => 'This info should be provided by your Bank'
        ),

        // _PG_CurrencyCode
        '_PG_CurrencyCode' => array(
          'title' => __( 'International Currency Code (978 = euro)', 'woocommerce_gateway_tvb' ),
          'type' => 'text',
          'default' => '978',
          'desc_tip' => true,
          'description' => 'Google for the "ISO 4217" standard and paste the number code for your wanted currency.',
        ),

        // _PG_Default_LangId
        '_PG_Default_LangId' => array(
          'title' => __( 'Default Language', 'woocommerce_gateway_tvb' ),
          'type'        => 'select',
          'default'     => 'ITA',
          'class'       => '_PG_Default_LangId wc-enhanced-select',
          'options'     => array( 
            'ITA' => 'Italian',
            'USA' => 'English',
            'FRA' => 'French',
            'DEU' => 'German',
            'ESP' => 'Spanish',
            'SLO' => 'Slovenian',
          )
        ),

        // _PG_URL_base
        '_PG_URL_base' => array(
          'title' => __( 'Response domain URL base', 'woocommerce_gateway_tvb' ),
          'type' => 'text',
          'default' => str_replace('http://','',get_site_url()),
        ),

        // _PG_responseURL
        '_PG_responseURL' => array(
          'title' => __( 'Default Callback Page', 'woocommerce_gateway_tvb' ),
          'type' => 'text',
          'default' => '/%s/shop/callback',
          'desc_tip'    => true,
          'description' => 'You can use "%s" as language code token'
        ),

        // _PG_errorURL
        '_PG_errorURL' => array(
          'title' => __( 'Error Callback Page', 'woocommerce_gateway_tvb' ),
          'type' => 'text',
          'default' => '/%s/shop/error',
          'desc_tip'    => true,
          'description' => 'You can use "%s" as language code token'
        ),

        // _PG_goodURL
        '_PG_goodURL' => array(
          'title' => __( 'Success Callback Page', 'woocommerce_gateway_tvb' ),
          'type' => 'text',
          'default' => '/%s/shop/success',
          'desc_tip'    => true,
          'description' => 'You can use "%s" as language code token'
        ),

      );
    }

    /**
     * Process the payment
     * @param  integer $order_id
     * @return array   result & redirect URL
     */
    function process_payment($order_id){
      // WooCommerce global
      global $woocommerce;

      // Require Triveneto class
      require('PgConsTriv.php');

      // Get Order Object
      $order = new WC_Order( $order_id );

      // init PgConsTriv Class for PaymentInit (action Purchase)
      $pg = new PgConsTriv(ICL_LANGUAGE_CODE, $this->settings);
      $pg->setAction('Purchase');
      // Payment Provider response will be sent to our triveneto_response_interface
      // Which is defined in 'woocommerce-triveneto-gateway.php'
      $pg->setResponseURL(get_home_url().'/?triveneto_response_interface=1');
      // Define the goto URL if something goes wrong
      $pg->setErrorURL($order->get_cancel_order_url());

      // Send the message of PaymentInit
      $amount      = $order->get_total();
      $amount      = floatval( preg_replace( '#[^\d.]#', '', $amount)); // parse numbers
      $tracking_id = $order_id;
      $pg->sendVal_PI($amount, $tracking_id);
      // Get result of request
      $hasError      = $pg->hasError_PI();
      $ID_PI         = $pg->getID_PI();

      // Check for errors
      if($hasError){
        $errorCode = $pg->getError_PI();
        // Cancel process and redirect to other page:
        return array(
          'result' => 'error',
          'redirect' => $order->get_cancel_order_url(),
        );
      }




      // Mark as on-hold (we're awaiting the cheque)
      $order->update_status('on-hold', __( 'Awaiting TrivenetoBassilichi payment', 'woocommerce_gateway_tvb' ));

      // Reduce stock levels
      $order->reduce_order_stock();

      // Remove cart
      $woocommerce->cart->empty_cart();
      
      // Get the payment URL for the customer:
      $paymentURL = $pg->getPaymentURL_PI();
      
      // Send to payment URL
      return array(
        'result' => 'success',
        'redirect' => $paymentURL,
      );

    }

  }
