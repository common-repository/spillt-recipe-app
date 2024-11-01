<?php
/**
 * This is responsible for processing AJAX or other requests
 *
 * @since 1.0.0
 */

namespace Spillt;


//prevent direct access data leaks
defined( 'ABSPATH' ) || exit;


class Cron {
	
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

	public function __construct(){
		add_action( 'spillt_sync_all_reviews', __CLASS__ . '::sync_all_reviews' );
		add_filter( 'cron_schedules', __CLASS__ . '::update_recipes_schedule' );

		// Fires Sync on post publish/update
		add_action( 'wp_after_insert_post', __CLASS__ . '::start_background_sync', 10, 2 );
		add_action( 'spillt_sync_new_post', __CLASS__ . '::sync_new_post', 10, 1 );
	}
	

	
	public static function start_background_sync($post_id, $post) {
		if ( !$post || $post->post_status != 'publish' ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$action = as_enqueue_async_action('spillt_sync_new_post', array($post_id) );
	}
	

	/**
     * @return boolean
     */
	public static function update_recipes_schedule( $schedules ){
		$schedules['sync_recipes_15_min'] = array(
			'interval' => 60 * 15,
			'display' => 'Every fifteen minutes.'
		);
		return $schedules;
	}

	public static function sync_all_reviews(){
		RecipesHelper::pull_recipe_comments_from_spillt();
	}

	public static function sync_new_post( $post_id ){
		$post = get_post($post_id);
		$recipe = RecipesHelper::get_recipe_from_post( $post, $post_id );

		if ($recipe != null) {
			$response = RecipesHelper::manual_sync_recipe_data_to_spillt( $recipe, $post, true );
			if ( isset( $response['message'] ) && strpos( $response['message'], 'WSError' ) !== false ) {
				update_option( 'spillt_error_show', 'true' );
			}
		}
	}
}
