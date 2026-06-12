<?php

namespace NovaToolsSEO\Core;

class Tokens {

	public static function replace( $template, $post = null ) {
		if ( empty( $template ) ) {
			return '';
		}

		$tokens = self::get_token_values( $post );
		$sep    = $tokens['%%sep%%'] ?? '-';
		$ph     = "\x00";

		$replacements = array();
		foreach ( $tokens as $token => $value ) {
			$replacements[ $token ] = ( $value === '' && $token !== '%%sep%%' ) ? $ph : $value;
		}

		$result = strtr( $template, $replacements );

		$escaped = preg_quote( $sep, '~' );
		$ph_e    = preg_quote( $ph, '~' );
		$result  = preg_replace( '~' . $ph_e . '\s*' . $escaped . '|' . $escaped . '\s*' . $ph_e . '~', '', $result );
		$result  = str_replace( $ph, '', $result );
		$result  = preg_replace( '~\s+~', ' ', $result );

		return trim( $result );
	}

	public static function get_token_values( $post = null ) {
		$sep  = apply_filters( 'wseo_separator', '-' );
		$name = get_bloginfo( 'name' );

		$values = array(
			'%%sitename%%'  => $name,
			'%%sitedesc%%'  => get_bloginfo( 'description' ),
			'%%sep%%'       => $sep,
			'%%page%%'      => '',
			'%%category%%'  => '',
			'%%title%%'     => '',
		);

		if ( $post ) {
			$values['%%title%%'] = get_the_title( $post );

			$categories = get_the_category( $post->ID );
			if ( ! empty( $categories ) ) {
				$values['%%category%%'] = $categories[0]->name;
			}
		} elseif ( is_singular() ) {
			global $post;
			if ( $post ) {
				$values['%%title%%'] = get_the_title( $post );
				$categories = get_the_category( $post->ID );
				if ( ! empty( $categories ) ) {
					$values['%%category%%'] = $categories[0]->name;
				}
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$values['%%title%%'] = single_term_title( '', false );
		} elseif ( is_post_type_archive() ) {
			$values['%%title%%'] = post_type_archive_title( '', false );
		} elseif ( is_author() ) {
			$values['%%title%%'] = get_the_author();
		} elseif ( is_date() ) {
			$values['%%title%%'] = get_the_date();
		} elseif ( is_search() ) {
			$values['%%title%%'] = sprintf( __( 'Search results for: %s', 'novatools-seo' ), get_search_query() );
		} elseif ( is_home() ) {
			$values['%%title%%'] = single_post_title( '', false );
		}

		$paged = get_query_var( 'paged' );
		if ( $paged > 1 ) {
			$values['%%page%%'] = sprintf(
				__( 'Page %d', 'novatools-seo' ),
				$paged
			);
		}

		return $values;
	}
}
