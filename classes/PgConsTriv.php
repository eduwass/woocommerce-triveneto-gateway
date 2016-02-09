<?php
/*********************************************************************************
 * Project:     Payment Gateway Class (Consorzio Triveneto S.p.A.)
 * File:        PgConsTriv.php
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
 * @author Davide Gullo (gullo [at] m4ss [dot] net)
 * @package PgConsTriv Class
 * @version 1.3 (12/05/2010)
 *
 *********************************************************************************/

/*********************************************************************************
 *
 * Note:
 * 
 * This is a modified version of the Triveneto PHP Class by Davide Gullo
 * Original credit goes to Davide.
 * 
 * I adapted it for WooCommerce use.
 * 
 * Modified it following GPL license terms.
 *
 * @author Edu Wass
 * @version 1.0 (05/08/2015) 
 * 
/*********************************************************************************/

/*
   -----------------------------------------------gp-------------------------------------
   | Online documentation for this class is available on:
   | http://www.m4ss.net/os-open-source/payment-gateway-consorzio-triveneto-php-class
   ------------------------------------------------------------------------------------
*/


	class PgConsTriv {
		// language
		private $lng; // ISO 639-1 Code
		private $hasLanguage = false;

		// Type of transaction (action)
		private $action;

		// Array variables to be sent to the PaymentInit
		private $arPayInit = array();
		// RECEIVED variables from PaymentInit
		private $PayInit_ID;
		private $PayInit_URL;
		private $PayInit_ERROR = null;
		private $PayInit_Code = null;

		// Variables NotificationMessage
		private $arNotMess = array();
		private $NotMess_ID;

		// Redirection URLs
		private $responseURL;
		private $errorURL;

		// Array of Action managed (PaymentInit and Payment)
		private $arAction = array(
								'Purchase' 		=> 1,
								'Credit' 		=> 2,
								'Reversal' 		=> 3,
								'Authorization'	=> 4,
								'Capture' 		=> 5,
								'Void' 			=> 9,
		);
							
		// Array for conversion Languages by encoding the PG to ISO
		private $arLingue = array(
						'it' => 'ITA',
						'en' => 'USA',
						'es' => 'ESP',
						'de' => 'DEU',
						'fr' => 'FRA',
		);

		/*
		*  Constructor
		*/
		function __construct($l=null, $settings)
		{

			// Save WooCommerce settings as Globals
			foreach($settings as $settingKey => $settingValue){
				define($settingKey, $settingValue);
			}

			/* URL Variable Payment Gateway and communication protocol */
			// These do not change:
			define("_PG_URL_PaymentInit_Test", "https://test4.constriv.com/cg301/servlet/PaymentInitHTTPServlet");
			define("_PG_URL_PaymentInit_Production", "https://www.constriv.com/cg/servlet/PaymentInitHTTPServlet");
			define("_PG_URL_Payment_Test", "https://test4.constriv.com/cg301/servlet/PaymentTranHTTPServlet");
			define("_PG_URL_Payment_Production", "https://www.constriv.com/cg/servlet/PaymentTranHTTPServlet");
			
			// Try to Set Language
			if(!is_null($l))
			{
				if(isset($this->arLingue[$l])) {
					$this->lng = $l;
					$this->hasLanguage = true;
				} else {
					// Use italian as default language
					$this->lng = 'it';
					$this->hasLanguage = true;
				}
			} else {
				// set ISO 639-1 Code for the default language
				$this->lng = array_search(_PG_Default_LangId, $this->arLingue);
				$this->hasLanguage = false;
			}
		}

		/*
		 	Function:	set_Action
			Set Type of transaction (action) that I am making
		*/
		function setAction($a)
		{
			if(isset($this->arAction[$a]))
			{
				$this->action = $this->arAction[$a];
			} else {
				throw new Exception('Azione non gestita da questa classe. Vedi documentazione.');
			}
		}

		/***********************************
		* 	Metodi per il PaymentInit
		* *********************************/
		/*
		 	Function:	setSecurityCode_PI
			Sept. Security code that will be sent via GET together with PaymentURL
		*/
		function setSecurityCode_PI($sc)
		{
			$this->PayInit_Code = $sc;
		}

		/*
		 	Function:	setCampoUdf_PI
			set UDF Fields (UDF fields at the discretion of the Merchant)
		*/
		function setCampoUdf_PI($n, $val)
		{
			$udf = 'udf'.$n;
			$this->setVal_PayInit($udf, $val );
		}

		/*
		 	Function:	sendVal_PI
			Sending Message PaymentInit and processing the response
		*/
		function sendVal_PI( $amt, $trackid)
		{
			// set values to be sent via POST
			$this->setVal_PayInit('id', $this->get_PG_ID_Merchant() );
			$this->setVal_PayInit('password', $this->get_PG_Password() );
			$this->setVal_PayInit('action', $this->action);
			$this->setVal_PayInit('amt', $amt);
			$this->setVal_PayInit('currencycode', _PG_CurrencyCode);
			$this->setVal_PayInit('langid', $this->getLngPG());
			$this->setVal_PayInit('responseURL', $this->getResponseURL_PaymentInit());
			$this->setVal_PayInit('errorURL', $this->getErrorURL_PaymentInit());
			$this->setVal_PayInit('trackid', $trackid);
			$this->setVal_PayInit('udf4', $this->PayInit_Code );

			// Send values via POST
			$res = $this->SendPost($this->get_PG_URL_PaymentInit(), $this->arPayInit);
			$this->triveneto_log("LOG Request sent to: ". $this->get_PG_URL_PaymentInit());
			$this->triveneto_log("LOG PaymentInit result: ".$res);
			// set values returned by the transaction sent
			$this->setVal_ResponsePayInit($res);
		}

		/*
		 	Function:	hasError_PI
			Returns Bool ERROR for existence on PaymentInit
		*/
		function hasError_PI()
		{
			return is_null($this->PayInit_ERROR) ? false : true;
		}

		/*
		 	Function:	getError_PI
			Returns the message ERROR PaymentInit
		*/
		function getError_PI()
		{
			return is_null($this->PayInit_ERROR) ? "NESSUN ERRORE!" : $this->PayInit_ERROR;
		}

		/*
		 	Function:	getID_PI
			Returns PaymentID returned by the closing of the transaction PaymentInit
		*/
		function getID_PI()
		{
			return $this->PayInit_ID;
		}

		/*
		 	Function:	getPaymentURL_PI
			Returns the URL to redirect the user (Cardholder) after the conclusion of the transaction PaymentInit
		*/
		function getPaymentURL_PI()
		{
			$url = $this->PayInit_URL . "?PaymentID=" . $this->getID_PI();
			return $url;
		}

		/*********************************************
		* 	Metodi per il NotificationMessage
		* *******************************************/

		/*
		 	Function:	setVal_NM
			set variables sent by NotificationMessage
		*/
		function setVal_NM($post)
		{
			$this->arNotMess = $post;
		}

		/*
		 	Function:	isValid_NM
			Check validity of data NotificationMessage according to SecurityCode
		*/
		function isValid_NM()
		{
			if( is_null($this->PayInit_Code) ) {
				return false;
			} else {
				return ( $this->PayInit_Code == $this->getVal_NM('udf4') ) ? true : false;
			}
		}

		/*
		 	Function:	isTransError_NM
			Bool Returns true if there is an ERROR when TRANSACTION
		*/
		function isTransError_NM()
		{
			return (isset($this->arNotMess["Error"]) && isset($this->arNotMess["ErrorText"])) ? true : false;
		}

		/*
		 	Function:	isTransGood_NM
			Bool Returns true if the TRANSACTION was drawn
		*/
		function isTransGood_NM()
		{
			return (isset($this->arNotMess["result"]) && isset($this->arNotMess["trackid"])) ? true : false;
		}
		/*
			Verifica Stati (result) della Transazione
		*/
		function isCaptured_NM() { return ($this->getVal_NM("result") == "CAPTURED") ? true : false; }
		function isNotCaptured_NM() { return ($this->getVal_NM("result") == "NOT CAPTURED") ? true : false; }
		function isApproved_NM() { return ($this->getVal_NM("result") == "APPROVED") ? true : false; }
		function isNotApproved_NM() { return ($this->getVal_NM("result") == "NOT APPROVED") ? true : false; }
		function isDeniedByRisk_NM() { return ($this->getVal_NM("result") == "DENIED BY RISK") ? true : false; }
		function isHostTimeout_NM() { return ($this->getVal_NM("result") == "HOST TIMEOUT") ? true : false; }

		/*
		 	Function:	getVal_NM
			Returns a value ($ v) if the array set with setVal_NM ($ post)
			* In caso non esista restituisce un valore null
		*/
		function getVal_NM($v)
		{
			return isset($this->arNotMess[$v]) ? $this->arNotMess[$v] : null;
		}

		/*
		 	Function:	getPaymentID_NM
			Get PaymentID
			* In caso non sia settato restituisce false
		*/
		function getPaymentID_NM()
		{
			return is_null($this->getVal_NM("paymentid")) ? false : $this->getVal_NM("paymentid");
		}

		/*
		 	Function:	getURL_NM
			Returns the URL to redirect the user (Cardholder)
			* L'URL viene creato in base a:
			* - risposta fornita dal server: valore result settato tramite il metodo setVal_NM($post)
			* - action impostata con set_Action($a)
		*/
		function getURL_NM()
		{
			// Start building address
			$url = "http://" . _PG_URL_base;
			switch($this->action)
			{
				case 1:
					$url .= $this->isCaptured_NM() ? _PG_goodURL : _PG_errorURL;
				break;
				case 4:
					$url .= $this->isApproved_NM() ? _PG_goodURL : _PG_errorURL;
				break;
			}
			$urlLng = $this->makeURLwithLng($url);
			return $urlLng;
		}

		/***********************************
		* 	Utility & Miscellanueos
		* *********************************/

		/*
			Function:	setVal_ResponsePayInit
			Converte l'array e lo invia tramite POST all'url specificato
		*/
		private function setVal_ResponsePayInit($r)
		{
			// Check the outcome PaymentInit
			if(strpos($r, "ERROR") === false )
			{
				// compose string and recovery PaymentID and PaymentURL
				$dd = strpos($r, ":");
				$PayID = substr($r, 0, $dd);
				$PayURL = substr($r, ($dd+1));
				// set PaymentID and PaymentURL
				$this->PayInit_ID = $PayID;
				$this->PayInit_URL = $PayURL;
			} else {
				$this->PayInit_ERROR = $r;
			}
		}

		/*
		 	Function:	setVal_PayInit
			Convert the array and sends it via POST to the url specified
		*/
		private function setVal_PayInit($k, $v)
		{
			if(!is_null($v)) {
				$this->arPayInit[$k] = $v;
			}
		}

		/*
		 	Function:	reset_PayInit
			Reset array PayInit
		*/
		private function reset_PayInit()
		{
			$this->arPayInit = array();
		}

		/*
		 	Function:	SendPost
			Send values ​​via POST to the url specified
		*/
		private function SendPost($url, $arVal)
		{
			$handle = curl_init();
			curl_setopt($handle, CURLOPT_URL, $url);
			curl_setopt($handle, CURLOPT_VERBOSE, true);
			curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
			//curl_setopt($handle, CURLOPT_CAINFO, _PATH_ROOT_SISTEMA . "\include\curl-ca-bundle.crt");
			curl_setopt($handle, CURLOPT_POST, true);
			curl_setopt($handle, CURLOPT_POSTFIELDS, $this->get_UrlEncodedFromArray($arVal));
			$buffer = curl_exec($handle);

			if($buffer === false)
			{
			    echo 'Curl error: ' . curl_error($handle);
			}

			curl_close($handle);
			return $buffer;
		}

		/*
		 	Function:	get_UrlEncodedFromArray
			Convert the array to string and send them via POST
		*/
		private function get_UrlEncodedFromArray($ar)
		{
			$str = "";
			if(count($ar) > 0) {
				foreach($ar AS $k => $v) {
					$str .= $k."=".urlencode($v)."&";
				}
				$str = substr($str, 0, -1);
			}
			return $str;
		}

		function setResponseURL($url){
			$this->responseURL = $url;
		}

		function setErrorURL($url){
			$this->errorURL = $url;
		}

		/*
		 	Function:	getResponseURL_PaymentInit
			Returns the URL to redirect the user (Cardholder)
		*/
		private function getResponseURL_PaymentInit()
		{
			return $this->responseURL;
		}

		/*
		 	Function:	getErrorURL_PaymentInit
			Returns the URL to redirect the user (Cardholder)
		*/
		private function getErrorURL_PaymentInit()
		{
			return $this->errorURL;
		}

		/*
		 	Function:	makeURLwithLng
			Sets the language passed in the URL depending on the property and $ lng $ hasLanguage
		*/
		private function makeURLwithLng($url)
		{
			$urlLng = ($this->hasLanguage) ? sprintf( $url, $this->lng ) : $url;
			return $urlLng;
		}

		/*
		 	Function:	get_PG_URL_PaymentInit
			Returns the URL to use for PaymentInit according to Test or Production
		*/
		private function get_PG_URL_PaymentInit()
		{
			$url = constant("_PG_URL_PaymentInit_" . _PG_System_Environment);
			return $url;
		}
		/*
		 	Function:	get_PG_ID_Merchant
			Returns ID_Merchant be used for PaymentInit according to Test or Production
		*/
		private function get_PG_ID_Merchant()
		{
			$url = constant("_PG_ID_Merchant_" . _PG_System_Environment);
			return $url;
		}
		/*
		 	Function:	get_PG_Password
			Returns Password to use for PaymentInit according to Test or Production
		*/
		private function get_PG_Password()
		{
			$url = constant("_PG_Password_" . _PG_System_Environment);
			return $url;
		}
		
		/*
		 	Function:	getLngPG
			It returns the language according to the coding of the Payment Gateway
		*/
		private function getLngPG()
		{
			return $this->arLingue[$this->lng];
		}
		
		/**
		 * Function: log
		 * Saves message to log file
		 * @param  string $s
		 */
		function triveneto_log($s) {
			$txt = $_SERVER['REMOTE_ADDR'];
			$upload_dir = wp_upload_dir();
			$path = $upload_dir['basedir'].'/wc-logs/';
			// Create dir if doesnt exist:
			if (!file_exists($path)) {
			  mkdir($path, 0777, true);
			}
			// Write log
			$f = fopen ($path . 'woocommerce-triveneto-gateway.log', "a+");
			$date = date('c');
			$dbg = print_r ($s, true);
			@fprintf ($f, "$date (" . ($txt ? "$txt" : '') . ") - ");
			fprintf ($f, $dbg . "\n");
			// Close
			fclose ($f);
		}

	}

?> 