<?php
/**
 * Readability analysis: Flesch Reading Ease and related checks.
 *
 * Produces a 0–100 score and a list of {id,label,status,message} items
 * mirroring the existing analysis-item contract used by the React UI.
 * Each check contributes green=100 / yellow=50 / red=0; the section score
 * is the average of those contributions.
 *
 * @package NovaToolsSEO\Analysis
 */

namespace NovaToolsSEO\Analysis;

defined( 'ABSPATH' ) || exit;

class Readability {

	/**
	 * Flesch Reading Ease score for plain text.
	 *
	 * @param string $text Plain text.
	 * @return float|null Null when there isn't enough content to compute.
	 */
	public static function flesch( $text ) {
		$words     = TextProcessor::word_count( $text );
		$sentences = count( TextProcessor::sentences( $text ) );
		$syllables = TextProcessor::total_syllables( $text );

		if ( $words < 1 || $sentences < 1 ) {
			return null;
		}

		$score = 206.835 - 1.015 * ( $words / $sentences ) - 84.6 * ( $syllables / $words );

		return round( $score, 1 );
	}

	/**
	 * Run all readability checks against HTML content.
	 *
	 * @param string $html Raw post content.
	 * @return array{score:int,items:array}
	 */
	public static function analyze( $html ) {
		$text          = TextProcessor::to_plain_text( $html );
		$word_count    = TextProcessor::word_count( $text );
		$sentences     = TextProcessor::sentences( $text );
		$sentence_count = count( $sentences );

		if ( $word_count < 1 || $sentence_count < 1 ) {
			return array(
				'score' => 0,
				'items' => array(
					array(
						'id'      => 'flesch',
						'label'   => __( 'Flesch Reading Ease', 'novatools-seo' ),
						'status'  => 'red',
						'message' => __( 'Not enough content to analyze readability yet.', 'novatools-seo' ),
					),
				),
			);
		}

		$items = array();

		// 1. Flesch Reading Ease.
		$flesch = self::flesch( $text );
		if ( null === $flesch ) {
			$items[] = array(
				'id'      => 'flesch',
				'label'   => __( 'Flesch Reading Ease', 'novatools-seo' ),
				'status'  => 'red',
				'message' => __( 'Not enough content to compute a Flesch score.', 'novatools-seo' ),
			);
		} elseif ( $flesch >= 60 ) {
			$items[] = array(
				'id'      => 'flesch',
				'label'   => __( 'Flesch Reading Ease', 'novatools-seo' ),
				'status'  => 'green',
				/* translators: %s: Flesch Reading Ease score. */
				'message' => sprintf( __( 'Score %1$s — easy to read. Good job!', 'novatools-seo' ), $flesch ),
			);
		} elseif ( $flesch >= 30 ) {
			$items[] = array(
				'id'      => 'flesch',
				'label'   => __( 'Flesch Reading Ease', 'novatools-seo' ),
				'status'  => 'yellow',
				/* translators: %s: Flesch Reading Ease score. */
				'message' => sprintf( __( 'Score %1$s — fairly difficult to read. Try shorter sentences and simpler words.', 'novatools-seo' ), $flesch ),
			);
		} else {
			$items[] = array(
				'id'      => 'flesch',
				'label'   => __( 'Flesch Reading Ease', 'novatools-seo' ),
				'status'  => 'red',
				/* translators: %s: Flesch Reading Ease score. */
				'message' => sprintf( __( 'Score %1$s — very difficult to read. Shorten your sentences and use simpler words.', 'novatools-seo' ), $flesch ),
			);
		}

		// 2. Sentence length — % of sentences over 20 words.
		$long = 0;
		foreach ( $sentences as $s ) {
			if ( TextProcessor::word_count( $s ) > 20 ) {
				$long ++;
			}
		}
		$long_pct = ( $long / $sentence_count ) * 100;
		if ( $long_pct < 15 ) {
			$items[] = array(
				'id'      => 'sentence_length',
				'label'   => __( 'Sentence Length', 'novatools-seo' ),
				'status'  => 'green',
				'message' => __( 'Most sentences are concise. Great!', 'novatools-seo' ),
			);
		} elseif ( $long_pct <= 25 ) {
			$items[] = array(
				'id'      => 'sentence_length',
				'label'   => __( 'Sentence Length', 'novatools-seo' ),
				'status'  => 'yellow',
				/* translators: %s: percentage of long sentences. */
				'message' => sprintf( __( '%1$s%% of sentences exceed 20 words. Consider breaking up the long ones.', 'novatools-seo' ), round( $long_pct ) ),
			);
		} else {
			$items[] = array(
				'id'      => 'sentence_length',
				'label'   => __( 'Sentence Length', 'novatools-seo' ),
				'status'  => 'red',
				/* translators: %s: percentage of long sentences. */
				'message' => sprintf( __( '%1$s%% of sentences exceed 20 words — too many long sentences.', 'novatools-seo' ), round( $long_pct ) ),
			);
		}

		// 3. Paragraph length — longest paragraph word count.
		$paragraphs = TextProcessor::paragraphs( $html );
		$longest    = 0;
		foreach ( $paragraphs as $p ) {
			$c = TextProcessor::word_count( $p );
			if ( $c > $longest ) {
				$longest = $c;
			}
		}
		if ( 0 === $longest ) {
			$longest = $word_count;
		}
		if ( $longest <= 150 ) {
			$items[] = array(
				'id'      => 'paragraph_length',
				'label'   => __( 'Paragraph Length', 'novatools-seo' ),
				'status'  => 'green',
				/* translators: %d: word count of longest paragraph. */
				'message' => sprintf( __( 'Longest paragraph is %1$d words — well sized.', 'novatools-seo' ), $longest ),
			);
		} elseif ( $longest <= 200 ) {
			$items[] = array(
				'id'      => 'paragraph_length',
				'label'   => __( 'Paragraph Length', 'novatools-seo' ),
				'status'  => 'yellow',
				/* translators: %d: word count of longest paragraph. */
				'message' => sprintf( __( 'Longest paragraph is %1$d words. Consider splitting long paragraphs.', 'novatools-seo' ), $longest ),
			);
		} else {
			$items[] = array(
				'id'      => 'paragraph_length',
				'label'   => __( 'Paragraph Length', 'novatools-seo' ),
				'status'  => 'red',
				/* translators: %d: word count of longest paragraph. */
				'message' => sprintf( __( 'Longest paragraph is %1$d words — too long. Split it into smaller paragraphs.', 'novatools-seo' ), $longest ),
			);
		}

		// 4. Passive voice — % of passive sentences.
		$passive_pct = PassiveVoice::passive_sentence_percentage( $text );
		if ( $passive_pct < 10 ) {
			$items[] = array(
				'id'      => 'passive_voice',
				'label'   => __( 'Passive Voice', 'novatools-seo' ),
				'status'  => 'green',
				/* translators: %s: percentage of passive-voice sentences. */
				'message' => sprintf( __( '%1$s%% passive voice — nicely active.', 'novatools-seo' ), round( $passive_pct ) ),
			);
		} elseif ( $passive_pct <= 15 ) {
			$items[] = array(
				'id'      => 'passive_voice',
				'label'   => __( 'Passive Voice', 'novatools-seo' ),
				'status'  => 'yellow',
				/* translators: %s: percentage of passive-voice sentences. */
				'message' => sprintf( __( '%1$s%% passive voice. A little more active voice would help.', 'novatools-seo' ), round( $passive_pct ) ),
			);
		} else {
			$items[] = array(
				'id'      => 'passive_voice',
				'label'   => __( 'Passive Voice', 'novatools-seo' ),
				'status'  => 'red',
				/* translators: %s: percentage of passive-voice sentences. */
				'message' => sprintf( __( '%1$s%% passive voice — too much. Rewrite in the active voice where possible.', 'novatools-seo' ), round( $passive_pct ) ),
			);
		}

		// 5. Transition words — % of sentences containing one.
		$transition_pct = TransitionWords::sentence_coverage( $text ) * 100;
		if ( $transition_pct >= 30 ) {
			$items[] = array(
				'id'      => 'transition_words',
				'label'   => __( 'Transition Words', 'novatools-seo' ),
				'status'  => 'green',
				/* translators: %s: percentage of sentences with a transition word. */
				'message' => sprintf( __( '%1$s%% of sentences contain a transition word. Excellent flow.', 'novatools-seo' ), round( $transition_pct ) ),
			);
		} elseif ( $transition_pct >= 20 ) {
			$items[] = array(
				'id'      => 'transition_words',
				'label'   => __( 'Transition Words', 'novatools-seo' ),
				'status'  => 'yellow',
				/* translators: %s: percentage of sentences with a transition word. */
				'message' => sprintf( __( '%1$s%% of sentences contain a transition word (aim for 30%%).', 'novatools-seo' ), round( $transition_pct ) ),
			);
		} else {
			$items[] = array(
				'id'      => 'transition_words',
				'label'   => __( 'Transition Words', 'novatools-seo' ),
				'status'  => 'red',
				/* translators: %s: percentage of sentences with a transition word. */
				'message' => sprintf( __( 'Only %1$s%% of sentences contain a transition word. Add words like "however", "therefore", "for example".', 'novatools-seo' ), round( $transition_pct ) ),
			);
		}

		return array(
			'score' => self::score_from_items( $items ),
			'items' => $items,
		);
	}

	/**
	 * Average a 0–100 score from a list of status items.
	 *
	 * @param array $items Items with a 'status' key.
	 * @return int
	 */
	public static function score_from_items( array $items ) {
		if ( empty( $items ) ) {
			return 0;
		}
		$total = 0;
		foreach ( $items as $item ) {
			$total += self::status_points( $item['status'] ?? 'red' );
		}
		return (int) round( $total / count( $items ) );
	}

	/**
	 * Map a status to its numeric contribution.
	 *
	 * @param string $status green|yellow|red.
	 * @return int
	 */
	public static function status_points( $status ) {
		switch ( $status ) {
			case 'green':
				return 100;
			case 'yellow':
				return 50;
			default:
				return 0;
		}
	}
}
