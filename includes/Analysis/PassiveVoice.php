<?php
/**
 * English passive-voice heuristic for readability analysis.
 *
 * Regex-based detection of auxiliary + past-participle patterns. This is a
 * fast, approximate detector (like Yoast's English passive-voice checker);
 * it will produce some false positives and miss edge cases. Documented and
 * acceptable for a v1 readability score.
 *
 * @package NovaToolsSEO\Analysis
 */

namespace NovaToolsSEO\Analysis;

defined( 'ABSPATH' ) || exit;

class PassiveVoice {

	/**
	 * Auxiliary (be/have/get/do) verbs that introduce a participle.
	 */
	const AUXILIARY = '(?:am|is|are|was|were|be|been|being|get|gets|got|gotten|have|has|had|do|does|did)';

	/**
	 * Common irregular past participles.
	 */
	const IRREGULAR = '(?:written|done|gone|seen|given|taken|made|known|shown|found|told|held|kept|left|sent|brought|built|bought|caught|taught|spent|paid|put|set|read|run|come|become|driven|eaten|fallen|forgotten|hidden|ridden|risen|sung|spoken|stolen|sworn|thrown|torn|worn|won|drawn|grown|chosen|frozen|broken|chosen)';

	/**
	 * Percentage of sentences flagged as containing passive voice.
	 *
	 * @param string $text Plain text.
	 * @return float 0–100.
	 */
	public static function passive_sentence_percentage( $text ) {
		$sentences = TextProcessor::sentences( $text );
		if ( empty( $sentences ) ) {
			return 0.0;
		}

		$passive = 0;
		foreach ( $sentences as $sentence ) {
			if ( self::is_passive( $sentence ) ) {
				$passive ++;
			}
		}

		return ( $passive / count( $sentences ) ) * 100;
	}

	/**
	 * Whether a sentence contains a likely passive construction.
	 *
	 * @param string $sentence A single sentence.
	 * @return bool
	 */
	public static function is_passive( $sentence ) {
		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $sentence ) : strtolower( $sentence );
		$aux   = self::AUXILIARY;

		$patterns = array(
			// auxiliary + (optional adverb) + -ed word.
			'/' . $aux . '\s+(?:\w+\s+)?\w+ed\b/',
			// auxiliary + (optional adverb) + -en word.
			'/' . $aux . '\s+(?:\w+\s+)?\w+en\b/',
			// auxiliary + (optional adverb) + irregular participle.
			'/' . $aux . '\s+(?:\w+\s+)?' . self::IRREGULAR . '\b/',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $lower ) ) {
				return true;
			}
		}

		return false;
	}
}
