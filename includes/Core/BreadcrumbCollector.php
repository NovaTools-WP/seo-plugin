<?php

namespace NovaToolsSEO\Core;

defined( 'ABSPATH' ) || exit;

class BreadcrumbCollector {

	public function get_items(): array {
		$items = array(
			array(
				'name' => __( 'Home', 'novatools-seo' ),
				'url'  => home_url( '/' ),
			),
		);

		if ( is_singular() ) {
			$items = array_merge( $items, $this->get_singular_items() );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$items[] = array(
				'name' => $term->name,
				'url'  => get_term_link( $term ),
			);
		} elseif ( is_post_type_archive() ) {
			$items[] = array(
				'name' => post_type_archive_title( '', false ),
				'url'  => get_post_type_archive_link( get_post_type() ),
			);
		} elseif ( is_home() && ! is_front_page() ) {
			$items[] = array(
				'name' => single_post_title( '', false ),
				'url'  => get_permalink( get_option( 'page_for_posts' ) ),
			);
		} elseif ( is_search() ) {
			$items[] = array(
				'name' => sprintf( __( 'Search results for: %s', 'novatools-seo' ), get_search_query() ),
				'url'  => '',
			);
		} elseif ( is_404() ) {
			$items[] = array(
				'name' => __( 'Page not found', 'novatools-seo' ),
				'url'  => '',
			);
		}

		return $items;
	}

	private function get_singular_items(): array {
		$items   = array();
		$post_id = get_queried_object_id();
		$post_type = get_post_type( $post_id );

		if ( 'post' === $post_type ) {
			$primary_cat = $this->get_primary_category( $post_id );
			if ( $primary_cat ) {
				$items[] = array(
					'name' => $primary_cat->name,
					'url'  => get_category_link( $primary_cat->term_id ),
				);
			} else {
				$categories = get_the_category( $post_id );
				if ( ! empty( $categories ) ) {
					$items[] = array(
						'name' => $categories[0]->name,
						'url'  => get_category_link( $categories[0]->term_id ),
					);
				}
			}
		} elseif ( 'product' === $post_type && taxonomy_exists( 'product_cat' ) ) {
			$terms = get_the_terms( $post_id, 'product_cat' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$items[] = array(
					'name' => $terms[0]->name,
					'url'  => get_term_link( $terms[0] ),
				);
			}
		} elseif ( is_post_type_hierarchical( $post_type ) ) {
			$ancestors = get_post_ancestors( $post_id );
			if ( ! empty( $ancestors ) ) {
				$ancestors = array_reverse( $ancestors );
				foreach ( $ancestors as $ancestor_id ) {
					$items[] = array(
						'name' => get_the_title( $ancestor_id ),
						'url'  => get_permalink( $ancestor_id ),
					);
				}
			}
		}

		$items[] = array(
			'name' => get_the_title( $post_id ),
			'url'  => get_permalink( $post_id ),
		);

		return $items;
	}

	private function get_primary_category( int $post_id ) {
		$primary = get_post_meta( $post_id, '_wseo_primary_category', true );
		if ( $primary ) {
			$term = get_term( $primary, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term;
			}
		}

		return null;
	}
}
