<?php
/**
 * Text link counter.
 *
 * Counts internal vs external `<a href>` links in post content, persists the
 * totals to post meta on save, surfaces them as a posts-list column, and is
 * reused by the content analysis engine for the "add internal links" check.
 *
 * The cached meta (`_wseo_internal_links`, `_wseo_external_links`) is computed,
 * so it is intentionally NOT declared in MetaKeys::POST_ALL — that keeps the
 * standard meta-save loops from clobbering it on every save.
 *
 * @package NovaToolsSEO\Analysis
 */

namespace NovaToolsSEO\Analysis;

use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

class LinkCounter {

	use Base;

	const INTERNAL_META = '_wseo_internal_links';
	const EXTERNAL_META = '_wseo_external_links';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'save_post', array( $this, 'persist_link_counts' ), 20, 2 );

		foreach ( $this->get_column_post_types() as $post_type ) {
			add_filter( "manage_edit-{$post_type}_columns", array( $this, 'add_column' ) );
			add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'register_sortable' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}

		add_action( 'pre_get_posts', array( $this, 'sort_by_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'print_styles' ) );
	}

	/**
	 * Post types the Links column (and its sortable handling) apply to.
	 *
	 * @return string[]
	 */
	protected function get_column_post_types() {
		return array( 'post', 'page' );
	}

	/**
	 * Count internal/external links in a chunk of HTML.
	 *
	 * @param string $html Raw HTML content.
	 * @return array{internal:int,external:int,total:int}
	 */
	public function count( $html ) {
		$html = (string) $html;
		if ( '' === $html ) {
			return array( 'internal' => 0, 'external' => 0, 'total' => 0 );
		}

		if ( ! preg_match_all( '/<a\b[^>]*?\shref=["\']([^"\']+)["\'][^>]*>/i', $html, $matches ) ) {
			return array( 'internal' => 0, 'external' => 0, 'total' => 0 );
		}

		$site_host = $this->normalize_host( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$internal  = 0;
		$external  = 0;

		foreach ( $matches[1] as $raw_href ) {
			$href = trim( $raw_href );
			if ( '' === $href ) {
				continue;
			}

			// Skip anchors and non-http(s) schemes.
			if ( '#' === $href[0] ) {
				continue;
			}
			$scheme = (string) wp_parse_url( $href, PHP_URL_SCHEME );
			if ( '' !== $scheme && ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true ) ) {
				continue;
			}

			$host = $this->normalize_host( (string) wp_parse_url( $href, PHP_URL_HOST ) );
			if ( '' === $host || $host === $site_host ) {
				$internal++;
			} else {
				$external++;
			}
		}

		return array(
			'internal' => $internal,
			'external' => $external,
			'total'    => $internal + $external,
		);
	}

	/**
	 * Persist link counts when a post is saved.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function persist_link_counts( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$counts = $this->count( $post->post_content );

		update_post_meta( $post_id, self::INTERNAL_META, (int) $counts['internal'] );
		update_post_meta( $post_id, self::EXTERNAL_META, (int) $counts['external'] );
	}

	/**
	 * Compute and persist link counts for all existing published content.
	 *
	 * Run once on plugin activation so the Links column (and its sortable
	 * ordering, which requires the meta to exist on every post) is correct from
	 * the first list render. New posts are handled on save_post.
	 *
	 * @return void
	 */
	public function backfill() {
		$post_types = $this->get_column_post_types();
		if ( empty( $post_types ) ) {
			return;
		}

		$offset = 0;
		$batch  = 500;

		while ( true ) {
			$posts = get_posts( array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => $batch,
				'offset'         => $offset,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			) );

			if ( empty( $posts ) ) {
				break;
			}

			foreach ( $posts as $pid ) {
				$post = get_post( $pid );
				if ( ! $post ) {
					continue;
				}
				$counts = $this->count( $post->post_content );
				update_post_meta( $pid, self::INTERNAL_META, (int) $counts['internal'] );
				update_post_meta( $pid, self::EXTERNAL_META, (int) $counts['external'] );
			}

			$offset += $batch;
		}
	}

	/**
	 * Add the "Links" column after the SEO score column (if present),
	 * otherwise after the title column.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$new      = array();
		$anchors  = array( 'wseo_content_score', 'title' );
		$inserted = false;

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( ! $inserted && in_array( $key, $anchors, true ) ) {
				$new['wseo_links'] = __( 'Links', 'novatools-seo' );
				$inserted = true;
			}
		}

		if ( ! $inserted ) {
			$new['wseo_links'] = __( 'Links', 'novatools-seo' );
		}

		return $new;
	}

	/**
	 * Register the Links column as sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function register_sortable( $columns ) {
		$columns['wseo_links'] = 'wseo_links';
		return $columns;
	}

	/**
	 * Order the list query by internal-link count when sorting by Links.
	 *
	 * The meta exists on every post (backfilled on activation and computed on
	 * save), so the INNER JOIN WP performs for meta_key ordering hides nothing.
	 *
	 * @param \WP_Query $query Main query.
	 * @return void
	 */
	public function sort_by_links( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( 'wseo_links' !== $query->get( 'orderby' ) ) {
			return;
		}

		$types = array_values( array_filter( (array) $query->get( 'post_type' ) ) );
		if ( empty( $types ) || empty( array_intersect( $types, $this->get_column_post_types() ) ) ) {
			return;
		}

		$query->set( 'meta_key', self::INTERNAL_META );
		$query->set( 'orderby', 'meta_value_num' );
	}

	/**
	 * Render the link counts for a single row.
	 *
	 * @param string $column Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( $column, $post_id ) {
		if ( 'wseo_links' !== $column ) {
			return;
		}

		// Lazily compute + cache for posts saved before this feature existed.
		// Use metadata_exists (not a value check) so a genuinely zero-link post
		// caches its 0 instead of recomputing on every list render.
		$internal_cached = metadata_exists( 'post', $post_id, self::INTERNAL_META );
		$external_cached = metadata_exists( 'post', $post_id, self::EXTERNAL_META );

		if ( $internal_cached && $external_cached ) {
			$internal = (int) get_post_meta( $post_id, self::INTERNAL_META, true );
			$external = (int) get_post_meta( $post_id, self::EXTERNAL_META, true );
		} else {
			$post     = get_post( $post_id );
			$internal = 0;
			$external = 0;
			if ( $post ) {
				$counts   = $this->count( $post->post_content );
				$internal = $counts['internal'];
				$external = $counts['external'];
				update_post_meta( $post_id, self::INTERNAL_META, $internal );
				update_post_meta( $post_id, self::EXTERNAL_META, $external );
			}
		}

		printf(
			'<span class="wseo-links" title="%3$s">%1$d / %2$d</span>',
			$internal,
			$external,
			esc_attr__( 'Internal / external links', 'novatools-seo' )
		);
	}

	/**
	 * Inline styles for the Links column.
	 *
	 * @return void
	 */
	public function print_styles() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-post', 'edit-page' ), true ) ) {
			return;
		}

		$handle = 'wseo-links-column';
		wp_register_style( $handle, false );
		wp_enqueue_style( $handle );
		wp_add_inline_style(
			$handle,
			'.wseo-links { font-variant-numeric: tabular-nums; color: #475569; font-size: 12px; }
			.column-wseo_links { width: 70px; }'
		);
	}

	/**
	 * MB-safe lowercase.
	 *
	 * @param string $s String.
	 * @return string
	 */
	private function lower( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s );
	}

	/**
	 * Normalize a hostname for internal/external comparison.
	 *
	 * Lowercases and strips a leading "www." so the www and non-www variants
	 * of the site domain are both treated as internal.
	 *
	 * @param string $host Hostname.
	 * @return string
	 */
	private function normalize_host( $host ) {
		return preg_replace( '/^www\./', '', $this->lower( (string) $host ) );
	}
}
