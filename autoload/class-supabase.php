<?php
	/**
	 * This is responsible for processing AJAX or other requests
	 *
	 * @since 1.0.0
	 */

	namespace Spillt;


	//prevent direct access data leaks
	use JetBrains\PhpStorm\ArrayShape;

	defined( 'ABSPATH' ) || exit;

	class SupaBase {
		const AUTH_URL      = 'https://nnzjbkfbsjavilzhdkjs.supabase.co/auth/v1/';
		const API_URL       = 'https://nnzjbkfbsjavilzhdkjs.supabase.co/rest/v1/rpc/';
		const PUBLIC_KEY    = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJyb2xlIjoiYW5vbiIsImlhdCI6MTYyMzI2ODM5MiwiZXhwIjoxOTM4ODQ0MzkyfQ.2Mw3g50au1uNISAkE9KunAy6m6cKxAQEvlUIrLVIneQ';
		protected static $instance = null;

		public function __construct(){
			add_action( 'admin_post_get_author_form', __CLASS__ . '::authorization_form_handler' );
		}

		public static function instance() {

			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}
			return self::$instance;
		}

		/**
		 * @param string    $type
		 *
		 * @return string
		 */
		public static function get_url( $type='api' ) {
			if ($type === 'api')
				return self::API_URL;

			if ($type === 'auth')
				return self::AUTH_URL;
		}

		/**
		 * @return string
		 */
		private static function get_public_key() {
			return self::PUBLIC_KEY;
		}

		/**
		 * @param string    $type
		 * @param string    $endpoint
		 *
		 * @return string
		 */
		private static function get_path(string $type, string $endpoint ) {
			return self::get_url($type) . trim($endpoint, '/');
		}

		/**
		 * @return void
		 */
		private static function refresh_access_token() {
			$expiration_date = get_option( '_spilt_author_access_token_expiration_date' );
			if ( $expiration_date < time() ):
				$refresh_token = get_option( '_spilt_author_refresh_token' );
				$response = null;
				if ($refresh_token) {
					$request_url = self::get_path('auth', 'token?grant_type=refresh_token');
					$headers     = self::get_request_headers( true );
					$response    = self::send_request( $request_url,$headers, [
							'refresh_token'    => $refresh_token,
					]);
				}
				if (isset($response['access_token']) && isset($response['refresh_token']) && isset($response['expires_in'])){
					update_option('_spillt_blog_user_supabase_token_response', $response, false);
					update_option('_spilt_author_refresh_token', $response['refresh_token'], false);
					update_option('_spilt_author_access_token', $response['access_token'], false);
					update_option('_spilt_author_access_token_expiration_date', (time() + $response['expires_in']), false);
				} else {
					update_option('spillt_error_show', 'true');
					add_settings_error( 'spillt_author_phone', 'settings_error', 'Please log in again by going to Spillt > Settings > Authorize Site.', 'error' );
					update_option('_spillt_blog_user_supabase_token_response', $response, false);
					update_option('_spilt_author_refresh_token', null, false);
					update_option('_spilt_author_access_token', null, false);
					update_option('_spilt_author_access_token_expiration_date', 0, false);
				}
			endif;
		}

		/**
		 * @return false|mixed|void
		 */
		private static function get_bearer_token() {
			return get_option( '_spilt_author_access_token' );
		}

		/**
		 * @return string
		 */
		private static function get_auth_key() {
			return 'Bearer '.self::get_bearer_token();
		}

		/**
		 * @param bool $public
		 *
		 * @return array
		 */
		private static function get_request_headers(bool $public=false ){
			if ( $public ) {
				return array(
					'Content-Type' => 'application/json',
					'apikey' => self::get_public_key()
				);
			} else {
				self::refresh_access_token();
				return array(
					'Content-Type' => 'application/json',
					'apikey' => self::get_public_key(),
					'Authorization' => self::get_auth_key(),
					'Prefer' => 'return=representation'
				);
			}
		}

		/**
		 * @param string    $event
		 *
		 * @return void
		 */
		public static function log_event(string $event, string $data = '') {
			$blog_url = get_bloginfo( 'url' );
			$request_url = self::get_path('api', 'log_wordpress');
			$headers = self::get_request_headers( false );
			self::send_request($request_url, $headers, [
				'blog_url' => $blog_url,
				'log_text' => $event,
				'additional_data' => $data
			]);
		}

		/**
		 * @param string    $author_phone
		 *
		 * @return array
		 */
		public static function send_sms(string $author_phone) {
			$request_url = self::get_path('auth', 'otp');
			$headers     = self::get_request_headers( true );
			$response    = self::send_request( $request_url,$headers, [
					'phone'            => $author_phone
			]);

			return $response;
		}

		/**
		 * @param string    $author_phone
		 * @param string    $sms_code
		 *
		 * @return array
		 */
		public static function verify_sms(string $author_phone, string $sms_code) {
			$request_url = self::get_path('auth', 'verify');
			$headers     = self::get_request_headers( true );
			$response    = self::send_request( $request_url, $headers, [
					'type'             => 'sms',
					'phone'            => $author_phone,
					'token'            => $sms_code
			]);
			return $response;
		}

		/**
		 * @param string    $author_phone
		 * @param string    $blog_url
		 * @param string    $blog_name
		 *
		 * @return array
		 */
		public static function insert_author($author_phone = '', $blog_url = '', $blog_name = '') {

			// Send request to Supabase
			$request_url = self::get_path('api', 'insert_author');
			$headers     = self::get_request_headers( false );
			$response    = self::send_request( $request_url, $headers, [
					'user_phone_number' => $author_phone,
					'blog_url' 			=> $blog_url,
					'blog_name'			=> $blog_name,
			]);

			return $response;
		}

		/**
		 * @param string    $blog_url
		 * @param string    $author_phone
		 *
		 * @return array
		 */
		public static function get_author_id($author_phone = '', $blog_url = '') {

			// Send request to Supabase
			$request_url = self::get_path('api', 'get_author_id');
			$headers     = self::get_request_headers( false );
			$response    = self::send_request( $request_url, $headers, [
					'user_phone_number' => $author_phone,
					'blog_url' 			=> $blog_url,
			]);

			return $response;
		}

		/**
		 * @param array     $recipe
		 *
		 * @return array
		 */
		public static function insert_recipe( $recipe = array() ) {

			// Send request to Supabase
			$request_url = self::get_path( 'api', 'insert_wp_recipe');
			$headers     = self::get_request_headers( false );
			$response    = self::send_request( $request_url, $headers, [
					'wp_recipe_title'  => $recipe['wp_recipe_title'],
					'wp_canonical_url' => $recipe['wp_canonical_url'],
					'incoming_post_id' => $recipe['incoming_post_id'],
					'wp_blog_url' 	   => $recipe['wp_blog_url'],
					'wp_thumbnail_url' => $recipe['wp_thumbnail_url'],
					'wp_ingredients'   => $recipe['wp_ingredients'],
					'wp_author_name'   => $recipe['wp_author_name'],
					'wp_categories'    => $recipe['wp_categories'],
					'should_broadcast' => $recipe['should_broadcast'],
			]);

			return $response;
		}

		/**
		 * @param string    $recipe_id
		 * @param string    $blog_url
		 *
		 * @return array
		 */
		public static function get_reviews( $blog_url = '' ){

			$request_url = self::get_path('api', 'get_wp_reviews_by_blog');
			$headers     = self::get_request_headers( false );
			$response    = self::send_request( $request_url, $headers, [
					'blog_url' 		   => $blog_url
			]);

			return $response;
		}

		/**
		 * @param string    $date
		 * @param string    $recipe_id
		 * @param string    $blog_url
		 *
		 * @return array
		 */
		public static function get_reviews_per_date($date = '', $recipe_id = '', $blog_url = '') {

			$request_url = self::get_path('api', 'get_wp_reviews_by_day');
			$headers     = self::get_request_headers( false );
			$response    = self::send_request( $request_url, $headers, [
					'created_at_date'  => $date,
					'post_id' 		   => $recipe_id,
					'blog_url' 		   => $blog_url
			]);

			return $response;
		}

		/**
		 * @param string    $recipe_id
		 * @param string    $blog_url
		 *
		 * @return array
		 */
		public static function remove_recipe( $recipe_id = '', $blog_url = '' ) {

			$request_url = self::get_path('api', 'remove_wp_recipe');
			$headers     = self::get_request_headers( false );
			$response    = self::send_request( $request_url, $headers, [
					'post_id' 		   => $recipe_id,
					'blog_url' 		   => $blog_url
			]);
			/*echo '<pre style="display: none;">';
			var_dump($response);
			echo '</pre>';*/
			return $response;
		}

		/**
		 * Post params data to Supabase by CURL.
		 *
		 * @param string    $url
		 * @param array     $headers
		 * @param array     $post_fields
		 *
		 * @return array
		 */
		public static function send_request ($url, $headers, $post_fields) {
			$args = array(
				'headers' => $headers,
				'body' => json_encode( $post_fields )
			);
			
			$response = wp_remote_post( $url, $args );
			
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				return array( 'success' => false, 'error' => $error_message );
			} else {
				$result = wp_remote_retrieve_body( $response );
				return json_decode( $result, true );
			}
		}
	}
