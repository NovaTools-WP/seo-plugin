<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Traits\Base;
use NovaToolsSEO\Traits\ScoreColumn;
use NovaToolsSEO\Core\MetaKeys;

defined( 'ABSPATH' ) || exit;

/**
 * Adds an "SEO Score" column to the Pages and Posts list tables.
 *
 * The score measures SEO metadata completeness across 8 key fields.
 * It is cached as post meta (_wseo_content_score) and recalculated
 * whenever a page or post is saved.
 *
 * Also registers bulk SEO actions: NoIndex, Index, and Reset SEO Data.
 */
class ContentListSeoColumn {

	use Base;
	use ScoreColumn;

	protected function get_score_post_types(): array {
		return array( 'post', 'page' );
	}

	protected function get_score_fields(): array {
		return array(
			'_wseo_title'          => true,
			'_wseo_description'    => true,
			'_wseo_og_image'       => true,
			'_thumbnail_id'        => true,
			'_wseo_og_title'       => true,
			'_wseo_og_description' => true,
			'_wseo_canonical'      => true,
			'_wseo_robots'         => true,
		);
	}

	protected function get_column_key(): string {
		return 'wseo_content_score';
	}

	protected function get_meta_key(): string {
		return '_wseo_content_score';
	}

	protected function get_anchor_column(): string {
		return 'title';
	}

	protected function get_tooltip_format(): string {
		/* translators: %1$d: number of completed fields, %2$d: total fields */
		return __( 'SEO Completeness: %1$d of %2$d fields completed', 'novatools-seo' );
	}

	protected function get_screen_ids(): array {
		return array( 'edit-post', 'edit-page' );
	}

	public function init() {
		foreach ( $this->get_score_post_types() as $post_type ) {
			add_filter( "manage_edit-{$post_type}_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'register_sortable' ) );
			add_action( "save_post_{$post_type}", array( $this, 'recalculate_score' ), 20 );

			add_filter( "bulk_actions-edit-{$post_type}", array( $this, 'register_bulk_actions' ) );
			add_filter( "handle_bulk_actions-edit-{$post_type}", array( $this, 'handle_bulk_actions' ), 10, 3 );
		}

		add_action( 'pre_get_posts', array( $this, 'sort_by_score' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'print_styles' ) );
		add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );
	}

	/**
	 * Register bulk SEO actions in the post list dropdown.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function register_bulk_actions( $actions ): array {
		$actions['wseo_noindex'] = __( 'Set to NoIndex', 'novatools-seo' );
		$actions['wseo_index']   = __( 'Set to Index', 'novatools-seo' );
		$actions['wseo_reset']   = __( 'Reset SEO Data', 'novatools-seo' );

		return $actions;
	}

	/**
	 * Handle bulk SEO action execution.
	 *
	 * @param string $redirect_to The redirect URL.
	 * @param string $doaction    The action being performed.
	 * @param int[]  $post_ids    Array of post IDs.
	 * @return string
	 */
	public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ): string {
		$valid_actions = array( 'wseo_noindex', 'wseo_index', 'wseo_reset' );

		if ( ! in_array( $doaction, $valid_actions, true ) ) {
			return $redirect_to;
		}

		$count    = 0;
		$post_ids = array_map( 'absint', $post_ids );
		$post_ids = array_filter( $post_ids );

		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			switch ( $doaction ) {
				case 'wseo_noindex':
					$this->set_noindex( $post_id );
					$count++;
					break;

				case 'wseo_index':
					$this->set_index( $post_id );
					$count++;
					break;

				case 'wseo_reset':
					$this->reset_seo_data( $post_id );
					$count++;
					break;
			}
		}

		$redirect_to = add_query_arg(
			array(
				'wseo_bulk_action' => $doaction,
				'wseo_bulk_count'  => $count,
			),
			$redirect_to
		);

		return $redirect_to;
	}

	/**
	 * Display an admin notice after a bulk SEO action.
	 */
	public function bulk_action_notices(): void {
		if ( empty( $_GET['wseo_bulk_action'] ) || empty( $_GET['wseo_bulk_count'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['wseo_bulk_action'] ) );
		$count  = absint( $_GET['wseo_bulk_count'] );

		if ( $count < 1 ) {
			return;
		}

		$messages = array(
			'wseo_noindex' => sprintf(
				/* translators: %d: number of posts */
				_n( '%d post set to NoIndex.', '%d posts set to NoIndex.', $count, 'novatools-seo' ),
				$count
			),
			'wseo_index' => sprintf(
				/* translators: %d: number of posts */
				_n( '%d post set to Index.', '%d posts set to Index.', $count, 'novatools-seo' ),
				$count
			),
			'wseo_reset' => sprintf(
				/* translators: %d: number of posts */
				_n( 'SEO data reset for %d post.', 'SEO data reset for %d posts.', $count, 'novatools-seo' ),
				$count
			),
		);

		if ( ! isset( $messages[ $action ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $messages[ $action ] )
		);
	}

	/**
	 * Set a post to NoIndex, preserving the follow/nofollow directive.
	 *
	 * @param int $post_id Post ID.
	 */
	private function set_noindex( int $post_id ): void {
		$robots = get_post_meta( $post_id, '_wseo_robots', true );

		if ( empty( $robots ) || strpos( $robots, 'noindex' ) !== false ) {
			update_post_meta( $post_id, '_wseo_robots', 'noindex,follow' );
			$this->recalculate_score( $post_id );
			return;
		}

		$robots = str_replace( 'index,', 'noindex,', $robots );
		$robots = str_replace( 'index', 'noindex', $robots );
		update_post_meta( $post_id, '_wseo_robots', $robots );
		$this->recalculate_score( $post_id );
	}

	/**
	 * Set a post to Index, preserving the follow/nofollow directive.
	 *
	 * @param int $post_id Post ID.
	 */
	private function set_index( int $post_id ): void {
		$robots = get_post_meta( $post_id, '_wseo_robots', true );

		if ( empty( $robots ) || strpos( $robots, 'noindex' ) === false ) {
			update_post_meta( $post_id, '_wseo_robots', 'index,follow' );
			$this->recalculate_score( $post_id );
			return;
		}

		$robots = str_replace( 'noindex,', 'index,', $robots );
		$robots = str_replace( 'noindex', 'index', $robots );
		update_post_meta( $post_id, '_wseo_robots', $robots );
		$this->recalculate_score( $post_id );
	}

	/**
	 * Delete all SEO meta for a post, resetting it to defaults.
	 *
	 * @param int $post_id Post ID.
	 */
	private function reset_seo_data( int $post_id ): void {
		foreach ( MetaKeys::POST_ALL as $key ) {
			delete_post_meta( $post_id, $key );
		}

		delete_post_meta( $post_id, '_wseo_content_score' );
	}
}
