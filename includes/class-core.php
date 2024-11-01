<?php
/**
 * Main class which sets all together
 *
 * @since      1.0.0
 */

namespace Spillt;


class Core {

	protected static $instance = null;

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


	/**
	 * @since 1.0.0
	 * @throws \Exception
	 */
	public function __construct(){

		//autoload files from `/autoload`
		spl_autoload_register( __CLASS__ . '::autoload' );

		//include files from `/includes`
		self::includes();
		
		RecipesPage::instance();
        SettingsPage::instance();
        SupaBase::instance();
        Cron::instance();
	}



	/**
	 * Include files
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function includes(){

	}


	/**
	 * Init the action links available in plugins list page
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init_plugin_action_links(){

		//add plugin action and meta links
		self::plugin_links(array(
			'actions' => array(
				PLUGIN_SETTINGS_URL => __('Settings', 'spillt-app'),
				// admin_url('admin.php?page=wc-status&tab=logs') => __('Logs', 'spillt-app'),
				// admin_url('plugins.php?action='.PREFIX.'_check_updates') => __('Check for Updates', 'spillt-app')
			),
			'meta' => array(
				// '#1' => __('Docs', 'spillt-app'),
				// '#2' => __('Visit website', 'spillt-app')
			),
		));
	}


	public static function autoload($filename) {

		$dir = PLUGIN_DIR . '/autoload/class-*.php';
		$paths = glob($dir);

		if ( is_array($paths) && count($paths) > 0 ){
			foreach( $paths as $file ) {
				if ( file_exists( $file ) ) {
					include_once $file;
				}
			}
		}
	}



	/**
	 * Add plugin action and meta links
	 *
	 * @since 1.0.0
	 * @param array $sections
	 * @return void
	 */
	private static function plugin_links($sections = array()) {

		//actions
		if (isset($sections['actions'])){

			$actions = $sections['actions'];
			$links_hook = is_multisite() ? 'network_admin_plugin_action_links_' : 'plugin_action_links_';

			add_filter($links_hook.PLUGIN_BASENAME, function($links) use ($actions){

				foreach(array_reverse($actions) as $url => $label){
					$link = '<a href="'.$url.'">'.$label.'</a>';
					array_unshift($links, $link);
				}

				return $links;

			});
		}

		//meta row
		if (isset($sections['meta'])){

			$meta = $sections['meta'];

			add_filter( 'plugin_row_meta', function($links, $file) use ($meta){

				if (PLUGIN_BASENAME == $file){

					foreach ($meta as $url => $label){
						$link = '<a href="'.$url.'">'.$label.'</a>';
						array_push($links, $link);
					}
				}

				return $links;

			}, 10, 2 );
		}

	}



	/**
	 * Run on plugin activation
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function on_activation(){

		if (version_compare(phpversion(), '7.0', '<')) {
			wp_die(sprintf(
				__('Hey! Your server must have at least PHP 7.0. Could you please upgrade. %sGo back%s', 'spillt-app'),
				'<a href="'.admin_url('plugins.php').'">',
				'</a>'
			));
		}

		if (version_compare(get_bloginfo('version'), '5.0', '<')) {
			wp_die(sprintf(
				__('We need at least Wordpress 5.0. Could you please upgrade. %sGo back%s', 'spillt-app'),
				'<a href="'.admin_url('plugins.php').'">',
				'</a>'
			));
		}
		
		// Remove old cron task if exists.
		wp_clear_scheduled_hook( 'spillt_sync_all_reviews' );
		
		// Add this new cron task again.
		wp_schedule_event( time(), 'daily', 'spillt_sync_all_reviews');
		
		// Spillt plugin first activation.
		if( get_option('spillt_activated') == false ) {
			
			$recipes = RecipesHelper::get_all_possible_recipes();
			
			// Init each recipe for sync status 'false'.
			foreach( $recipes as $recipe ){
				update_post_meta( $recipe->ID, 'spillt_recipe_sync', 'false' );
			}
			
			// Set plugin first activated.
			update_option('spillt_activated', true );
			
		} else {
			// Spillt plugin not first activation.
			// Assign sync status for WPRM recipes.
		
			// Get recipes with acceptable sync params.
			$recipes = RecipesHelper::get_all_syncable_recipes();

			// Init each recipe for sync status 'true'.
			foreach( $recipes as $recipe ){
				update_post_meta( $recipe->ID, 'spillt_recipe_sync', 'true' );
			}
				
			
			// Get recipes with not acceptable sync params.
			$recipes = RecipesHelper::get_unsynced_recipes();
			
			// Init each recipe for sync status 'false'.
			foreach( $recipes as $recipe ){
				update_post_meta( $recipe->ID, 'spillt_recipe_sync', 'false' );
			}
		}
	
	}
	


	/**
	 * Run on plugin deactivation
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function on_deactivation(){
	
		// Remove cron task.
		wp_clear_scheduled_hook( 'spillt_sync_all_reviews' );
		wp_clear_scheduled_hook( 'spillt_sync_new_recipes' );
	}



	/**
	 * Run when plugin is deleting
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function on_uninstall(){
	
		// Delete author authorization phone number.
		delete_option( 'spillt_author_phone' );
		delete_option( '_spilt_author_refresh_token' );
		delete_option( '_spilt_author_access_token' );
		delete_option( 'spillt_author_id' );
		delete_option( '_spillt_blog_user_supabase_token_response' );
		delete_option( '_spilt_author_access_token_expiration_date' );
	}

}
Core::instance();
