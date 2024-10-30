<?php
/*
Plugin Name: KISS Metrics for WooCommerce
Plugin URI: http://www.maxwellrice.com/kiss-metrics-for-woocommerce/
Description: Adds KISS Metrics tracking to WooCommerce
Version: 0.3.0
Author: Max Rice
Author URI: http://www.maxwellrice.com

License:
 
	Copyright: © 2012 Max Rice (max@maxwellrice.com)
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Check if WC is installed
if (!in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' )) ) ) return;
// Check if our class name is free
if ( (class_exists('WC_KISS_Metrics')) ) return;

add_action ('plugins_loaded', 'wc_init_km', 0);

function wc_init_km() {
		
		// KISS Metrics PHP API
		require_once ('km.php');

		// since WC 1.5.5
		if (!class_exists('WC_Integration')) return;
		
		class WC_KISS_Metrics extends WC_Integration {
			
			public function __construct() {
				
				// Localization
				load_plugin_textdomain('wc_kiss_metrics', false, dirname( plugin_basename( __FILE__) ) . '/lang');
				
				// Lifecycle hooks
				register_activation_hook( __FILE__, array( &$this, 'activate' ) );
				register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );
				
				
				// Setup plugin
				$this->id						= 'kiss_metrics';
				$this->method_title				= __( 'KISS Metrics', 'wc_kiss_metrics' );
				$this->method_description		= __( 'Web analytics tool that tracks visitors to your site as people, not pageviews. Visualize your online sales funnels and find out which ones are driving revenue and which are not.', 'wc_kiss_metrics' );
				
				// Load admin form
				$this->init_form_fields();
				
				// Load settings
				$this->init_settings();
				
				// Load user-defined variables
				$this->km_api_key						= $this->settings['km_api_key'];
				$this->km_identity_pref					= $this->settings['km_identity_pref'];
				
				// Save admin options
				add_action( 'woocommerce_update_options_integration_kiss_metrics', array( &$this, 'process_admin_options') );
				
				// Load the header tracking code
				add_action( 'wp_head', array( &$this, 'km_output_head') );
				add_action( 'login_head', array( &$this, 'km_output_head') );

				// Init KM API
				KM::init(
					$this->km_api_key, array(
					'use_cron'=> FALSE,
					'to_stderr'=>true
					));
				
				/*
				/ Add tracked events to proper hooks
				*/
				
				// Signed in
				add_action( 'wp_login', array( &$this, 'km_signed_in' ));
				
				// Signed out
				add_action( 'wp_logout', array( &$this, 'km_signed_out' ));
				
				// Signed up for new account (on my account page if enabled OR during checkout)
				add_action( 'user_register', array( &$this, 'km_signed_up' ));
				
				// Started Checkout
				add_action( 'woocommerce_before_checkout_form', array( &$this, 'km_started_checkout' ));
				
				// Viewed Cart (either with items or empty)
				add_action( 'woocommerce_before_cart_contents', array( &$this, 'km_viewed_cart' ));
				add_action( 'woocommerce_cart_is_empty', array( &$this, 'km_viewed_cart' ));
				
				// Viewed Product (Properties: Name)
				add_action( 'woocommerce_before_single_product', array( &$this, 'km_viewed_product' ));
				
				// Added Product to Cart (Properties: Name, Quantity)
				add_action( 'woocommerce_add_to_cart', array( &$this, 'km_added_to_cart' ), 10, 6 );
				
				// Commented (Properties: Content Type=>product review or blog post, Product Name if review)
				add_action( 'comment_post', array( &$this, 'km_commented' ));
				
				// Completed Purchase
				add_action( 'woocommerce_thankyou', array( &$this, 'km_completed_purchase'), 10, 1);
				
			}
					
			function km_signed_in() { $this->km_api_record_event('signed in'); }
			
			function km_signed_out() { $this->km_api_record_event('signed out'); }
			
			function km_signed_up() { $this->km_api_record_event('signed up'); }
			
			function km_started_checkout() { $this->km_js_record_event('started checkout'); }
			
			function km_viewed_cart() { $this->km_js_record_event('viewed cart'); }
			
			function km_viewed_product() {
				// Don't track view if refresh from add to cart
				if ((stripos( $_SERVER['REQUEST_URI'], '?add-to-cart=') === FALSE)) {
					$this->km_js_record_event('Viewed Product',array('Product Name'=>get_the_title()));
				}
			}
			
			function km_added_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
				$this->km_api_record_event('added to cart',array('product name'=>get_the_title($product_id), 'quantity'=>$quantity));
			}
				
			function km_commented() {
				$type = get_post_type();
				if ($type == 'product') {
					$this->km_api_record_event('commented',array('content type'=>'product review','product name'=>get_the_title()));
				} elseif ($type == 'post') {
				 	$this->km_api_record_event('commented',array('content type'=>'blog comment'));
				}
			}
			
			function km_completed_purchase($order_id) {
				$order = new WC_Order($order_id);
				
				if ($order->get_items()) {
					 foreach($order->get_items() as $item)
					 {
						$total_qty += $item['qty'];
					 }
				}
				
				$this->km_js_record_event('purchased',array('order id'=>$order_id, 'revenue'=>$order->order_total,'total quantity'=>$total_qty,'payment method'=>$order->payment_method_title));
			}
			
			/**
			* Output tracking code to header
			* @ since 0.1.0
			*
			*/
			function km_output_head() {
				// don't track admin & don't track if API key is blank
				if ( is_admin() OR current_user_can('manage_options') OR (!$this->km_api_key)) return;
				
				// identify logged in users
				if ( is_user_logged_in() ) {
					
					$current_user = get_user_by( 'id', get_current_user_id() );
					
					// send identify command with either email address or wordpress username
					$identity = ($this->km_identity_pref == 'email' ? $current_user->user_email : $current_user->user_login);
				} else { $identity = 'null'; }
				
				# todo - output this nicer
				?>
				<script type="text/javascript">
				  var _kmq = _kmq || [];
				  function _kms(u){
					setTimeout(function(){
					var s = document.createElement('script'); var f = document.getElementsByTagName('script')[0]; s.type = 'text/javascript'; s.async = true;
					s.src = u; f.parentNode.insertBefore(s, f);
					}, 1);
				  }
				  _kms('//i.kissmetrics.com/i.js');_kms('//doug1izaerwt3.cloudfront.net/<?php echo $this->km_api_key; ?>.1.js');
				  _kmq.push(['identify', '<?php echo $identity ?>']);
				</script>
				<?php
			}
			
			/**
			* Output event tracking javascript
			* 
			* @ since 0.2.0
			*
			* @param string $event_name Required. Name of Event to be set
			* @param associative array $properties Optional. Properties to be set with event.
			*/
			function km_js_record_event($event_name, $properties = '') {
					if($properties) $properties = ", " . json_encode($properties);
					echo "<script type=\"text/javascript\">_kmq.push(['record', '$event_name'$properties]);</script>";
			}
			
			/**
			* Record event via PHP API
			* @ since 0.3.0
			*
			* @param string $event_name required. Name of Event to be set
			* @param associative array $properties Optional. Properties to be set with event.
			*/
			function km_api_record_event($event_name, $properties ='') {
				// Verify that tracking cookie is set and get preferred identity
				// When logging events via PHP API, prefer named identity first, then anonymous
				if (isset($_COOKIE['km_ni'])){
				
					$identity = $_COOKIE['km_ni'];
					
				} elseif (isset($_COOKIE['km_ai'])) {
					
					$identity = $_COOKIE['km_ai'];
					
				} else {
				
					 return; 
				}
				
				KM::identify($identity);
				
				if($properties) {
					KM::record($event_name,$properties);
				} else {
					KM::record($event_name);
				}
				
			}
			
			/**
			 * Admin options
			**/
			function init_form_fields() {
			
				$this->form_fields = array( 
						'km_api_key' => array(  
							'title' 			=> __('API Key', 'wc_kiss_metrics'),
							'description' 		=> __('Log into your account and go to Site Settings. Leave blank to disable tracking.', 'wc_kiss_metrics'),
							'type' 				=> 'text',
		    				'default' 			=> get_option('woocommerce_km_api_key')
							),
						'km_identity_pref' => array(
							'title'				=> __('Identity Preference', 'wc_kiss_metrics'),
							'description'		=> __('Select how to identify logged in users.', 'wc_kiss_metrics'),
							'type'				=> 'select',
							'default'			=> get_option('woocommerce_km_identity_pref'),
							'options'			=> array(
													'email' => __('Email Address', 'wc_kiss_metrics'),
													'username' => __('Wordpress Username', 'wc_kiss_metrics')
													)
							)
					);
			}
			
			/**
			 * Lifecycle functions
			**/
			function activate ( $network_wide) {
				//
			}
			
			function deactivate ( $network_wide) {
				//
			}
	
		}
}

/**
 * Add the integration to WooCommerce
 **/
function add_kiss_metrics_integration( $integrations ) {
	$integrations[] = 'WC_KISS_Metrics'; return $integrations;
}
add_filter('woocommerce_integrations', 'add_kiss_metrics_integration' );?>