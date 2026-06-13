<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

/**
 * Adds an "SEO Score" column to the Pages and Posts list tables.
 *
 * The score measures SEO metadata completeness across 8 key fields.
 * It is cached as post meta (_wseo_content_score) and recalculated
 * whenever a page or post is saved.
 */
class ContentListSeoColumn {

	use Base;

	/**
	 * Post types this column is registered for.
	 */
	const POST_TYPES = array( 'post', 'page' );

	/**
	 * Meta keys used for the 8-point SEO completeness check.
	 */
	const SCORE_FIELDS = array(
		'_wseo_title'          => true,
		'_wseo_description'    => true,
		'_wseo_og_image'       => true,
		'_thumbnail_id'        => true,
		'_wseo_og_title'       => true,
		'_wseo_og_description' => true,
		'_wseo_canonical'      => true,
		'_wseo_robots'         => true,
	);

	public function init() {
		foreach ( self::POST_TYPES as $post_type ) {
			add_filter( "manage_edit-{$post_type}_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'register_sortable' ) );
			add_action( "save_post_{$post_type}", array( $this, 'recalculate_score' ), 20 );
		}

		add_action( 'pre_get_posts', array( $this, 'sort_by_score' ) );
		add_action( 'admin_head', array( $this, 'print_styles' ) );
	}

	/**
	 * Insert the SEO Score column after the Title column.
	 *
	 * @param array $columns Existing list-table columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['wseo_content_score'] = __( 'SEO Score', 'novatools-seo' );
			}
		}
		return $new;
	}

	/**
	 * Render the SEO Score badge for a single row.
	 *
	 * @param string $column  Column identifier.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'wseo_content_score' !== $column ) {
			return;
		}

		$score = get_post_meta( $post_id, '_wseo_content_score', true );

		if ( '' === $score ) {
			$score = $this->calculate_score( $post_id );
			update_post_meta( $post_id, '_wseo_content_score', $score );
		}

		$score  = (int) $score;
		$color  = $this->get_color_class( $score );
		$passed = $this->get_passed_count( $score );
		$total  = count( self::SCORE_FIELDS );

		printf(
			'<span class="wseo-score-badge wseo-score-%s" title="%s">%d%%</span>',
			esc_attr( $color ),
			esc_attr( sprintf(
				/* translators: %1$d: number of completed fields, %2$d: total fields */
				__( 'SEO Completeness: %1$d of %2$d fields completed', 'novatools-seo' ),
				$passed,
				$total
			) ),
			$score
		);
	}

	/**
	 * Make the SEO Score column sortable by meta key.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function register_sortable( $columns ) {
		$columns['wseo_content_score'] = '_wseo_content_score';
		return $columns;
	}

	/**
	 * Handle ordering by the cached _wseo_content_score meta value.
	 *
	 * @param \WP_Query $query The main query.
	 */
	public function sort_by_score( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( '_wseo_content_score' === $orderby ) {
			$query->set( 'meta_key', '_wseo_content_score' );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Recalculate and cache the score when a post/page is saved.
	 *
	 * @param int $post_id Post ID.
	 */
	public function recalculate_score( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$score = $this->calculate_score( $post_id );
		update_post_meta( $post_id, '_wseo_content_score', $score );
	}

	/**
	 * Print inline styles for the score badge (only on pages/posts list screens).
	 */
	public function print_styles() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-post', 'edit-page' ), true ) ) {
			return;
		}

		echo '<style>
			.wseo-score-badge {
				display: inline-block;
				min-width: 38px;
				padding: 2px 8px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 600;
				text-align: center;
				line-height: 20px;
			}
			.wseo-score-green {
				background-color: #dcfce7;
				color: #166534;
			}
			.wseo-score-yellow {
				background-color: #fef9c3;
				color: #854d0e;
			}
			.wseo-score-red {
				background-color: #fee2e2;
				color: #991b1b;
			}
			.column-wseo_content_score {
				width: 80px;
			}
		</style>';
	}

	/**
	 * Calculate the SEO Completeness score for a post/page.
	 *
	 * @param int $post_id Post ID.
	 * @return int Score 0–100.
	 */
	private function calculate_score( $post_id ) {
		$passed = 0;

		foreach ( array_keys( self::SCORE_FIELDS ) as $meta_key ) {
			$value = get_post_meta( $post_id, $meta_key, true );
			if ( is_array( $value ) ) {
				if ( count( $value ) > 0 ) {
					$passed++;
				}
			} elseif ( ! empty( $value ) ) {
				$passed++;
			}
		}

		$total = count( self::SCORE_FIELDS );
		return $total > 0 ? (int) round( ( $passed / $total ) * 100 ) : 0;
	}

	/**
	 * Get the color class for a given score.
	 *
	 * @param int $score Score 0–100.
	 * @return string 'green', 'yellow', or 'red'.
	 */
	private function get_color_class( $score ) {
		if ( $score >= 86 ) {
			return 'green';
		}
		if ( $score >= 50 ) {
			return 'yellow';
		}
		return 'red';
	}

	/**
	 * Reverse-calculate how many fields passed for the tooltip.
	 *
	 * @param int $score Score 0–100.
	 * @return int Number of passed fields.
	 */
	private function get_passed_count( $score ) {
		return (int) round( ( $score / 100 ) * count( self::SCORE_FIELDS ) );
	}
}
