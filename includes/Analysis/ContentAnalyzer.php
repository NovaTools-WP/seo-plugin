<?php
/**
 * Content analysis orchestrator.
 *
 * Resolves the sources of truth for a post (client override → post meta →
 * post object), runs the readability and SEO/keyphrase analyses, and
 * returns the combined payload used by the REST /analyze endpoint and the
 * React meta box. Also persists aggregate scores on post save so later
 * features (e.g. a posts-list score column) can reuse them.
 *
 * @package NovaToolsSEO\Analysis
 */

namespace NovaToolsSEO\Analysis;

use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

class ContentAnalyzer {

	use Base;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'save_post', array( $this, 'persist_scores_on_save' ), 20, 2 );
	}

	/**
	 * Run the full analysis for a post (or arbitrary content).
	 *
	 * @param array $args {
	 *     Optional overrides; missing values fall back to post data.
	 *
	 *     @type int    $post_id     Post ID (required for meta/post fallbacks).
	 *     @type string $keyphrase   Focus keyphrase.
	 *     @type string $content     Raw HTML content override.
	 *     @type string $title       SEO title override.
	 *     @type string $description Meta description override.
	 * }
	 * @return array
	 */
	public function analyze( array $args ) {
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$post    = $post_id ? get_post( $post_id ) : null;

		// Content: override → post_content.
		if ( isset( $args['content'] ) && '' !== $args['content'] ) {
			$content = $args['content'];
		} elseif ( $post ) {
			$content = $post->post_content;
		} else {
			$content = '';
		}

		// Title: override → _wseo_title → post title.
		if ( isset( $args['title'] ) && '' !== $args['title'] ) {
			$title = $args['title'];
		} else {
			$meta_title = $post_id ? get_post_meta( $post_id, '_wseo_title', true ) : '';
			$title      = '' !== $meta_title ? $meta_title : ( $post ? get_the_title( $post ) : '' );
		}

		// Description: override → _wseo_description.
		if ( isset( $args['description'] ) ) {
			$description = (string) $args['description'];
		} elseif ( $post_id ) {
			$description = (string) get_post_meta( $post_id, '_wseo_description', true );
		} else {
			$description = '';
		}

		$permalink  = $post_id ? (string) get_permalink( $post_id ) : '';
		$keyphrase  = isset( $args['keyphrase'] ) ? trim( (string) $args['keyphrase'] ) : '';

		$seo = $this->analyze_seo( $content, $keyphrase, $title, $description, $permalink );

		return array(
			'post_id'     => $post_id,
			'readability' => Readability::analyze( $content ),
			'seo'         => $seo,
		);
	}

	/**
	 * Run the SEO / focus-keyphrase checks.
	 *
	 * Keyphrase-dependent checks are excluded from the score denominator
	 * when no keyphrase is set, so the score isn't crushed before a user
	 * has entered one.
	 *
	 * @param string $content     Raw HTML content.
	 * @param string $keyphrase   Focus keyphrase.
	 * @param string $title       SEO title.
	 * @param string $description Meta description.
	 * @param string $permalink   Post permalink.
	 * @return array{score:int,keyphrase:string,items:array}
	 */
	private function analyze_seo( $content, $keyphrase, $title, $description, $permalink ) {
		$items      = array();
		$text       = TextProcessor::to_plain_text( $content );
		$word_count = TextProcessor::word_count( $text );
		$has_kp     = '' !== $keyphrase;

		// 1. Keyphrase set.
		if ( $has_kp ) {
			$items[] = array(
				'id'      => 'keyphrase_set',
				'label'   => __( 'Focus Keyphrase', 'novatools-seo' ),
				'status'  => 'green',
				'message' => __( 'Focus keyphrase is set.', 'novatools-seo' ),
			);
		} else {
			$items[] = array(
				'id'      => 'keyphrase_set',
				'label'   => __( 'Focus Keyphrase', 'novatools-seo' ),
				'status'  => 'red',
				'message' => __( 'No focus keyphrase set. Enter one to analyze keyphrase usage.', 'novatools-seo' ),
			);
		}

		// Keyphrase-dependent checks.
		if ( $has_kp ) {
			$kp = $this->normalize( $keyphrase );

			// 2. Density.
			$items[] = $this->check_density( $kp, $text, $word_count );

			// 3. In SEO title.
			$items[] = $this->check_in_title( $kp, $title );

			// 4. In URL.
			$items[] = $this->check_in_url( $keyphrase, $permalink );

			// 5. In first paragraph.
			$items[] = $this->check_in_first_paragraph( $kp, $content );

			// 6. In headings.
			$items[] = $this->check_in_headings( $kp, $content );

			// 7. In meta description.
			$items[] = $this->check_in_description( $kp, $description );
		}

		// 8. Content length (always).
		$items[] = $this->check_content_length( $word_count );

		// 9. Heading structure (always).
		$items[] = $this->check_heading_structure( $content );

		// 10. Internal links (always).
		$items[] = $this->check_internal_links( $content );

		// 11. Outbound links (always).
		$items[] = $this->check_external_links( $content );

		return array(
			'score'     => Readability::score_from_items( $items ),
			'keyphrase' => $keyphrase,
			'items'     => $items,
		);
	}

	/**
	 * Keyphrase density check.
	 *
	 * @param string $kp         Normalized keyphrase.
	 * @param string $text       Plain text.
	 * @param int    $word_count Word count.
	 * @return array
	 */
	private function check_density( $kp, $text, $word_count ) {
		$clean = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $this->lower( $text ) );
		$clean = trim( preg_replace( '/\s+/u', ' ', $clean ) );

		$occurrences = $this->count_phrase( $kp, $clean );
		$density     = $word_count > 0 ? ( $occurrences / $word_count ) * 100 : 0;
		$rounded     = round( $density, 1 );

		$item = array(
			'id'    => 'density',
			'label' => __( 'Keyphrase Density', 'novatools-seo' ),
		);

		if ( 0 === $occurrences ) {
			$item['status']  = 'red';
			$item['message'] = __( 'The focus keyphrase does not appear in the content. Add it.', 'novatools-seo' );
		} elseif ( $density >= 0.5 && $density <= 2.5 ) {
			$item['status']  = 'green';
			/* translators: %s: keyphrase density percentage. */
			$item['message'] = sprintf( __( 'Density %1$s%% — well-balanced keyphrase usage.', 'novatools-seo' ), $rounded );
		} elseif ( ( $density >= 0.25 && $density < 0.5 ) || ( $density > 2.5 && $density <= 3.5 ) ) {
			$item['status']  = 'yellow';
			/* translators: %s: keyphrase density percentage. */
			$item['message'] = sprintf( __( 'Density %1$s%% — slightly off the 0.5–2.5%% target.', 'novatools-seo' ), $rounded );
		} else {
			$item['status']  = 'red';
			/* translators: %s: keyphrase density percentage. */
			$item['message'] = sprintf( __( 'Density %1$s%% — too far from the 0.5–2.5%% target.', 'novatools-seo' ), $rounded );
		}

		return $item;
	}

	/**
	 * Keyphrase in SEO title check.
	 *
	 * @param string $kp    Normalized keyphrase.
	 * @param string $title SEO title.
	 * @return array
	 */
	private function check_in_title( $kp, $title ) {
		$title_norm = $this->normalize( $title );
		$pos        = ( '' !== $kp ) ? strpos( $title_norm, $kp ) : false;

		$item = array(
			'id'    => 'in_title',
			'label' => __( 'Keyphrase in SEO Title', 'novatools-seo' ),
		);

		if ( false === $pos ) {
			$item['status']  = 'red';
			$item['message'] = __( 'The focus keyphrase is not in the SEO title.', 'novatools-seo' );
		} elseif ( 0 === $pos ) {
			$item['status']  = 'green';
			$item['message'] = __( 'The focus keyphrase appears at the start of the SEO title.', 'novatools-seo' );
		} else {
			$item['status']  = 'yellow';
			$item['message'] = __( 'The focus keyphrase is in the SEO title, but not at the start.', 'novatools-seo' );
		}

		return $item;
	}

	/**
	 * Keyphrase in URL check.
	 *
	 * @param string $keyphrase Raw keyphrase.
	 * @param string $permalink Post permalink.
	 * @return array
	 */
	private function check_in_url( $keyphrase, $permalink ) {
		$kp_slug = $this->slugify( $keyphrase );
		$path    = $this->lower( (string) wp_parse_url( $permalink, PHP_URL_PATH ) );
		$found   = ( '' !== $kp_slug && '' !== $path && false !== strpos( $path, $kp_slug ) );

		return array(
			'id'      => 'in_url',
			'label'   => __( 'Keyphrase in URL', 'novatools-seo' ),
			'status'  => $found ? 'green' : 'red',
			'message' => $found
				? __( 'The focus keyphrase appears in the URL.', 'novatools-seo' )
				: __( 'The focus keyphrase is not in the URL.', 'novatools-seo' ),
		);
	}

	/**
	 * Keyphrase in first paragraph check.
	 *
	 * @param string $kp      Normalized keyphrase.
	 * @param string $content Raw HTML content.
	 * @return array
	 */
	private function check_in_first_paragraph( $kp, $content ) {
		$first  = $this->normalize( TextProcessor::first_paragraph( $content ) );
		$found  = ( '' !== $kp && '' !== $first && false !== strpos( $first, $kp ) );

		return array(
			'id'      => 'in_first_paragraph',
			'label'   => __( 'Keyphrase in First Paragraph', 'novatools-seo' ),
			'status'  => $found ? 'green' : 'red',
			'message' => $found
				? __( 'The focus keyphrase appears in the first paragraph.', 'novatools-seo' )
				: __( 'The focus keyphrase does not appear in the first paragraph.', 'novatools-seo' ),
		);
	}

	/**
	 * Keyphrase in headings check.
	 *
	 * @param string $kp      Normalized keyphrase.
	 * @param string $content Raw HTML content.
	 * @return array
	 */
	private function check_in_headings( $kp, $content ) {
		$headings = TextProcessor::headings( $content );
		$total    = count( $headings );
		$found    = 0;

		foreach ( $headings as $heading ) {
			if ( false !== strpos( $this->normalize( $heading['text'] ), $kp ) ) {
				$found ++;
			}
		}

		$item = array(
			'id'    => 'in_headings',
			'label' => __( 'Keyphrase in Headings', 'novatools-seo' ),
		);

		if ( 0 === $total ) {
			$item['status']  = 'yellow';
			$item['message'] = __( 'No headings found. Add subheadings that include the focus keyphrase.', 'novatools-seo' );
		} elseif ( $found >= 1 ) {
			$item['status']  = 'green';
			/* translators: 1: headings containing keyphrase, 2: total headings. */
			$item['message'] = sprintf( __( 'Found in %1$d of %2$d headings.', 'novatools-seo' ), $found, $total );
		} else {
			$item['status']  = 'red';
			/* translators: %d: total headings. */
			$item['message'] = sprintf(
				_n( 'Not found in any of %d heading.', 'Not found in any of %d headings.', $total, 'novatools-seo' ),
				$total
			);
		}

		return $item;
	}

	/**
	 * Keyphrase in meta description check.
	 *
	 * @param string $kp          Normalized keyphrase.
	 * @param string $description Meta description.
	 * @return array
	 */
	private function check_in_description( $kp, $description ) {
		$desc  = $this->normalize( $description );
		$found = ( '' !== $kp && '' !== $desc && false !== strpos( $desc, $kp ) );

		return array(
			'id'      => 'in_meta_description',
			'label'   => __( 'Keyphrase in Meta Description', 'novatools-seo' ),
			'status'  => $found ? 'green' : 'red',
			'message' => $found
				? __( 'The focus keyphrase appears in the meta description.', 'novatools-seo' )
				: __( 'The focus keyphrase is not in the meta description.', 'novatools-seo' ),
		);
	}

	/**
	 * Content length check.
	 *
	 * @param int $word_count Word count.
	 * @return array
	 */
	private function check_content_length( $word_count ) {
		$item = array(
			'id'    => 'content_length',
			'label' => __( 'Content Length', 'novatools-seo' ),
		);

		if ( $word_count >= 300 ) {
			$item['status']  = 'green';
			/* translators: %d: word count. */
			$item['message'] = sprintf( __( '%1$d words — good content length.', 'novatools-seo' ), $word_count );
		} elseif ( $word_count >= 250 ) {
			$item['status']  = 'yellow';
			/* translators: %d: word count. */
			$item['message'] = sprintf( __( '%1$d words — slightly short (aim for 300+).', 'novatools-seo' ), $word_count );
		} else {
			$item['status']  = 'red';
			/* translators: %d: word count. */
			$item['message'] = sprintf( __( '%1$d words — too short. Aim for at least 300 words.', 'novatools-seo' ), $word_count );
		}

		return $item;
	}

	/**
	 * Heading structure check (no skipped levels, subheadings present).
	 *
	 * @param string $content Raw HTML content.
	 * @return array
	 */
	private function check_heading_structure( $content ) {
		$headings = TextProcessor::headings( $content );
		$item     = array(
			'id'    => 'heading_structure',
			'label' => __( 'Heading Structure', 'novatools-seo' ),
		);

		if ( empty( $headings ) ) {
			$item['status']  = 'yellow';
			$item['message'] = __( 'No subheadings found. Add H2/H3 headings to structure your content.', 'novatools-seo' );
			return $item;
		}

		// WordPress post titles render as the H1 outside post_content, so the
		// body's lowest heading level (often H2) is the effective top level
		// here. Only jumps wider than one level above that baseline
		// (e.g. H2 → H4) count as a skip — a body that simply starts at H2 is
		// correct, not a skip.
		$levels   = array_column( $headings, 'level' );
		$baseline = $levels ? min( $levels ) : 1;

		$seen    = array();
		$skipped = false;
		foreach ( $headings as $heading ) {
			$level = $heading['level'];
			if ( $level > $baseline && empty( $seen[ $level - 1 ] ) ) {
				$skipped = true;
			}
			$seen[ $level ] = true;
		}

		if ( $skipped ) {
			$item['status']  = 'yellow';
			$item['message'] = __( 'Heading levels are skipped (e.g. H2 followed by H4). Keep a logical hierarchy.', 'novatools-seo' );
		} else {
			$item['status']  = 'green';
			$item['message'] = __( 'Heading structure looks good.', 'novatools-seo' );
		}

		return $item;
	}

	/**
	 * Internal-links check — encourages linking to other site content.
	 *
	 * @param string $content Raw HTML content.
	 * @return array
	 */
	private function check_internal_links( $content ) {
		$counts   = LinkCounter::get_instance()->count( $content );
		$internal = (int) $counts['internal'];

		$item = array(
			'id'    => 'internal_links',
			'label' => __( 'Internal Links', 'novatools-seo' ),
		);

		if ( $internal >= 3 ) {
			$item['status']  = 'green';
			/* translators: %d: internal link count. */
			$item['message'] = sprintf( __( '%1$d internal links — great for site structure.', 'novatools-seo' ), $internal );
		} elseif ( $internal >= 1 ) {
			$item['status']  = 'yellow';
			/* translators: %d: internal link count. */
			$item['message'] = sprintf( __( '%1$d internal link — add a few more for stronger site structure.', 'novatools-seo' ), $internal );
		} else {
			$item['status']  = 'red';
			$item['message'] = __( 'No internal links found. Add links to related content.', 'novatools-seo' );
		}

		return $item;
	}

	/**
	 * Outbound-links check — encourages linking to authoritative sources.
	 *
	 * @param string $content Raw HTML content.
	 * @return array
	 */
	private function check_external_links( $content ) {
		$counts   = LinkCounter::get_instance()->count( $content );
		$external = (int) $counts['external'];

		$item = array(
			'id'    => 'external_links',
			'label' => __( 'Outbound Links', 'novatools-seo' ),
		);

		if ( $external >= 1 ) {
			$item['status']  = 'green';
			/* translators: %d: outbound link count. */
			$item['message'] = sprintf(
				_n( '%1$d outbound link — good for authority.', '%1$d outbound links — good for authority.', $external, 'novatools-seo' ),
				$external
			);
		} else {
			$item['status']  = 'yellow';
			$item['message'] = __( 'No outbound links. Linking to authoritative sources can add value.', 'novatools-seo' );
		}

		return $item;
	}

	/**
	 * Persist aggregate scores on post save (for later list-column use).
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function persist_scores_on_save( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$result = $this->analyze( array(
			'post_id'   => $post_id,
			'keyphrase' => (string) get_post_meta( $post_id, '_wseo_focus_keyphrase', true ),
		) );

		if ( isset( $result['seo']['score'] ) ) {
			update_post_meta( $post_id, '_wseo_seo_score', (int) $result['seo']['score'] );
		}
		if ( isset( $result['readability']['score'] ) ) {
			update_post_meta( $post_id, '_wseo_readability_score', (int) $result['readability']['score'] );
		}
	}

	/**
	 * Count occurrences of a phrase bounded by whitespace or string edges.
	 *
	 * Counts adjacent phrases that share a single separating space (which the
	 * non-overlapping substr_count() would merge into one) — e.g. a keyphrase
	 * at the end of a heading followed by the same phrase at the start of the
	 * next paragraph.
	 *
	 * @param string $kp    Normalized keyphrase.
	 * @param string $clean Normalized, single-spaced text.
	 * @return int
	 */
	private function count_phrase( $kp, $clean ) {
		if ( '' === $kp ) {
			return 0;
		}
		// Leading boundary is consumed; trailing boundary is a lookahead so
		// the separating space can also serve as the next match's lead.
		$pattern = '/(?:^|\s)' . preg_quote( $kp, '/' ) . '(?=\s|$)/u';
		$count   = preg_match_all( $pattern, $clean );
		return false === $count ? 0 : $count;
	}

	/**
	 * Lowercase + collapse whitespace for keyphrase matching.
	 *
	 * @param string $s String.
	 * @return string
	 */
	private function normalize( $s ) {
		$s = $this->lower( (string) $s );
		$s = preg_replace( '/\s+/u', ' ', $s );
		return trim( $s );
	}

	/**
	 * Convert a keyphrase to a URL slug for matching.
	 *
	 * @param string $s String.
	 * @return string
	 */
	private function slugify( $s ) {
		$s = $this->lower( (string) $s );
		$s = preg_replace( '/[^a-z0-9]+/', '-', $s );
		return trim( $s, '-' );
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
}
