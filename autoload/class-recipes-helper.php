<?php
	/**
	 * This is a helper class for identifying recipes
	 *
	 * @since 1.0.0
	 */

	namespace Spillt;

	use Mediavine\Create\Reviews_Models;
	use Mediavine\Create\Creations;

	class RecipesHelper {

		public static function manual_sync_recipe_data_to_spillt( $recipe, $recipe_parent, $should_broadcast ) {
			// Categories formation.
			$categories = array();
			$taxonomy = $selected = get_option('spillt_author_tax_choosen')?get_option('spillt_author_tax_choosen'):'category';
			$get_categories = get_the_terms($recipe_parent->ID, $taxonomy);
			$recipe_id = $recipe->ID;
			if( !empty( $get_categories ) ) {
				foreach ( $get_categories as $category ) {

					// Skip category Uncategorized.
					if ( $category->term_id == 1 ) {
						continue;
					}

					$categories[] = html_entity_decode( $category->name, ENT_QUOTES );
				}
			}
			if( !empty( $categories ) ){
				// Prepare categories array to Supabase database format.
				$categories = str_replace( array('["', '"]'), array('{"', '"}'), json_encode( $categories ) );
			} else {
				$categories = '{""}';
			}

			$thumbnail = get_post_meta($recipe_parent->ID, 'og:image', true);
			if (empty($thumbnail)) {
				$thumbnail = get_the_post_thumbnail_url( $recipe->ID );
			} 

			$recipe_title = $recipe->post_title;

			// Ingredients formation.
			$ingredients = '{"No ingredients"}';
			if ( $recipe->post_type == 'wprm_recipe' ) {

				$get_ingredients = get_post_meta( $recipe_id, 'wprm_ingredients', true )[0]['ingredients'];

				if ( ! empty( $get_ingredients ) ) {

					$ingredients = [];

					foreach ( $get_ingredients as $ingredient ) {
						if ($ingredient['amount']) {
							$ingredient_string = $ingredient['amount'];
						}
						if (strlen($ingredient['unit']) > 0) {
							$ingredient_string = $ingredient_string.' '.$ingredient['unit'];
						}
						if (strlen($ingredient_string) > 0) {
							$ingredient_string = $ingredient_string.' '.$ingredient['name'];
						} else {
							$ingredient_string = $ingredient['name'];
						}
						if (strlen($ingredient['notes']) > 0) {
							$ingredient_string = $ingredient_string.', '.$ingredient['notes'];
						}
						$ingredients[] = html_entity_decode($ingredient_string, ENT_QUOTES);
					}

				}

			} elseif ( $recipe->post_type == 'tasty_recipe' ) {
				$get_ingredients = get_post_meta( $recipe_id, 'ingredients', true );


				if ( ! empty( $get_ingredients ) ) {

					$ingredients = preg_split( '/\r?\n\r?/', $get_ingredients, -1, PREG_SPLIT_NO_EMPTY );
					array_walk_recursive($ingredients, function (&$value) { $value = html_entity_decode($value, ENT_QUOTES); });

				}

			} elseif ( $recipe->post_type == 'mv_create' ) {
				$thumbnail = self::get_mediavine_recipe_thumbnail( $recipe_id );
				// Remove the ' Creation' from mediavine post titles 
				$recipe_title = substr($recipe_title, 0, -9);
				$get_ingredients = self::get_mediavine_recipe_ingredients( $recipe_id );
				if (!empty($get_ingredients))
					$ingredients = $get_ingredients;
			}

			// Prepare ingredients array to Supabase database format.
			$filtered_ingredients = array_values(array_filter($ingredients, function ($var) {
				if (is_string($var)) {
					return strlen($var) > 0;
				}
				return false;
			}));
			$encoded_ingredients = str_replace( array('["', '"]'), array('{"', '"}'), json_encode( $filtered_ingredients ) );

			$author_name = get_the_author_meta( $recipe->post_author );
			$blog_url = get_bloginfo('url');

			$recipe_data = array(
				'wp_recipe_title'  => html_entity_decode($recipe_title, ENT_QUOTES),
				'wp_canonical_url' => get_permalink($recipe_parent),
				'incoming_post_id' => $recipe_id,
				'wp_blog_url' 	   => $blog_url,
				'wp_thumbnail_url' => $thumbnail,
				'wp_ingredients'   => $encoded_ingredients,
				'wp_author_name'   => html_entity_decode($author_name, ENT_QUOTES),
				'wp_categories'    => $categories,
				'should_broadcast' => $should_broadcast,
			);

			$response = SupaBase::insert_recipe( $recipe_data );

			// 200 - recipe is inserted first time. 400 - recipe is already exists.
			if( isset($response['code']) && $response['code'] == '200' ){
				update_post_meta( $recipe_id, 'date_synced_to_spillt', current_time('mysql') );
				update_post_meta( $recipe_id, 'spillt_recipe_sync', 'true' );
				return $response;
			} else {
				return $response;
			}
		}

		public static function pull_recipe_comments_from_spillt() {

			// Get Supabase recipe reviews.
			$response = SupaBase::get_reviews( get_bloginfo('url') );

			if( isset($response['code']) && $response['code'] == 200 ) {
				// Loop each review of recipe.
				foreach ( $response['reviews'] as $review ) {
					$recipe_id = $review['wp_post_id'];
					if (!$recipe_id) {
						continue;
					}

					$recipe = get_post($recipe_id);
					$recipe_parent_id = null;
					
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
					}
					$parent_post = get_post( $recipe_parent_id );
					
					if (!$recipe || !$parent_post) {
						continue;
					}

					$data = [
						'comment_post_ID'      => $recipe_parent_id,
						'comment_author'       => wp_slash( $review['wp_name'] ?? 'Spillt User' ),
						'comment_content'      => wp_slash( $review['review_text'] ),
						'comment_date'         => iso8601_to_datetime( $review['created_at'] ),
						'comment_approved'     => 0,
						'comment_author_email' => wp_slash( $review['wp_email'] ?? '' ),
						'comment_author_url'   => '',
					];

					// Add new comment for post. Function prevent duplicate identical comments for one posts post.
					$comment_id = wp_new_comment( $data, true );

					if ( ! is_wp_error( $comment_id ) ) {
						if ( $recipe->post_type == 'mv_create' ) {
							$creation_ids = Creations::get_creation_ids_by_post($recipe_parent_id, ['recipe']);
							if (empty( $creation_ids) ) {
								return;
							}
							$Reviews_Models = new Reviews_Models();
							// Build review
							$review = [
								'author_email'   => wp_slash( $review['wp_email'] ?? '' ),
								'author_name'    => wp_slash( $review['wp_name'] ?? 'Spillt User' ),
								'rating'         => $review['rating'],
								'review_content' => wp_slash( $review['review_text'] ),
								'creation'       => $creation_ids[0],
							];
							$Reviews_Models->create_review( $review );
						} elseif ( $recipe->post_type == 'wprm_recipe' ){
							update_comment_meta( $comment_id, 'wprm-comment-rating', $review['rating'] );
						} elseif ( $recipe->post_type == 'tasty_recipe' ) {
							update_comment_meta( $comment_id, 'ERRating', $review['rating'] );
						}
						update_comment_meta( $comment_id, 'spillt_comment', '' );
					}
				}
			}

			return $response;
		}



		/**
		 * Get Mediavine Recipe Ingredients
		 *
		 * @param int $post_id
		 *
		 * @return array|false
		 */
		public static function get_mediavine_recipe_ingredients ( $post_id ) {
			global $wpdb;
			$table_name_1 = $wpdb->prefix . 'mv_creations';
			$table_name_2 = $wpdb->prefix . 'mv_supplies';
			$type = 'ingredients';
			$prepared_statement = $wpdb->prepare( "SELECT original_text FROM {$table_name_2} as supplies INNER JOIN {$table_name_1} as creation ON creation.id = supplies.creation WHERE creation.object_id = %d AND supplies.type = %s", (int)$post_id, $type );
			$recipe_ingredients = $wpdb->get_col( $prepared_statement );

			if( !empty($recipe_ingredients) )
				return $recipe_ingredients;

			return false;
		}

		/**
         * Get Mediavine recipe Thumbnail
         *
		 * @param int $post_id
		 *
		 * @return string
		 */
		public static function get_mediavine_recipe_thumbnail ( $post_id ) {
			global $wpdb;
			$thumbnail_url = '';
			$table_name_1 = $wpdb->prefix . 'mv_creations';
			$type = 'recipe';
			$prepared_statement = $wpdb->prepare( "SELECT thumbnail_id FROM {$table_name_1} as creation WHERE creation.object_id = %d AND creation.type = %s", (int)$post_id, $type );
			$recipe_thumbnails = $wpdb->get_col( $prepared_statement );
			if (is_array($recipe_thumbnails))
				$recipe_thumbnails = array_filter($recipe_thumbnails);

			if( !empty($recipe_thumbnails) ) {
				$thumbnail_id = $recipe_thumbnails[0];
				$thumbnail_url = wp_get_attachment_image_url( (int)$thumbnail_id, 'full', false );
			}

			return $thumbnail_url;

		}

		public static function get_tasty_recipe_thumbnail( $post_id ) {

		}

		public static function find_tasty_recipe_id($post_content) {
				// Match either `<!-- wp:wp-tasty/tasty-recipe {"id": $post_id` or `[tasty-recipe id="$post_id"`
				$regex = '/(?:<!--\s*wp:wp-tasty\/tasty-recipe\s*{\s*"id"\s*:\s*(\d+)\s*)|(?:\[tasty-recipe\s+id\s*=\s*"(\d+)"\s*\])/';
				$matches = array();
				if (preg_match($regex, $post_content, $matches)) {
				  // Return the first captured group that is not empty
				  return isset($matches[1]) && !empty($matches[1]) ? $matches[1] : $matches[2];
				} else {
				  return null;
				}
		}

		/**
		 * Check if Tasty Recipe has parents.
		 * Return parents IDs array if has.
		 *
		 * @param $post_id
		 * @param $force_check re-run the query to ensure most up to date post
		 *
		 * @return bool|array
		 */
		public static function get_tasty_recipe_has_parent( $post_id, $force_check = false ) {
			$tasty_parent_array = get_post_meta( $post_id, 'spillt_tasty_parent_id', true );

			if ( !$tasty_parent_array || $force_check ) {
				global $wpdb;
				$table_name = $wpdb->prefix . 'posts';
				$tasty_parent_array = $wpdb->get_results("SELECT ID FROM $table_name WHERE post_type != 'revision' AND (post_content LIKE '%<!-- wp:wp-tasty/tasty-recipe {\"id\":$post_id,%' OR post_content LIKE '%[tasty-recipe id=\"$post_id\"%')", ARRAY_N);
	
				if ( empty($tasty_parent_array) ) {
					update_post_meta( $post_id, 'spillt_tasty_parent_id', null );
					return false;
				} else {
					update_post_meta( $post_id, 'spillt_tasty_parent_id', $tasty_parent_array );
				}
			}

			if ( !empty($tasty_parent_array) ) {
				$tasty_recipe_parent_id = (int)reset($tasty_parent_array)[0];
				$tasty_parent = get_post($tasty_recipe_parent_id);
				if ( $tasty_parent->post_status == 'publish' ) {
					return $tasty_parent_array;
				}
			}

			return false;

		}

		/**
		 * @param int $post_id
		 *
		 * @return array|false
		 */
		public static function get_mediavine_has_parent( $post_id ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'mv_creations';
			$type = 'recipe';
			$prepared_statement = $wpdb->prepare( "SELECT canonical_post_id FROM {$table_name} WHERE  type = %s AND object_id = %d ", $type, (int)$post_id );
			$mediavine_recipes_parent = $wpdb->get_col( $prepared_statement );
			if (is_array($mediavine_recipes_parent))
				$mediavine_recipes_parent = array_filter($mediavine_recipes_parent);

            // Add check to make sure that the parent is also published
			if ( !empty($mediavine_recipes_parent) ) {
                $recipe_parent_id = $mediavine_recipes_parent[0];
                $recipe_parent = get_post($recipe_parent_id);
                if ($recipe_parent->post_status == 'publish') {
                    return $mediavine_recipes_parent;
                }
            }

			return false;
		}

		public static function get_mediavine_child( $post_id ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'mv_creations';
			$type = 'recipe';
			$int_post_id = intval($post_id);
			$prepared_statement = $wpdb->prepare( "SELECT object_id FROM {$table_name} WHERE type = %s AND canonical_post_id = {$int_post_id}", $type );
			$mediavine_child_recipe = $wpdb->get_col( $prepared_statement );
			if (is_array($mediavine_child_recipe)) {
				return array_filter($mediavine_child_recipe);
			}
			
			return false;
		}

		public static function is_create_active() {
			include_once(ABSPATH.'wp-admin/includes/plugin.php');
			return is_plugin_active('mediavine-create/mediavine-create.php');
		}

		public static function is_wprm_active() {
			include_once(ABSPATH.'wp-admin/includes/plugin.php');
			return is_plugin_active('wp-recipe-maker/wp-recipe-maker.php');
		}

		public static function is_tasty_active() {
			include_once(ABSPATH.'wp-admin/includes/plugin.php');
			return is_plugin_active('tasty-recipes/tasty-recipes.php');
		}


		public static function get_all_syncable_recipes() {
			if ( self::is_create_active() ) {
				$create_recipe = get_posts( [
					'numberposts' => -1,
					'post_type' => ['mv_create'],
					'post_status' => ['publish'],
					'meta_query' => [
						// If recipe sync enabled (or is null,
						// which indicates it is a new recipe).
						'relation' => 'OR',
						[
							'key' => 'spillt_recipe_sync',
							'value' => 'true',
						],
						[
							'key' => 'spillt_recipe_sync',
							'compare' => 'NOT EXISTS',
						]
					]
				] );
			} else {
				$create_recipe = [];
			}

			if ( self::is_wprm_active() ) {				
				$wprm_recipe = get_posts( [
					'numberposts' => -1,
					'post_type' => ['wprm_recipe'],
					'post_status' => ['publish'],
					'meta_query' => [
						'relation' => 'AND',
						[
							// If recipe sync enabled (or is null,
							// which indicates it is a new recipe).
							'relation' => 'OR',
							[
								'key' => 'spillt_recipe_sync',
								'value' => 'true',
							],
							[
								'key' => 'spillt_recipe_sync',
								'compare' => 'NOT EXISTS',
							]
						],
						[
							// If widget of recipe used in any post.
							'key' => 'wprm_parent_post_id',
							'compare' => 'EXISTS',
						],
					]
				] );
			} else {
				$wprm_recipe = [];
			}

			if ( self::is_tasty_active() ) {				
				$tasty_recipe = get_posts( [
					'numberposts' => -1,
					'post_type' => ['tasty_recipe'],
					'post_status' => ['publish'],
					'meta_query' => [
						// If recipe sync enabled (or is null,
						// which indicates it is a new recipe).
						'relation' => 'OR',
						[
							'key' => 'spillt_recipe_sync',
							'value' => 'true',
						],
						[
							'key' => 'spillt_recipe_sync',
							'compare' => 'NOT EXISTS',
						]
					]
				] );
			} else {
				$tasty_recipe = [];
			}
			if ( !empty($tasty_recipe) ){
				foreach ( $tasty_recipe as $tk => $t_r ){
					if ( !self::get_tasty_recipe_has_parent( $t_r->ID ) ){
						unset($tasty_recipe[$tk]);
					}
				}
			}
			if ( !empty($create_recipe) ){
				foreach ( $create_recipe as $ck => $c_r ){
					if ( !SpilltRecipeListing::mediavine_create_is_recipe( $c_r->ID ) ||
					     !self::get_mediavine_has_parent( $c_r->ID ) ){
						unset($create_recipe[$ck]);
					}
				}
			}

			$all_recipe = array_merge( $wprm_recipe, $tasty_recipe, $create_recipe );

			return $all_recipe;

		}

		public static function get_unsynced_recipes() {
			$create_recipe = [];
			$wprm_recipe = [];
			$tasty_recipe = [];

			if ( self::is_create_active() ) {
				$create_recipe = get_posts( [
					'numberposts' => -1,
					'post_type' => ['mv_create'],
					'post_status' => ['publish'],
					'meta_query' => [
						'relation' => 'AND',
						[
							// If recipe sync enabled (or is null,
							// which indicates it is a new recipe).
							'relation' => 'OR',
							[
								'key' => 'spillt_recipe_sync',
								'value' => 'true',
							],
							[
								'key' => 'spillt_recipe_sync',
								'compare' => 'NOT EXISTS',
								]
						],
						[
							// Only return recipes that have not been synced
							'key' => 'date_synced_to_spillt',
							'compare' => 'NOT EXISTS',
						],
					]
				] );
			}

			if ( self::is_wprm_active() ) {
				$wprm_recipe = get_posts( [
					'numberposts' => -1,
					'post_type' => ['wprm_recipe'],
					'post_status' => ['publish'],
					'meta_query' => [
						'relation' => 'AND',
						[
							// If recipe sync enabled (or is null,
							// which indicates it is a new recipe).
							'relation' => 'OR',
							[
								'key' => 'spillt_recipe_sync',
								'value' => 'true',
							],
							[
								'key' => 'spillt_recipe_sync',
								'compare' => 'NOT EXISTS',
							]
						],
						[
							// If widget of recipe used in any post.
							'key' => 'wprm_parent_post_id',
							'compare' => 'EXISTS',
						],
						[
							// Only return recipes that have not been synced
							'key' => 'date_synced_to_spillt',
							'compare' => 'NOT EXISTS',
						],
					]
				] );
			}
			if ( self::is_tasty_active() ) {
				$tasty_recipe = get_posts( [
					'numberposts' => -1,
					'post_type' => ['tasty_recipe'],
					'post_status' => ['publish'],
					'meta_query' => [
						'relation' => 'AND',
						[
							// If recipe sync enabled (or is null,
							// which indicates it is a new recipe).
							'relation' => 'OR',
							[
								'key' => 'spillt_recipe_sync',
								'value' => 'true',
							],
							[
								'key' => 'spillt_recipe_sync',
								'compare' => 'NOT EXISTS',
							]
						],
						[
							// Only return recipes that have not been synced
							'key' => 'date_synced_to_spillt',
							'compare' => 'NOT EXISTS',
						],
					]
				] );
			}
			if ( !empty($tasty_recipe) ){
				foreach ( $tasty_recipe as $tk => $t_r ){
					if ( !self::get_tasty_recipe_has_parent( $t_r->ID ) ){
						unset($tasty_recipe[$tk]);
					}
				}
			}
			if ( !empty($create_recipe) ){
				foreach ( $create_recipe as $ck => $c_r ){
					if ( !SpilltRecipeListing::mediavine_create_is_recipe( $c_r->ID ) ||
					     !self::get_mediavine_has_parent( $c_r->ID ) ){
						unset($create_recipe[$ck]);
					}
				}
			}

			$all_recipe = array_merge( $wprm_recipe, $tasty_recipe, $create_recipe );

			return $all_recipe;
		}

        public static function get_all_possible_recipes() {
			$create_recipe = [];
			$wprm_recipe = [];
			$tasty_recipe = [];

			if ( self::is_create_active() ) {
				$create_recipe = get_posts( [
					'numberposts' => -1,
					'post_type' => ['mv_create'],
					'post_status' => ['publish']
				] );
			}

			if ( self::is_wprm_active() ) {
				$wprm_recipe = get_posts( [
					'numberposts' => -1,
					'post_type' => ['wprm_recipe'],
					'post_status' => ['publish']
				] );
			}

			if ( self::is_tasty_active() ) {
				$tasty_recipe = get_posts( [
					'numberposts' => -1,
					'post_type' => ['tasty_recipe'],
					'post_status' => ['publish']
				] );
			}

			if ( !empty($tasty_recipe) ){
				foreach ( $tasty_recipe as $tk => $t_r ){
					if ( !self::get_tasty_recipe_has_parent( $t_r->ID ) ){
						unset($tasty_recipe[$tk]);
					}
				}
			}
			if ( !empty($create_recipe) ){
				foreach ( $create_recipe as $ck => $c_r ){
					if ( !SpilltRecipeListing::mediavine_create_is_recipe( $c_r->ID ) ||
					     !self::get_mediavine_has_parent( $c_r->ID ) ){
						unset($create_recipe[$ck]);
					}
				}
			}

			$all_recipe = array_merge( $wprm_recipe, $tasty_recipe, $create_recipe );

			return $all_recipe;
		}

		public static function get_recipe_from_post( $post, $post_id ) {
			
			$recipe = null;

			if (self::is_wprm_active()) {
				$recipes_array = get_posts( [
					'numberposts' => -1,
					'post_type' => ['wprm_recipe'],
					'post_status' => ['publish'],
					'meta_query' => [
						[
							'key' => 'wprm_parent_post_id',
							'value' => $post_id,
							'compare' => '='
						]
					]
				] );

				if (!empty($recipes_array)) {
					$recipe = $recipes_array[0];
				}
			}

			if ($recipe == null && self::is_create_active()) {
				$create_recipe_ids = self::get_mediavine_child($post_id);
				if (!empty( $create_recipe_ids )) {
					$recipe = get_post( array_shift( $create_recipe_ids ) );
				}
			}

			if ($recipe == null && self::is_tasty_active()) {
				$recipe_id = self::find_tasty_recipe_id($post->post_content);
				if ($recipe_id !== null) {
					$recipe = get_post($recipe_id);
				}
			}

			return $recipe;

		}

	}
