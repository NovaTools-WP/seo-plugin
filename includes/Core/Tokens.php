<?php

namespace NovaToolsSEO\Core;

class Tokens {

	public static function replace( $template, $post = null ) {
		if ( empty( $template ) ) {
			return '';
		}

		$tokens = self::get_token_values( $post );

		return str_replace(
			array_keys( $tokens ),
			array_values( $tokens ),
			$template
		);
	}

	public static function get_token_values( $post = null ) {
		$sep  = apply_filters( 'wseo_separator', '-' );
		$name = get_bloginfo( 'name' );

		$values = array(
			'%%sitename%%' => $name,
			'%%sep%%'      => $sep,
			'%%page%%'     => '',
			'%%category%%' => '',
			'%%title%%'    => '',
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
