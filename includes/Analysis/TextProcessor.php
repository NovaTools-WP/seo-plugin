<?php
/**
 * Text processing helpers for the content analysis engine.
 *
 * Pure, dependency-free utilities for turning HTML post content into
 * measurable text: plain text, sentences, paragraphs, words, syllables,
 * and heading extraction. Written to be PHP-7.4 compatible and free of
 * WordPress dependencies so the analysis math stays portable and testable.
 *
 * @package NovaToolsSEO\Analysis
 */

namespace NovaToolsSEO\Analysis;

defined( 'ABSPATH' ) || exit;

class TextProcessor {

	/**
	 * Word matcher: a run of letters/numbers, optionally joined by apostrophes
	 * or hyphens (so "don't" and "well-being" each count as one word).
	 */
	const WORD_REGEX = '/[\p{L}\p{N}]+(?:[\'\-][\p{L}\p{N}]+)*/u';

	/**
	 * Convert HTML post content to plain text.
	 *
	 * Strips script/style blocks and shortcodes, replaces tags with spaces
	 * (so adjacent block elements don't merge words), decodes entities, and
	 * collapses whitespace.
	 *
	 * @param string $html Raw post content (may contain HTML/shortcodes).
	 * @return string
	 */
	public static function to_plain_text( $html ) {
		$html = (string) $html;
		if ( '' === $html ) {
			return '';
		}

		// Drop script/style blocks entirely.
		$html = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', ' ', $html );

		// Replace every remaining tag with a space (prevents word merge).
		$text = preg_replace( '/<[^>]+>/u', ' ', $html );

		// Strip WordPress/Gutenberg shortcodes like [gallery ...] or <!-- wp:... -->.
		$text = preg_replace( '/\[[^\]]+\]/', ' ', $text );
		$text = preg_replace( '/<!--.*?-->/us', ' ', $text );

		// Decode HTML entities.
		$encoding = defined( 'ENT_HTML5' ) ? ( ENT_QUOTES | ENT_HTML5 ) : ENT_QUOTES;
		$text     = html_entity_decode( $text, $encoding, 'UTF-8' );

		// Collapse whitespace.
		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( $text );
	}

	/**
	 * Split text into sentences, guarding against common abbreviations.
	 *
	 * Fragments with fewer than two words are discarded so list markers and
	 * stray punctuation don't inflate the sentence count.
	 *
	 * @param string $text Plain text (run through to_plain_text() first).
	 * @return string[]
	 */
	public static function sentences( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return array();
		}

		// Mask common abbreviations so their periods don't split sentences.
		$abbrev = array(
			'Mr.', 'Mrs.', 'Ms.', 'Dr.', 'Prof.', 'Sr.', 'Jr.', 'St.',
			'Inc.', 'Ltd.', 'Co.', 'Corp.', 'e.g.', 'i.e.', 'vs.', 'etc.',
			'U.S.', 'U.K.', 'a.m.', 'p.m.',
		);
		$placeholders = array();
		foreach ( $abbrev as $i => $a ) {
			$placeholders[] = "\x00A" . $i . "\x00";
		}
		$masked = str_replace( $abbrev, $placeholders, $text );

		$parts = preg_split( '/(?<=[.!?])\s+/u', $masked, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$parts     = str_replace( $placeholders, $abbrev, $parts );
		$sentences = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( self::word_count( $part ) >= 2 ) {
				$sentences[] = $part;
			}
		}

