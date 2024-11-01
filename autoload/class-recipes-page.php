<?php
	/**
	 * This is responsible for processing AJAX or other requests
	 *
	 * @since 1.0.0
	 */

	namespace Spillt;

	use Mediavine\Create\Reviews_Models;

	class RecipesPage {

		protected static $instance = null;
		protected static $recipes_obj = null;

		/**
		 * @since 1.0.0
		 * @throws \Exception
		 */
		public function __construct(){
			add_action( 'admin_menu', __CLASS__ . '::menu_page_register' );
			add_filter( 'set-screen-option', __CLASS__ . '::set_option', 10, 3 );

			// Scripts connection.
			add_action( 'admin_enqueue_scripts',  __CLASS__ . '::enqueue_scripts' );

			// AJAX callback php.
			add_action( 'wp_ajax_sync_counter', __CLASS__ . '::sync_counter_callback' ); // sync-counter.js php
			// callback  bulk_actions_callback
			add_action( 'wp_ajax_spillt_bulk_actions', __CLASS__ . '::bulk_actions_callback' );
			//manual background sync
			add_action( 'wp_ajax_manually_background', __CLASS__ . '::manually_background_sync' );
			add_action( 'spillt_one_time_action_asap', __CLASS__ . '::one_time_function_asap', 10, 1 );

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

		public static function menu_page_register() {

			$icon = Constants::icon();
			$notification_errors = get_option('spillt_error_show') == 'true' ? 1 : 0;

			add_menu_page(
				'Spillt Recipes',
				$notification_errors ? sprintf( 'Spillt <span class="awaiting-mod">%d</span>', $notification_errors ) : 'Spillt',
				'',
				'spillt',
				NULL,
				$icon
			);
			$hook = add_submenu_page(
				'spillt',
				'Spillt',
				'Spillt',
				'manage_options',
				'spillt-main',
				array( __CLASS__, 'spillt_subpage_callback' )
			);
			add_action( "load-".$hook, array( __CLASS__, 'screen_option') );
		}

		public static function screen_option() {

			$option = 'per_page';
			$args   = [
				'default' => 20,
				'label'   => 'Recipes Per Page (up to 100)',
				'max' => 100,
				'min' => 1,
				'option'  => 'recipes_per_page',
				'step' => 1
			];

			add_screen_option( $option, $args );

			self::$recipes_obj = new SpilltRecipeListing();
		}

		public static function set_option( $status, $option, $value ) {
			if ( 'recipes_per_page' === $option ) {
				return $value;
			}
		}

		public static function spillt_subpage_callback () {

			if( isset($_GET['s']) ){
				self::$recipes_obj->prepare_items($_GET['s']);
			} else {
				self::$recipes_obj->prepare_items();
			}
			?>
            <div class="wrap">
                <h2><?php echo get_admin_page_title(); ?></h2>
				<?php
					settings_errors('posts_updated');
				?>

                <div id="poststuff">
                    <div id="post-body" class="metabox-holder">
                        <div id="post-body-content">
                            <div class="meta-box-sortables ui-sortable">
                                <form id="spillt-app-recipe-listing" method="post">
									<?php
										self::$recipes_obj->display();?>
                                </form>
                            </div>
                            <div id="manual_sync_result_wrap" class="overlay">
                                <div class="popup" style="width: 45%;">
                                    <h2 style="border-bottom: 3px solid #FFEC11;margin-bottom: 15px;">Manually Sync Process Running.</h2>
                                    <table id="manually-sync-result">
                                        <thead>
                                        <tr>
                                            <th>Title</th>  <!--<th>Code</th>-->
                                            <th>Message</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <br class="clear">
                </div>
            </div>
			<?php
		}


		public static function enqueue_scripts() {

			wp_enqueue_style( 'spilt-settings-styles', PLUGIN_URL .'/assets/css/spilt-style.css', array());
			wp_enqueue_script( 'sync-counter', PLUGIN_URL . '/assets/js/sync_counter.js', array('jquery'), '', true );
			wp_enqueue_script( 'ajax-script', PLUGIN_URL .'/assets/js/ajax-admin.js', array('jquery'), false );
			wp_localize_script( 'sync-counter', 'ajax_object',
				array( 'ajax_url' => admin_url( 'admin-ajax.php' )) );
		}

		/**
		 * Ajax manually sync recipes counter HENDLER.
		 */
		public static function sync_counter_callback(){

			if( isset($_POST['init_counter']) ){
				RecipesHelper::pull_recipe_comments_from_spillt();
				wp_send_json( RecipesHelper::get_all_syncable_recipes() );
			}
			if( isset($_POST['sync_recipe']) ){
				$result = array();
				$recipe = get_post( $_POST['id_recipe'] );
				switch ( $recipe->post_type ){
					case 'tasty_recipe':
						$recipe_parent_array = RecipesHelper::get_tasty_recipe_has_parent($recipe->ID);
						if ( $recipe_parent_array && is_array( $recipe_parent_array ) ) {
							$recipe_parent_id = (int)reset($recipe_parent_array)[0];
						}
						break;
					case 'wprm_recipe':
						$recipe_parent_id = get_post_meta( $recipe->ID, 'wprm_parent_post_id', true );
						break;
					case 'mv_create':
						$recipe_parent_array = RecipesHelper::get_mediavine_has_parent( $recipe->ID );
						$recipe_parent_id = $recipe_parent_array[0];
						break;
					default:
						$result['recipe'][]     = ['code'=>'400', 'message' => 'Recipe has an unsupported post type.' ];
						wp_send_json( $result );
						break;
				}

				$recipe_parent = get_post( $recipe_parent_id );

				if( $recipe_parent ){
					$recipe_sync = RecipesHelper::manual_sync_recipe_data_to_spillt( $recipe, $recipe_parent, false );

					if(isset($recipe_sync['message']) && strpos($recipe_sync['message'], 'WSError') !== false) {
						$result['recipe'][] = ['message' => 'Your access has expired. Please login again in the Spillt Settings page.'];
					} else {
						$result['recipe'][] = $recipe_sync;
					}
				} else {
					$result['recipe'][]         = ['code'=>'404', 'message' => 'Recipe doesn\'t have a parent and couldn\'t be added to Spillt App' ];
				}
				wp_send_json($result);
				die();
			}
		}

		public static function bulk_actions_callback(){
			if( isset($_POST['init_counter'] ) && isset($_POST['recipe_IDs'] ) ){
				RecipesHelper::pull_recipe_comments_from_spillt();
				$posts_ID = $_POST['recipe_IDs'];
				wp_send_json( get_posts(['post_type'=>['wprm_recipe','tasty_recipe','mv_create'],
				                         'include'=>$posts_ID]) );
			}
			if( isset($_POST['sync_recipe']) ){
				$result = array();
				$recipe = get_post( $_POST['id_recipe'] );
				if ($_POST['sync_recipe'] === 'add_to_spillt') {
					switch ( $recipe->post_type ) {
						case 'tasty_recipe':
							$recipe_parent_array = RecipesHelper::get_tasty_recipe_has_parent( $recipe->ID );
							if ( $recipe_parent_array && is_array( $recipe_parent_array ) ) {
								$recipe_parent_id = (int)reset($recipe_parent_array)[0];
							}
							break;
						case 'wprm_recipe':
							$recipe_parent_id = get_post_meta( $recipe->ID, 'wprm_parent_post_id', true );
							break;
						case 'mv_create':
							$recipe_parent_array = RecipesHelper::get_mediavine_has_parent( $recipe->ID );
							$recipe_parent_id = $recipe_parent_array[0];
							break;
						default:
							$result['recipe'][]     = ['code'=>'400', 'message' => 'Recipe has unsupported post type.' ];
							wp_send_json( $result );
							break;
					}

					$recipe_parent = get_post( $recipe_parent_id );

					if ( $recipe_parent ) {
						$recipe_sync                = RecipesHelper::manual_sync_recipe_data_to_spillt( $recipe, $recipe_parent, false );

						if(isset($recipe_sync['message']) && strpos($recipe_sync['message'], 'WSError') !== false) {
							$result['recipe'][] = ['message' => 'Your access has expired. Please login again in the Spillt Settings page.'];
						} else {
							$result['recipe'][] = $recipe_sync;
						}
					} else {
						$result['recipe'][]         = ['code'=>'404', 'message' => 'Recipe doesn\'t have parent and couldn\'t be added to Spillt App' ];
					}
					wp_send_json( $result );
				}
				if ($_POST['sync_recipe'] === 'remove_from_spillt') {
					$remove                         = SupaBase::remove_recipe( $recipe->ID, get_bloginfo('url') );
					if(isset($recipe_sync['message']) && strpos($recipe_sync['message'], 'WSError') !== false) {
						$result['recipe'][]             = ['message' => 'Your access has expired. Please login again in the Spillt Settings page.'];
                    } else {
						$result['recipe'][]             = $remove;
					}
					if ($remove['code'] == '200')
						update_post_meta( $recipe->ID, 'spillt_recipe_sync', 'false' );

					wp_send_json( $result );
				}
				die();
			}
		}

		public static function manually_background_sync(){

			if( isset($_POST['init_sync']) ) {
				$recipes = RecipesHelper::get_all_syncable_recipes();

				if ( !empty( $recipes ) ) {
					foreach ($recipes as $recipe):
						as_enqueue_async_action(
							'spillt_one_time_action_asap',
							[$recipe]
						);
					endforeach;
					wp_send_json( array( 'msg' => 'Background Task is Running', 'status' => '200' ) );
				}else {
					wp_send_json( array( 'msg' => 'No recipes found to sync.', 'status' => '404' ) );
				}
			} else {
				wp_send_json( array( 'msg' => 'Wrong action', 'status' => '400' ) );
			}
			die();
		}

		public static function one_time_function_asap( $recipe_data ) {
			$recipe = get_post( $recipe_data['ID'] );
			switch ( $recipe->post_type ){
				case 'tasty_recipe':
					$recipe_parent_array = RecipesHelper::get_tasty_recipe_has_parent($recipe->ID);
					if ( $recipe_parent_array && is_array( $recipe_parent_array ) ) {
						$recipe_parent_id = (int)reset($recipe_parent_array)[0];
					}
					break;
				case 'wprm_recipe':
					$recipe_parent_id = get_post_meta( $recipe->ID, 'wprm_parent_post_id', true );
					break;
				case 'mv_create':
					$recipe_parent_array = RecipesHelper::get_mediavine_has_parent( $recipe->ID );
					$recipe_parent_id = $recipe_parent_array[0];
					break;
				default:
					$result['recipe'][]     = ['code'=>'400', 'message' => 'Recipe has an unsupported post type.' ];
					break;
			}

			$recipe_parent = get_post( $recipe_parent_id );

			if( $recipe_parent ){
				$recipe_sync = RecipesHelper::manual_sync_recipe_data_to_spillt( $recipe, $recipe_parent, false );

				if(isset($recipe_sync['message']) && strpos($recipe_sync['message'], 'WSError') !== false) {
					$result['recipe'][] = ['message' => 'Your access has expired. Please login again in the Spillt Settings page.'];
				} else {
					$result['recipe'][] = $recipe_sync;
				}

				error_log( $recipe->post_title . ' - ' . $recipe_sync['message'].PHP_EOL, 3, ERROR_PATH );
			} else {
				error_log(  'Recipe doesn\'t have a parent and couldn\'t be added to Spillt App'.PHP_EOL, 3, ERROR_PATH );
			}

			RecipesHelper::pull_recipe_comments_from_spillt();
		}

	}
