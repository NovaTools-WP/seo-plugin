<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Cornerstone content management in the post/page list tables.
 *
 * Adds a "Cornerstone" column, a list-filter dropdown, and bulk actions to
 * mark posts as cornerstone (the most important content on a site). The flag
 * itself lives in the `_wseo_cornerstone` post meta key (registered in
 * MetaKeys::POST_ALL, so it persists through the standard meta save loops and
 * powers the dedicated sitemap-cornerstone.xml in the Generator).
 */
class Cornerstone {

	use Base;

	const META_KEY = '_wseo_cornerstone';
	const FILTER_QUERY_VAR = 'wseo_cornerstone';

	/**
	 * Post types the cornerstone column/filter apply to.
	 *
	 * @return string[]
	 */
	protected function get_post_types() {
		return array( 'post', 'page' );
	}

	/**
	 * Register the list-table hooks for each supported post type.
	 *
	 * @return void
	 */
	public function init() {
		foreach ( $this->get_post_types() as $post_type ) {
			add_filter( "manage_edit-{$post_type}_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
			add_action( "restrict_manage_posts", array( $this, 'render_filter_dropdown' ) );
			add_filter( "bulk_actions-edit-{$post_type}", array( $this, 'register_bulk_actions' ) );
			add_filter( "handle_bulk_actions-edit-{$post_type}", array( $this, 'handle_bulk_actions' ), 10, 3 );
		}

		add_action( 'pre_get_posts', array( $this, 'filter_query' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'print_styles' ) );
		add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );
	}

	/**
	 * Insert the Cornerstone column after the title column.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$new    = array();
		$anchor = 'title';

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( $anchor === $key ) {
				$new['wseo_cornerstone'] = __( 'Cornerstone', 'novatools-seo' );
			}
		}

		return $new;
	}

	/**
	 * Render the cornerstone star for a single row.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( $column, $post_id ) {
		if ( 'wseo_cornerstone' !== $column ) {
			return;
		}

		if ( '1' === (string) get_post_meta( $post_id, self::META_KEY, true ) ) {
			printf(
				'<span class="wseo-cornerstone-star" title="%s">★</span>',
				esc_attr__( 'Cornerstone content', 'novatools-seo' )
			);
		}
	}

	/**
	 * Render the "Cornerstone" filter dropdown above the list table.
	 *
	 * Only shown on supported post-type screens.
	 *
	 * @param string $post_type The current list-table post type.
	 * @return void
	 */
	public function render_filter_dropdown( $post_type ) {
		if ( ! in_array( $post_type, $this->get_post_types(), true ) ) {
			return;
		}

		$current = isset( $_GET[ self::FILTER_QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::FILTER_QUERY_VAR ] ) ) : '';
		?>
		<label for="wseo-filter-cornerstone" class="screen-reader-text">
			<?php esc_html_e( 'Filter by cornerstone', 'novatools-seo' ); ?>
		</label>
		<select name="<?php echo esc_attr( self::FILTER_QUERY_VAR ); ?>" id="wseo-filter-cornerstone">
			<option value="" <?php selected( $current, '' ); ?>><?php esc_html_e( 'All content', 'novatools-seo' ); ?></option>
			<option value="1" <?php selected( $current, '1' ); ?>><?php esc_html_e( 'Cornerstone only', 'novatools-seo' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Apply the cornerstone filter to the list query.
	 *
	 * @param \WP_Query $query Main query.
	 * @return void
	 */
	public function filter_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		if ( ! in_array( $post_type, $this->get_post_types(), true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::FILTER_QUERY_VAR ] ) || '1' !== sanitize_text_field( wp_unslash( $_GET[ self::FILTER_QUERY_VAR ] ) ) ) {
			return;
		}

		$meta_query = (array) $query->get( 'meta_query' );
		$meta_query[] = array(
			'key'   => self::META_KEY,
			'value' => '1',
		);
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Register cornerstone bulk actions.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function register_bulk_actions( $actions ) {
		$actions['wseo_cornerstone_mark']   = __( 'Mark as Cornerstone', 'novatools-seo' );
		$actions['wseo_cornerstone_unmark'] = __( 'Remove Cornerstone', 'novatools-seo' );
		return $actions;
	}

	/**
	 * Handle cornerstone bulk-action execution.
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $doaction    Action being performed.
	 * @param int[]  $post_ids    Selected post IDs.
	 * @return string
	 */
	public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		$valid = array( 'wseo_cornerstone_mark', 'wseo_cornerstone_unmark' );
		if ( ! in_array( $doaction, $valid, true ) ) {
			return $redirect_to;
		}

		$count    = 0;
		$post_ids = array_filter( array_map( 'absint', $post_ids ) );
		$marking  = 'wseo_cornerstone_mark' === $doaction;

		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			if ( $marking ) {
				update_post_meta( $post_id, self::META_KEY, '1' );
			} else {
				delete_post_meta( $post_id, self::META_KEY );
			}
			$count++;
		}

		return add_query_arg(
			array(
				'wseo_bulk_action' => $doaction,
				'wseo_bulk_count'  => $count,
			),
			$redirect_to
		);
	}

	/**
	 * Admin notice after a cornerstone bulk action.
	 *
	 * @return void
	 */
	public function bulk_action_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['wseo_bulk_action'] ) || empty( $_GET['wseo_bulk_count'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = sanitize_text_field( wp_unslash( $_GET['wseo_bulk_action'] ) );
		$count  = absint( $_GET['wseo_bulk_count'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $count < 1 ) {
			return;
		}

		$messages = array(
			'wseo_cornerstone_mark' => _n( '%d post marked as cornerstone.', '%d posts marked as cornerstone.', $count, 'novatools-seo' ),
			'wseo_cornerstone_unmark' => _n( 'Cornerstone removed from %d post.', 'Cornerstone removed from %d posts.', $count, 'novatools-seo' ),
		);

		if ( ! isset( $messages[ $action ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( sprintf( $messages[ $action ], $count ) )
		);
	}

	/**
	 * Inline styles for the cornerstone star + column width.
	 *
	 * @return void
	 */
	public function print_styles() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$on_screen = false;
		foreach ( $this->get_post_types() as $post_type ) {
			if ( $screen->id === 'edit-' . $post_type ) {
				$on_screen = true;
				break;
			}
		}
		if ( ! $on_screen ) {
			return;
		}

		$handle = 'wseo-cornerstone-column';
		wp_register_style( $handle, false );
		wp_enqueue_style( $handle );
		wp_add_inline_style(
			$handle,
			'.wseo-cornerstone-star { font-size: 16px; color: #f59e0b; line-height: 1; }
			.column-wseo_cornerstone { width: 90px; }'
		);
	}
}