		return $sentences;
	}

	/**
	 * Split HTML into paragraphs, honoring block-level boundaries.
	 *
	 * @param string $html Raw post content.
	 * @return string[] Plain-text paragraphs.
	 */
	public static function paragraphs( $html ) {
		$html = (string) $html;
		if ( '' === $html ) {
			return array();
		}

		// Convert block-level closers and <br> to paragraph breaks.
		$text = preg_replace( '#<\s*(/p|/div|/li|/tr|br\s*/?|/h[1-6])[^>]*>#i', "\n\n", $html );
		$text = preg_replace( '/<[^>]+>/u', ' ', $text );

		$encoding = defined( 'ENT_HTML5' ) ? ( ENT_QUOTES | ENT_HTML5 ) : ENT_QUOTES;
		$text     = html_entity_decode( $text, $encoding, 'UTF-8' );

		$parts = preg_split( '/\n\s*\n|\r\n\s*\r\n/', $text );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$out = array();
		foreach ( $parts as $part ) {
			$part = trim( preg_replace( '/\s+/u', ' ', $part ) );
			if ( '' !== $part ) {
				$out[] = $part;
			}
		}

		return $out;
	}

	/**
	 * Extract all words from text.
	 *
	 * @param string $text Plain text.
	 * @return string[]
	 */
	public static function words( $text ) {
		if ( preg_match_all( self::WORD_REGEX, (string) $text, $m ) ) {
			return $m[0];
		}
		return array();
	}

	/**
	 * Count words in text.
	 *
	 * @param string $text Plain text.
	 * @return int
	 */
	public static function word_count( $text ) {
		$count = preg_match_all( self::WORD_REGEX, (string) $text );
		return ( false === $count ) ? 0 : $count;
	}

	/**
	 * Estimate the syllable count of a single word.
	 *
	 * Uses the classic vowel-group heuristic with a silent-trailing-e
	 * adjustment and a small exception table for common irregular words.
	 * Good enough for Flesch Reading Ease; not linguistically exact.
	 *
	 * @param string $word A single word.
	 * @return int
	 */
	public static function syllable_count( $word ) {
		$word = strtolower( preg_replace( '/[^a-zA-Z]/', '', (string) $word ) );
		if ( '' === $word ) {
			return 0;
		}

		// Known exceptions where the heuristic miscounts.
		static $exceptions = array(
			'the'        => 1,
			'every'      => 2,
			'recipe'     => 3,
			'orange'     => 2,
			'people'     => 2,
			'family'     => 2,
			'different'  => 3,
			'something'  => 3,
			'business'   => 3,
			'everyone'   => 3,
			'beautiful'  => 4,
		);
		if ( isset( $exceptions[ $word ] ) ) {
			return $exceptions[ $word ];
		}

		$count = preg_match_all( '/[aeiouy]+/', $word );
		if ( ! $count ) {
			return 1;
		}

		// Trailing silent 'e', unless the word ends in consonant + 'le'
		// (table, apple) where the 'e' is pronounced.
		if ( 'e' === substr( $word, -1 ) && ! preg_match( '/[^aeiouy]le$/', $word ) ) {
			$count --;
		}

		return $count < 1 ? 1 : $count;
	}

	/**
	 * Total syllables across all words in text.
	 *
	 * @param string $text Plain text.
	 * @return int
	 */
	public static function total_syllables( $text ) {
		$total = 0;
		foreach ( self::words( $text ) as $word ) {
			$total += self::syllable_count( $word );
		}
		return $total;
	}

	/**
	 * Average words per sentence.
	 *
	 * @param string $text Plain text.
	 * @return float
	 */
	public static function words_per_sentence( $text ) {
		$sentences = self::sentences( $text );
		if ( empty( $sentences ) ) {
			return 0.0;
		}
		return self::word_count( $text ) / count( $sentences );
	}

	/**
	 * Extract headings (H1–H6) with their level and plain text.
	 *
	 * @param string $html Raw post content.
	 * @return array{level:int,text:string}[]
	 */
	public static function headings( $html ) {
		$headings = array();
		if ( preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', (string) $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$text = self::to_plain_text( $m[2] );
				if ( '' !== $text ) {
					$headings[] = array(
						'level' => (int) $m[1],
						'text'  => $text,
					);
				}
			}
		}
		return $headings;
	}

	/**
	 * Get the plain text of the first content paragraph.
	 *
	 * @param string $html Raw post content.
	 * @return string
	 */
	public static function first_paragraph( $html ) {
		$paragraphs = self::paragraphs( $html );
		return isset( $paragraphs[0] ) ? $paragraphs[0] : '';
	}
}
