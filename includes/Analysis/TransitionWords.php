<?php
/**
 * English transition-word detection for readability analysis.
 *
 * A curated list of common English transition words/phrases. Sentence
 * coverage (the fraction of sentences containing at least one) is a
 * readability signal used by Yoast/Rank Math. Pure coordinating
 * conjunctions (and, but, or) are intentionally excluded so the metric
 * remains meaningful.
 *
 * @package NovaToolsSEO\Analysis
 */

namespace NovaToolsSEO\Analysis;

defined( 'ABSPATH' ) || exit;

class TransitionWords {

	/**
	 * Curated transition words and phrases.
	 *
	 * Multi-word phrases must contain only spaces (no regex metachars
	 * beyond what preg_quote handles).
	 */
	const WORDS = array(
		'above all', 'accordingly', 'additionally', 'after', 'afterward', 'afterwards',
		'although', 'as a result', 'as an illustration', 'as a matter of fact',
		'at last', 'at the same time', 'because', 'before', 'besides', 'by the way',
		'certainly', 'consequently', 'conversely', 'despite', 'due to', 'especially',
		'eventually', 'finally', 'first', 'first of all', 'firstly', 'for example',
		'for instance', 'further', 'furthermore', 'hence', 'however', 'in addition',
		'in conclusion', 'in contrast', 'in fact', 'in other words', 'in particular',
		'in short', 'in summary', 'in the end', 'in the meantime', 'indeed', 'instead',
		'last', 'lastly', 'later', 'likewise', 'meanwhile', 'moreover', 'namely',
		'nevertheless', 'next', 'nonetheless', 'of course', 'on the contrary',
		'on the other hand', 'otherwise', 'overall', 'particularly', 'rather',
		'regardless', 'second', 'secondly', 'similarly', 'since', 'soon',
		'specifically', 'still', 'subsequently', 'such as', 'then', 'thereafter',
		'thereby', 'therefore', 'third', 'thirdly', 'though', 'thus', 'to begin with',
		'to conclude', 'to illustrate', 'to summarize', 'until', 'what\'s more',
		'while', 'yet',
	);

	/**
	 * Fraction of sentences containing at least one transition word.
	 *
	 * @param string $text Plain text.
	 * @return float 0.0–1.0.
	 */
	public static function sentence_coverage( $text ) {
		$sentences = TextProcessor::sentences( $text );
		if ( empty( $sentences ) ) {
			return 0.0;
		}

		$with_transition = 0;
		foreach ( $sentences as $sentence ) {
			if ( self::sentence_has_transition( $sentence ) ) {
				$with_transition ++;
			}
		}

		return $with_transition / count( $sentences );
	}

	/**
	 * Whether a single sentence contains a transition word/phrase.
	 *
	 * @param string $sentence A single sentence.
	 * @return bool
	 */
	public static function sentence_has_transition( $sentence ) {
		$lower = ' ' . self::lower( trim( $sentence ) ) . ' ';
		foreach ( self::WORDS as $word ) {
			if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/i', $lower ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * MB-safe lowercase helper.
	 *
	 * @param string $s String.
	 * @return string
	 */
	private static function lower( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s );
	}
}
