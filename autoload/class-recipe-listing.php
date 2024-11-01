<?php
	namespace Spillt;

	use JetBrains\PhphStorm\ArrayShape;

	defined( 'ABSPATH' ) || exit;
	/**
	 * Adding WP List table class if it's not available.
	 */
	if ( ! class_exists( \WP_List_Table::class ) ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
	}

	class SpilltRecipeListing extends \WP_List_Table  {
		protected static $instance = null;
		public function __construct(){
			parent::__construct( array(
				'singular'  => 'Spillt Recipes List',     //singular name of the listed records
				'plural'    => 'Spillt Recipes List',    //plural name of the listed records
				'ajax'      => true

			) );
		}
		public static function instance() {

			if ( null == self::$instance ) {
				self::$instance = new self;
			}
			return self::$instance;
		}
		/**
		 * Get list columns.
		 *
		 * @return array
		 */
		public function get_columns() {
			return array(
				'cb'                            => '<input type="checkbox" />',
				'recipe_title'                  => wp_strip_all_tags( __( 'Title' ) ),
				'recipe_author'                 => wp_strip_all_tags( __( 'Author' ) ),
				'recipe_spillt_app_status'      => wp_strip_all_tags( __( 'Visible in Spillt' ) ),
				'recipe_date'                   => wp_strip_all_tags( __( 'Date' ) ),
				'recipe_system'                 => wp_strip_all_tags( __( 'Recipe Plugin' ) ),
			);
		}
		/**
		 * Column cb.
		 *
		 * @param  array $item Item data.
		 * @return string
		 */
		public function column_cb( $item ) {
			return sprintf(
				'<input type="checkbox" name="bulk-spillt-recipe[]" value="%s" />', $item['ID']
			);
		}
		/**
		 * Display text for when there are no items.
		 */
		public function no_items() {
			esc_html_e( 'No recipes found.', 'spillt' );
		}
		/**
		 * Include the columns which can be sortable.
		 *
		 * @return array  Return array of sortable columns.
		 */
		public function get_sortable_columns() {
			return array(
				'recipe_title'      => array('recipe_title',true),
				'recipe_date'       => array('recipe_date',true),
				'recipe_system'		=> array('recipe_system', true)
			);
		}
		/**
		 * Table Navigation Display
		 *
		 * @param string $which
		 */
		protected function display_tablenav( $which ) {
			?>
            <div class="tablenav <?php echo esc_attr( $which ); ?>">

				<?php if ( $this->has_items() ) : ?>
                    <div class="alignleft actions bulkactions">
						<?php $this->bulk_actions( $which ); ?>
                    </div>
				<?php
				endif;
					$this->extra_tablenav( $which );
					$this->pagination( $which );
				?>

                <br class="clear" />
            </div>
			<?php
		}
		/**
		 * Additional Navigation Element
		 * "Manually Sync"
		 *
		 * @param string $which
		 */
		protected function extra_tablenav( $which ) {

			if ( 'bottom' === $which ) {
				?>
                <div class="alignleft actions custom">
                    <div id="manually-sync" class="button">
						<?php echo 'Manually Sync'; ?>
                    </div>
                </div>
                <div class="alignleft actions custom">
                    <div id="manually-sync-back" class="button">
						<?php echo 'Manually Sync ( Background Task )'; ?>
                    </div>
                </div>

				<?php
			}
		}
		/**
		 * Get list of hidden columns.
		 *
		 * @return array
		 */
		public function get_hidden_columns()
		{
			// Setup Hidden columns and return them
			return array('ID' => 'ID');
		}
		/**
		 * Get bulk actions.
		 *
		 * @return array
		 */
		public function get_bulk_actions()
		{

			return array(
				'add_to_spillt'         => 'Add to Spillt',
				'remove_from_spillt'    => 'Remove from Spillt',
			);
		}

		/**
		 * The Default columns
		 *
		 * @param  array  $item        The Item being displayed.
		 * @param  string $column_name The column we're currently in.
		 * @return string              The Content to display
		 */
		public function column_default( $item, $column_name )

		{

			switch( $column_name ) {

				case 'recipe_title':
					return "<strong>".$item[$column_name]."</strong>";
				case 'recipe_author':
					return "<strong>".get_the_author_meta('display_name', $item[$column_name])."</strong>";
					break;
				case 'recipe_spillt_app_status':
					if ( $item[$column_name] == 'true' ){
						return '<img src="'. PLUGIN_URL . '/assets/img/check-mark.png">';
					} elseif ($item[$column_name] == 'false' ){
						return '<img src="'. PLUGIN_URL . '/assets/img/x-mark.png">';
					}
					break;
				case 'recipe_date':
					return "<strong>Last Modified <br>".date(' y/m/d \a\t g:i a ', strtotime($item[$column_name])).'</strong>';
				case 'recipe_system':
					if ($item[$column_name] == "wprm_recipe") {
						return "<strong>WP Recipe Maker</strong>";
					} else if($item[$column_name] == 'mv_create') {
						return "<strong>Create by Mediavine</strong>";
					} else if($item[$column_name] == 'tasty_recipe') {
						return "<strong>Tasty Recipe</strong>";
					} else {
						return "<strong>" . $item[ $column_name ] . "</strong>";
					}
					break;
				default:
					return print_r( $item, true ) ;

			}

		}

		/**
		 * Check if Mediavine post is recipe.
		 *
		 * @param   int $post_id       Post Id to check
		 *
		 * @return  bool
		 */
		public static function mediavine_create_is_recipe ( $post_id ){
			global $wpdb;
			$table_name = $wpdb->prefix . 'mv_creations';
			$type = 'recipe';
			$prepared_statement = $wpdb->prepare( "SELECT object_id FROM {$table_name} WHERE  type = %s", $type );
			$mediavine_recipes = $wpdb->get_col( $prepared_statement );

			if( !empty($mediavine_recipes) ) {
				return in_array( $post_id, $mediavine_recipes );
			}

			return false;
		}
		/**
		 * Table data
		 * @return array
		 */
		private function table_data()
		{
			$spillt_recipes = RecipesHelper::get_all_possible_recipes();

			$name = $author = $date = $status = $post_type = [];
			$field_name_two = [];
			$i = 0;
			$skip_not_recipe = true; // skip not 'recipe' type for mediavine create
			$data = [];
			foreach ( $spillt_recipes as $spillt_recipe ) {
				$recipe_title = $spillt_recipe->post_title;
				if ( $spillt_recipe->post_type == 'mv_create' ) {
					$skip_not_recipe = self::mediavine_create_is_recipe( $spillt_recipe->ID );
					$recipe_title = substr($recipe_title, 0, -9);
				} else {
					$skip_not_recipe = true;
				}

				if ( $skip_not_recipe ) {
					$id[]        = $spillt_recipe->ID;
					$name[]      = $recipe_title . ' (' . $spillt_recipe->post_status . ')';
					$author[]    = $spillt_recipe->post_author;
					$date[]      = $spillt_recipe->post_date;
					$status[]    = get_post_meta( $spillt_recipe->ID, 'spillt_recipe_sync', true );
					$post_type[] = $spillt_recipe->post_type;

					$data[] = [
						'ID'                       => $id[ $i ],
						'recipe_title'             => $name[ $i ],
						'recipe_author'            => $author[ $i ],
						'recipe_spillt_app_status' => $status[ $i ],
						'recipe_date'              => $date[ $i ],
						'recipe_system'            => $post_type[ $i ],
					];
					$i ++;
				}
			}
			return $data;

		}


		/**
		 * Prepare the data for the WP List Table
		 *
		 * @return void
		 */
		public function prepare_items()
		{

			global $wpdb;

			$columns = $this->get_columns();

			$sortable = $this->get_sortable_columns();

			$hidden=$this->get_hidden_columns();

			$this->process_bulk_action();

			$data = $this->table_data();

			$totalitems = count($data);

			$user = get_current_user_id();

			$screen = get_current_screen();

			$option = $screen->get_option('per_page', 'option');
			$perpage = get_user_meta($user, $option, true);

			$this->_column_headers = array($columns,$hidden,$sortable);

			if ( empty ( $perpage) || $perpage < 1 ) {

				$perpage = $screen->get_option( 'per_page', 'default' );

			}

			usort($data, array('Spillt\SpilltRecipeListing', 'usort_reorder') );
			$totalpages = ceil($totalitems/$perpage);

			$currentPage = $this->get_pagenum();

			$data = array_slice($data,(($currentPage-1)*$perpage),$perpage);

			$this->set_pagination_args( array(

				"total_items" => $totalitems,

				"total_pages" => $totalpages,

				"per_page" => $perpage,
			) );
			$this->items = $data;
		}
		/**
		 * Usort Data
		 *
		 * @param $a
		 * @param $b
		 *
		 * @return float|int
		 */
		public static function usort_reorder($a,$b){

			$orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'recipe_title'; //If no sort, default to title

			$order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'desc'; //If no order, default to asc

			$result = strcmp($a[$orderby], $b[$orderby]); //Determine sort order

			return ($order==='asc') ? $result : -$result; //Send final sort direction to usort

		}
	}
