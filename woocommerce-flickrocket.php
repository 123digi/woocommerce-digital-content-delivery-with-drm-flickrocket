<?php
	/*
	Plugin Name: WooCommerce Digital Content Delivery (incl. DRM) - FlickRocket
	Plugin URI: http://www.flickrocket.com/
	Description: Enable sales and rentals of (optionally DRM protected) digital content such as DVDs, video (HD+SD), audio books, ebooks  (epub and PDF) and packaged content such as HTML, Flash, images, etc. Includes CDN, customizable player/reader, tracking and much more. Supports PC, Mac, iOS, Android, Kindle and SmartTVs.
	Version: 1.0
	Author: FlickRocket
	Author URI: http://www.flickrocket.com/
	License: ***********
	*/
	
	if(session_id() == '')
		session_start();
	
	error_reporting(0);
	
	global $wpdb, $FlickPluginCurrentVersion;
			
	$FlickPluginCurrentVersion 	= "1.0";
	
	define( 'FW_PATH',	dirname(__FILE__) );
	
	define( 'FW_URL', 	plugins_url()."/".basename(dirname(__FILE__)) );
	
	define( 'FILE_NAME' , __FILE__ );
	 	
	add_action( 'init', 'initialize',100 );
	
	add_action( 'plugins_loaded', 'flick_myplugin_init' );
	
	add_action( 'woocommerce_get_settings_pages', 'load_setting_tab', 200, 1 );
	
	function flick_myplugin_init(){
		include( dirname(__FILE__ ) . '/languages/en_US.php' ); 
		
	}
	
	function flick_safeFormInput($input){
		return stripslashes($input);
	}
	
	function flick_safeOutput($input){
		return htmlentities($input, ENT_QUOTES);
	}
	
	function load_setting_tab($settings){
		$settings[] = include( FW_PATH."/flickrocket_setting.php" );
		return $settings;
	}
	
	function initialize(){
		
		include_once FW_PATH."/flickrocket_function.php";
		
		include_once FW_PATH."/includes/class.flickrocketwoocommerce.php";
		
		
		
		if(class_exists('FlickRocketWooocommerce')){
			FlickRocketWooocommerce::init();	
			
		}
	}
			
	
	// Check if WooCommerce is active
	
	if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		
		// Hooks call when install the plugin, it will create table from db	
		register_activation_hook( __FILE__, 'flickrocket_woocommerce_activate' );
	}
			
	function flickrocket_woocommerce_activate(){
	
		global $wpdb, $FlickPluginCurrentVersion;
		
		update_option( 'sandbox_active', 'yes', '', 'yes' );
	}
	
	
	// Hooks call when deactivate the plugin
	
	register_deactivation_hook( __FILE__, 'flickrocket_woocommerce_deactivate' );
	
	function flickrocket_woocommerce_deactivate(){ 
		
		global $wpdb, $FlickPluginCurrentVersion;
		
	}
	
	
	// Hooks call when uninstall/delete the plugin, it will delete table from db 
	
	register_uninstall_hook( __FILE__, 'flickrocket_woocommerce_uninstall' );
	
	function flickrocket_woocommerce_uninstall(){	
		
		global $wpdb, $FlickPluginCurrentVersion;
		
		delete_option( 'flickrocket_user_email' );
		
		delete_option( 'flickrocket_user_password' );
		
		delete_option( 'flickrocket_theme_id' );
		
		delete_option( 'sandbox_active' );	
	}

	add_action( 'init', 'flickrocket_woocommerce_style' );

	function flickrocket_woocommerce_style(){	
		
		wp_enqueue_style( 'myPluginStylesheet', FW_URL . '/css/flickrocket.css' );
						
		wp_enqueue_script( 'custom_jquery', FW_URL . '/js/custom.js', array(), '', true );
	   
	}
	

	add_action('woocommerce_checkout_process', 'woocommerce_process_checkout_fliprocket', 5);
		
	//check checkour user all ready flickrocket user
	function woocommerce_process_checkout_fliprocket() {
		
		global $woocommerce;
		
		$billing_email			=	$_REQUEST['billing_email'];
		
		$account_password		=	$_REQUEST['account_password'];
		
		$data					=	array();
				 
		if($billing_email!="" && $account_password !=""){
		
			include_once FW_PATH."/includes/class.flickrocketwoocommerce.php";
			
			$FR_result = FlickRocketWooocommerce::check_user_exist_flickrocket($billing_email, $account_password);
			
			$companyNameA = $FR_result->Companies->string;
			
			$totalCompany = count($companyNameA);
	 
			if($totalCompany > 1){
		
				foreach($companyNameA as $companyName){
		
					$companyNameS .= $companyNameA . $companyNameA == '' ? '' : ', ';
				}
		
			}else{
		
				$companyNameS = $companyNameA;
			}
				 		
			if($FR_result->ErrorCode == -5 || $FR_result->ErrorCode == '-5'){
				
				$messages = ob_get_clean();

				echo '<!--WC_START-->' . json_encode(
					array(
						'result'	=> 'failure',
						'messages' 	=> '<ul class="woocommerce-error">
					
														<li><strong>FlickRocket Error </strong><div>The password you have specified does not match the records of the digital delivery backend for your email. To access all your content one account, you need to use the same password as you have with the following services:</div><div style="margin-top:5px;">&raquo; ' . $companyNameS . '</div></li>
					
													</ul>',
						'refresh' 	=> isset( WC()->session->refresh_totals ) ? 'true' : 'false',
						'reload'    => isset( WC()->session->reload_checkout ) ? 'true' : 'false'
					)
				) . '<!--WC_END-->';
			
				die(0);	
			}
		}
	}
	
	add_filter( 'woocommerce_payment_complete_order_status', 'virtual_order_payment_complete_order_status', 10, 2 );

	function virtual_order_payment_complete_order_status( $order_status, $order_id ) {

		$order = new WC_Order( $order_id );
		
		if ( 'processing' == $order_status && ( 'on-hold' == $order->status || 'pending' == $order->status || 'failed' == $order->status ) ) {
			
			$virtual_order = null;
			
			if ( count( $order->get_items() ) > 0 ) {
				
				foreach( $order->get_items() as $item ) {
				
					if ( 'line_item' == $item['type'] ) {
				
						$_product = $order->get_product_from_item( $item );
				
						if ( ! $_product->is_virtual() ) {
				
							// once we've found one non-virtual product we know we're done, break out of the loop
							$virtual_order = false;
							
							break;
				
						} else {
							
							$virtual_order = true;
						}
					}
				}
			}
			
			// virtual order, mark as completed
			if ( $virtual_order ) {
				
				return 'completed';
			}
		}
		
		// non-virtual order, return original status
		
		return 'completed'; //$order_status;
	}	
?>