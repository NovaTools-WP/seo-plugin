<?php

namespace NovaToolsSEO\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Shared logic for SEO score list-table columns.
 *
 * Concrete classes must implement the configuration methods and provide
 * their own init() to register the appropriate WordPress hooks.
 */
trait ScoreColumn {

	/**
	 * Post types this column is registered for.
	 *
	 * @return string[]
	 */
	abstract protected function get_score_post_types(): array;

	/**
	 * Meta keys to check when calculating the score.
	 *
	 * @return array<string, true>
	 */
	abstract protected function get_score_fields(): array;

	/**
	 * Column identifier used in the list table.
	 *
	 * @return string
	 */
	abstract protected function get_column_key(): string;

	/**
	 * Post meta key where the cached score is stored.
	 *
	 * @return string
	 */
	abstract protected function get_meta_key(): string;

	/**
	 * Existing column key to insert the score column after.
	 *
	 * @return string
	 */
	abstract protected function get_anchor_column(): string;

	/**
	 * Translatable tooltip format string.
	 *
	 * Must contain %1$d (passed count) and %2$d (total count) placeholders.
	 *
	 * @return string
	 */
	abstract protected function get_tooltip_format(): string;

	/**
	 * Screen IDs where the inline CSS should be printed.
	 *
	 * @return string[]
	 */
	abstract protected function get_screen_ids(): array;

	/**
	 * Register per-post-type hooks (columns, sort, save) and global hooks.
	 */
	abstract public function init();

	/**
	 * Insert the score column after the anchor column.
	 *
	 * @param array $columns Existing list-table columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$new     = array();
		$anchor  = $this->get_anchor_column();
		$col_key = $this->get_column_key();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( $anchor === $key ) {
				$new[ $col_key ] = __( 'SEO Score', 'novatools-seo' );
			}
		}

		return $new;
	}

	/**
	 * Render the score badge for a single row.
	 *
	 * @param string $column  Column identifier.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( $this->get_column_key() !== $column ) {
			return;
		}

		$meta_key = $this->get_meta_key();
		$score    = get_post_meta( $post_id, $meta_key, true );

		if ( '' === $score ) {
			$score = $this->calculate_score( $post_id );
			update_post_meta( $post_id, $meta_key, $score );
		}

		$score  = (int) $score;
		$color  = $this->get_color_class( $score );
		$passed = $this->get_passed_count( $score );
		$total  = count( $this->get_score_fields() );

		printf(
			'<span class="wseo-score-badge wseo-score-%s" title="%s">%d%%</span>',
			esc_attr( $color ),
			esc_attr( sprintf( $this->get_tooltip_format(), $passed, $total ) ),
			$score
		);
	}

	/**
	 * Make the score column sortable by its meta key.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function register_sortable( $columns ) {
		$columns[ $this->get_column_key() ] = $this->get_meta_key();
		return $columns;
	}

	/**
	 * Handle ordering by the cached score meta value.
	 *
	 * @param \WP_Query $query The main query.
	 */
	public function sort_by_score( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( $this->get_meta_key() === $orderby ) {
			$query->set( 'meta_key', $this->get_meta_key() );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Recalculate and cache the score when a post is saved.
	 *
	 * @param int $post_id Post ID.
	 */
	public function recalculate_score( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$score = $this->calculate_score( $post_id );
		update_post_meta( $post_id, $this->get_meta_key(), $score );
	}

	/**
	 * Enqueue inline styles for the score badge on relevant screens.
	 */
	public function print_styles() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, $this->get_screen_ids(), true ) ) {
			return;
		}

		$handle = 'wseo-score-column';

		wp_register_style( $handle, false );
		wp_enqueue_style( $handle );

		$css = sprintf(
			'.wseo-score-badge {
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
			.column-%s {
				width: 80px;
			}',
			esc_attr( $this->get_column_key() )
		);

		wp_add_inline_style( $handle, $css );
	}

	/**
	 * Calculate the completeness score for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int Score 0–100.
	 */
	private function calculate_score( $post_id ) {
		$passed = 0;

		foreach ( array_keys( $this->get_score_fields() ) as $meta_key ) {
			$value = get_post_meta( $post_id, $meta_key, true );
			if ( is_array( $value ) ) {
				if ( count( $value ) > 0 ) {
					$passed++;
				}
			} elseif ( ! empty( $value ) ) {
				$passed++;
			}
		}

		$total = count( $this->get_score_fields() );
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
		return (int) round( ( $score / 100 ) * count( $this->get_score_fields() ) );
	}
}
