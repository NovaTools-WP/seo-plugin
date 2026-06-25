<?php
/**
 * Internal link suggestions.
 *
 * Ranks other published posts for the post being edited by how strongly they
 * relate to it (shared terms, focus-keyphrase/title overlap), excluding posts
 * the content already links to. Powers the editor's "link suggestions" panel.
 *
 * @package NovaToolsSEO\Analysis
 */

namespace NovaToolsSEO\Analysis;

use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

class LinkAnalyzer {

	use Base;

	const DEFAULT_LIMIT = 5;

	/**
	 * Get ranked internal-link suggestions for a post.
	 *
	 * @param int    $post_id   Post ID being edited.
	 * @param int    $limit     Max suggestions.
	 * @param string $keyphrase Optional live keyphrase override; falls back to
	 *                          the saved focus keyphrase when empty.
	 * @return array[] Each: {id,title,permalink,excerpt,score,reason}.
	 */
	public function get_suggestions( $post_id, $limit = self::DEFAULT_LIMIT, $keyphrase = '' ) {
		$post_id = (int) $post_id;
		$post    = $post_id ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return array();
		}

		$limit       = max( 1, (int) $limit );
		$term_ids    = $this->get_post_term_ids( $post_id );
		$keyphrase   = '' !== trim( (string) $keyphrase )
			? trim( (string) $keyphrase )
			: trim( (string) get_post_meta( $post_id, '_wseo_focus_keyphrase', true ) );
		$title_words = $this->significant_words( $post->post_title );
		$linked_ids  = $this->get_already_linked_ids( $post->post_content, $post_id );

		$candidates = get_posts( array(
			'post_type'        => $post->post_type,
			'post_status'      => 'publish',
			'posts_per_page'   => 50,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'fields'           => 'all',
			'exclude'          => array_merge( array( $post_id ), $linked_ids ),
			'suppress_filters' => false,
		) );

		if ( empty( $candidates ) ) {
			return array();
		}

		$scored = array();
		foreach ( $candidates as $candidate ) {
			$shared_terms = count( array_intersect( $term_ids, $this->get_post_term_ids( $candidate->ID ) ) );

			$haystack = $this->lower( $candidate->post_title . ' ' . $this->plain( $candidate->post_content ) );
			$kp_hit   = ( '' !== $keyphrase && false !== strpos( $haystack, $this->lower( $keyphrase ) ) );

			$cand_words = $this->significant_words( $candidate->post_title );
			$title_overlap = count( array_intersect( $title_words, $cand_words ) );

			$score = ( $shared_terms * 3 ) + ( $kp_hit ? 2 : 0 ) + $title_overlap;
			if ( $score < 1 ) {
				// Fall back to recent posts so the panel is never empty.
				$score = 0;
			}

			$scored[] = array(
				'post'      => $candidate,
				'score'     => $score,
				'shared'    => $shared_terms,
				'kp_hit'    => $kp_hit,
				'title_hit' => $title_overlap,
			);
		}

		// Prefer scored matches; break ties by recency (candidates are date-desc).
		usort( $scored, function ( $a, $b ) {
			if ( $a['score'] === $b['score'] ) {
				return $b['post']->ID <=> $a['post']->ID;
			}
			return $b['score'] <=> $a['score'];
		} );

		// If the top results have no signal at all, still surface recent posts.
		$result = array();
		foreach ( array_slice( $scored, 0, $limit ) as $row ) {
			$result[] = array(
				'id'        => (int) $row['post']->ID,
				'title'     => get_the_title( $row['post'] ),
				'permalink' => (string) get_permalink( $row['post'] ),
				'excerpt'   => wp_trim_words( $this->plain( $row['post']->post_content ), 18 ),
				'score'     => (int) $row['score'],
				'reason'    => $this->reason( $row ),
			);
		}

		return $result;
	}

	/**
	 * Human-readable reason for a suggestion.
	 *
	 * @param array $row Scored row.
	 * @return string
	 */
	private function reason( $row ) {
		$bits = array();
		if ( $row['shared'] > 0 ) {
			/* translators: %d: shared term count. */
			$bits[] = sprintf( _n( 'Shares %d term', 'Shares %d terms', $row['shared'], 'novatools-seo' ), $row['shared'] );
		}
		if ( $row['kp_hit'] ) {
			$bits[] = __( 'Matches focus keyphrase', 'novatools-seo' );
		}
		if ( $row['title_hit'] > 0 ) {
			$bits[] = __( 'Title overlap', 'novatools-seo' );
		}
		return $bits ? implode( ', ', $bits ) : __( 'Recent content', 'novatools-seo' );
	}

	/**
	 * Term IDs (categories + tags) attached to a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private function get_post_term_ids( $post_id ) {
		$ids    = array();
		$taxos  = array( 'category', 'post_tag' );
		$terms  = wp_get_object_terms( $post_id, $taxos );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		return $ids;
	}

	/**
	 * IDs of posts that the content already links to (so they aren't re-suggested).
	 *
	 * @param string $content Post HTML content.
	 * @param int    $post_id Current post ID.
	 * @return int[]
	 */
	private function get_already_linked_ids( $content, $post_id ) {
		if ( ! preg_match_all( '/<a\b[^>]*?\shref=["\']([^"\']+)["\'][^>]*>/i', (string) $content, $matches ) ) {
			return array();
		}

		$ids = array();
		foreach ( $matches[1] as $href ) {
			$url = trim( $href );
			if ( '' === $url || '#' === $url[0] ) {
				continue;
			}
			// url_to_postid resolves internal permalinks (and some other types).
			$found = url_to_postid( $url );
			if ( $found && (int) $found !== (int) $post_id ) {
				$ids[] = (int) $found;
			}
		}
		return array_unique( $ids );
	}

	/**
	 * Strip HTML to plain text.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private function plain( $html ) {
		return trim( wp_strip_all_tags( (string) $html ) );
	}

	/**
	 * Significant (non-stopword-ish) words from a title, lowercased.
	 *
	 * @param string $title Title.
	 * @return string[]
	 */
	private function significant_words( $title ) {
		$words    = array();
		$stoplist = array( 'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'is', 'are', 'with', 'your', 'you', 'this', 'that', 'it', 'as', 'at', 'by', 'be', 'from' );

		if ( preg_match_all( '/[\p{L}\p{N}]+/u', $this->lower( (string) $title ), $matches ) ) {
			foreach ( $matches[0] as $w ) {
				if ( mb_strlen( $w ) < 3 || in_array( $w, $stoplist, true ) ) {
					continue;
				}
				$words[] = $w;
			}
		}
		return array_unique( $words );
	}

	/**
	 * MB-safe lowercase.
	 *
	 * @param string $s String.
	 * @return string
	 */
	private function lower( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s ) : strtolower( (string) $s );
	}
}
