<?php
	/**
	 * This is responsible for processing AJAX or other requests
	 *
	 * @since 1.0.0
	 */

	namespace Spillt;

	class SettingsPage {

		protected static $instance = null;

		/**
		 * @since 1.0.0
		 * @throws \Exception
		 */
		public function __construct(){
			add_action( 'admin_menu', __CLASS__ . '::menu_page_register' );
			add_action( 'admin_init', __CLASS__ . '::settings_page_init' );
			add_action( 'admin_notices', __CLASS__ . '::notice_activation_status' );
			add_action( 'wp_ajax_spillt_send_sms_to_phone', __CLASS__ . '::request_phone_verification' );
			add_action( 'wp_ajax_spillt_verify_author_phone_number', __CLASS__ . '::sms_verification' );
			add_action( 'spillt_verify_author_phone_number', __CLASS__ . '::sms_verification' );
			add_action( 'admin_enqueue_scripts',  __CLASS__ . '::enqueue_admin_scripts' );
		}

		/**
		 * Return an instance of this class.
		 *
		 * @since     1.0.0
		 *
		 * @return    object    A single instance of this class.
		 * @throws \Exception
		 */

		public static function instance() {

			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		public static function enqueue_admin_scripts($hook) {
			if($hook == 'spillt_page_spillt-settings') {
				wp_enqueue_style( 'spilt-settings-styles', PLUGIN_URL .'/assets/css/spilt-style.css', array());
				wp_enqueue_script( 'ajax-script', PLUGIN_URL .'/assets/js/ajax-admin.js', array('jquery'), false );
				wp_localize_script( 'ajax-script', 'ajax_object',
					array( 'ajax_url' => admin_url( 'admin-ajax.php' )) );
			}
		}
		public static function menu_page_register() {

		    $notification_errors = get_option('spillt_error_show') == 'true' ? 1 : 0;

			add_submenu_page(
				'spillt',
				'Settings',
				$notification_errors ? sprintf( 'Settings <span class="awaiting-mod">%d</span>', $notification_errors ) : 'Settings',
				'manage_options',
				'spillt-settings',
				array( __CLASS__, 'spillt_settings_content' )
			);
		}


		public static function spillt_settings_content(){
			?>
            <div class="wrap">
                <h2><?php echo esc_textarea(get_admin_page_title()); ?></h2>

                <form id="spillt_blog_phone" action="options.php" method="POST">
                    <input type="hidden" name="action" value="get_author_form">
					<?php
						settings_errors('spillt_author_phone');
						settings_fields('spillt_settings_group');
						do_settings_sections('spillt-settings');
					?>

                </form>
                <a id="checkPhoneBTN" class="button button-primary">Authorize site</a>
                <div id="spillt_verify_phone_wrap" class="overlay">
                    <div class="popup">
                        <h2>Please Enter code from SMS</h2>
                        <div style="position:absolute;top:20px;right:30px;"><a id="close-manually-sync" class="button button-primary">Close</a></div>
                        <div class="content">
                            <form id="spillt_verify_phone" action="" method="POST">
                                <input type="hidden" name="action" value="spillt_verify_author_phone_number">
                                <input type="hidden" name="type" value="sms">
                                <input id="phoneToVerify" type="hidden" name="phone" value="">
                                <input type="number" name="token" maxlength="6" minlength="6" required>
                                <input type="submit" name="verify_sms" value="Send" class="button button-primary">
                                <a id="spilt_resend_sms" class="button">Resend Code.</a>
                            </form>
                            <div class="verify-error"></div>
                        </div>
                    </div>
                </div>
            </div>
			<?php
		}



		public static function settings_page_init(){

			add_settings_section(
				'spillt_setting_section', 				  	    // id
				'Authorization',								// title
				__CLASS__ . '::spillt_setting_section_callback', // callback function
				'spillt-settings'	 				// slug from add_menu_page()
			);
			add_settings_field(
				'spillt_author_phone',
				'Mobile phone number',
				__CLASS__ . '::spillt_author_phone_callback',
				'spillt-settings',			 // slug from add_menu_page()
				'spillt_setting_section' 					 // section id
			);
			add_settings_field(
				'spillt_author_tax_choosen',
				'Choose Taxonomy for Sync',
				__CLASS__ . '::spillt_author_choose_tax_callback',
				'spillt-settings',			 // slug from add_menu_page()
				'spillt_setting_section' 					 // section id
			);
			register_setting( 'spillt_settings_group', 'spillt_author_phone', array(
					'sanitize_callback' => __CLASS__ . '::spillt_author_phone_sanitize_callback'
				)
			);
			register_setting( 'spillt_settings_group', 'spillt_author_tax_choosen', array(
					'sanitize_callback' => __CLASS__ . '::spillt_author_taxonomy_sanitize_callback'
				)
			);
		}



		public static function spillt_setting_section_callback() {
			$url = 'https://spillt.co/download';
			echo "<p>Enter your mobile phone number in order to authorize Spillt. Use the same phone number as you used to sign up for the Spillt mobile application. If you have not downloaded the app yet, please visit <a target=blank href=" . esc_url($url) . ">this link</a> to download the app first.</p>";
		}

		public static function spillt_author_phone_callback() {
			?>

            <input
                    id="spillt_author_phone"
                    type="tel"
                    name="spillt_author_phone"
                    value="<?php echo esc_html( get_option('spillt_author_phone') ); ?>"
                    maxlength="14"
                    placeholder="+1 (___) ___-____"
                    required
            />
            <div id="phone_check_error"></div>
			<?php

		}
		public static function spillt_author_choose_tax_callback() {
			$taxonomies = get_taxonomies( [ 'public' => true ], 'objects', 'and' ); //'object_type' => [ 'post' ],
			$selected = get_option('spillt_author_tax_choosen')?get_option('spillt_author_tax_choosen'):'category';
			?>
            <select name="spillt_author_tax_choosen" id="spillt_author_tax_choosen">
				<?php
					foreach ( $taxonomies as $taxonomy ){
						?>
                        <option value="<?=$taxonomy->name;?>" <?=selected( $selected, $taxonomy->name, false )?>><?=$taxonomy->label;?></option>
						<?php
					}
				?>
            </select>

			<?php

		}

		static function cleanedPhone( $phone ) {
			$phone = sanitize_text_field( $phone );

			if (substr($phone, 0, 1) === "+" ) {
				$phone = preg_replace("/[^0-9]/", '', $phone);
			} else {
				$phone = preg_replace("/[^0-9]/", '', $phone);
				// Assume if no + included and 10 digits, US only for time being.
				if (strlen($phone) == 10) {
					$phone = '1'.$phone;
				}
			}

			return $phone;
		}

		// there should be validation after receiving the suprabase response (rewrite function)
		public static function spillt_author_phone_sanitize_callback( $author_phone ) {

			$option_name = 'spillt_author_phone';
			$message = 'Data sent successfully. Please, wait for the authorization of your site!';
			$type = 'updated';

			$author_phone = self::cleanedPhone( $author_phone );

			//if we have 10 digits left, it's probably not valid.
			if( strlen($author_phone) < 10 ){
				$message = 'Phone number is entered incorrectly. Please enter a valid phone number.';
				add_settings_error( $option_name, 'settings_error', $message, 'error' );

				return get_option( $option_name );
			}

			$response = SupaBase::insert_author( $author_phone, get_bloginfo('url'), html_entity_decode(get_bloginfo('name'), ENT_QUOTES) ); // post data

			if ( $response['message'] === 'JWT expired' ) {
				SupaBase::log_event('access_token_expired');
				update_option('_spilt_author_access_token_expiration_date', 0);
				update_option('_spilt_author_access_token', null);
				update_option('_spilt_author_refresh_token', null);
			} else {
				update_option('spillt_error_show', 'false');
			}

			if ($response['code'] == 200):
				SupaBase::log_event('verification_completed');
				update_option( 'spillt_author_id', $response['message'] );
				add_settings_error( $option_name, 'settings_updated', 'Verification completed!', 'success' );
            elseif ( $response['code'] == 400 ):
				$get_response = SupaBase::get_author_id( $author_phone, get_bloginfo('url') );
				if ( $get_response['code'] == 200 ){
					update_option( 'spillt_author_id', $get_response['message'] );
					add_settings_error( $option_name, 'settings_updated', 'Authorization successful!', 'success' );
				}
				else if ( $get_response['code'] == 404 ) {
					add_settings_error( $option_name, 'settings_error', 'This blog has already been added to another user.', 'error' );
				} else {
					add_settings_error( $option_name, 'settings_error', 'We were unable to link your Spillt account with your Wordpress site.', 'error' );
				}
            elseif ($response['code'] == '404'):
				add_settings_error( $option_name, 'settings_error', $response['message'], 'error' );
			else:
				add_settings_error( $option_name, 'settings_error', 'We were unable to link your Spillt account with your Wordpress site.', 'error' );
			endif;

			return $author_phone;
		}

		public static function spillt_author_taxonomy_sanitize_callback( $author_tax ) {
			return $author_tax;
		}

		public static function request_phone_verification() {
			SupaBase::log_event('phone_verification_request');
			if (!isset($_POST['phone']) || empty($_POST['phone'])) {
				echo json_encode( [ "status" => "error", "message" => "Phone number can't be blank" ] );
				wp_die();
			}
			$author_phone = self::cleanedPhone( $_POST['phone'] );

			$response = SupaBase::send_sms($author_phone);
 			if ( is_wp_error($response) || (isset($response['code']) && $response['code'] != 200)) {
				SupaBase::log_event('phone_verification_request_sms_error', json_encode($response));
				echo json_encode( [ "status" => "error", "message" => "An error occurred, please check your phone number and try again." ] );
				wp_die();
			} else {
				SupaBase::log_event('phone_verification_request_sms_sent');
				echo json_encode( [ "status" => "success", "message" => "SMS Send" ] );
				wp_die();
			}
		}
		public static function sms_verification() {
			SupaBase::log_event('confirmation_code_verified');
			$option_name = 'spillt_author_phone';
			if(!isset($_POST['token']) || empty($_POST['token'])) {
				echo json_encode( [ "status" => "error", "message" => "Please enter SMS code." ] );
				wp_die();
			}
			$type = $_POST['type'];
			$phone = self::cleanedPhone($_POST['phone']);
			$token = $_POST['token'];
			$response = SupaBase::verify_sms($phone,$token);

			if ( !isset($response['access_token']) && !isset($response['refresh_token']) && !isset($response['expires_in'])){
				SupaBase::log_event('confirmation_code_error');
				add_settings_error( $option_name, 'settings_error', $response['msg'], 'error' );
				update_option('_spillt_blog_user_supabase_token_response', $response, false);
				echo json_encode(array("status" => "error", "message" => $response['msg']));
			} else {
				SupaBase::log_event('confirmation_code_success');
				update_option('_spillt_blog_user_supabase_token_response', $response, false);
				update_option('_spilt_author_refresh_token', $response['refresh_token'], false);
				update_option('_spilt_author_access_token', $response['access_token'], false);
				update_option('_spilt_author_access_token_expiration_date', (time() + $response['expires_in']), false);
				update_option('spillt_author_id', $response['user']['id'], false);
				echo json_encode(array("status" => "success", "message" => "Token received"));
			}
			wp_die();
		}

		public static function notice_activation_status() {
			$current_screen = get_current_screen();

			$expiration_date = get_option( '_spilt_author_access_token_expiration_date' );
			if ( $expiration_date < time() && $current_screen->parent_base == 'spillt-main' || get_option( 'spillt_author_id' ) && $current_screen->parent_base == 'spillt-main' && ( !get_option( '_spilt_author_access_token' ) || !get_option( '_spilt_author_refresh_token' ) && $current_screen->parent_base == 'spillt-main') || get_option('spillt_error_show') == 'true') {
				?>
			        <div class="notice notice-error"> <p>Your access has expired. Please login again in the Spillt Settings page.</p></div>
                <?php
			}

			if( $current_screen->parent_base !== 'spillt-main' ) // if page is not Spillt plugin.
				return;

			if( !get_option( 'spillt_author_id' ) ){
				echo '<div class="notice notice-error"> <p>Plugin is not authorized. Please enter your phone number in the Spillt Settings page.</p> </div>'; // before phone sent
			} else if( $current_screen->id == 'spillt_page_spillt-settings' ){
				echo '<div class="notice notice-success is-dismissible"> <p>Plugin is authorized!</p> </div>'; // after phone sent
			} else if ( get_option( 'spillt_author_id' ) && ( !get_option( '_spilt_author_access_token' ) || !get_option( '_spilt_author_refresh_token' ) ) ){
				echo '<div class="notice notice-error"> <p>Your access has expired. Please login again in the Spillt Settings page.</p></div>'; //tokens expired
            }
			// If crone disabled
			if( defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ){
				echo '<div class="notice notice-warning"><p>WP-Cron is disabled by some plugin, which breaks the automatic syncing. You can still synchronize your recipes manually!</p></div>'; // before phone sent
			}
		}

	}
