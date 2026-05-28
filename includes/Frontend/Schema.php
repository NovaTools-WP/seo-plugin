<?php

namespace NovaToolsSEO\Frontend;

use NovaToolsSEO\Traits\Base;

class Schema {

	use Base;

	public function init() {
		add_action( 'wp_head', array( $this, 'output' ), 2 );
	}

	public function output() {
		$schemas = array();

		if ( is_front_page() || is_home() ) {
			$schemas[] = $this->get_website_schema();
		}

		if ( is_singular( 'post' ) ) {
			$schemas[] = $this->get_article_schema();
		}

		$schemas[] = $this->get_breadcrumb_schema();

		foreach ( $schemas as $schema ) {
			if ( ! empty( $schema ) ) {
				printf(
					'<script type="application/ld+json">%s</script>' . "\n",
					wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
				);
			}
		}
	}

	private function get_website_schema() {
		return array(
			'@context' => 'https://schema.org',
			'@type'    => 'WebSite',
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
			'potentialAction' => array(
				'@type'  => 'SearchAction',
				'target' => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	private function get_article_schema() {
		$post_id = get_queried_object_id();
		$post = get_post( $post_id );

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => is_singular( 'post' ) ? 'BlogPosting' : 'Article',
			'headline' => get_the_title( $post_id ),
			'datePublished' => get_the_date( 'c', $post_id ),
			'dateModified'  => get_the_modified_date( 'c', $post_id ),
			'author'   => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', $post->post_author ),
			),
			'publisher' => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
			),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post_id ),
			),
		);

		$image = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $image ) {
			$schema['image'] = $image;
		}

		return $schema;
	}

	private function get_breadcrumb_schema() {
		$items = $this->get_breadcrumb_items();
		if ( empty( $items ) ) {
			return array();
		}

		$list_items = array();
		$position = 1;
		foreach ( $items as $item ) {
			$list_items[] = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $item['name'],
				'item'     => $item['url'],
			);
		}

		return array(
			'@context' => 'https://schema.org',
			'@type'    => 'BreadcrumbList',
			'itemListElement' => $list_items,
		);
	}

	private function get_breadcrumb_items() {
		$items = array(
			array(
				'name' => __( 'Home', 'novatools-seo' ),
				'url'  => home_url( '/' ),
			),
		);

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			$post_type = get_post_type( $post_id );

			if ( 'post' === $post_type ) {
				$categories = get_the_category( $post_id );
				if ( ! empty( $categories ) ) {
					$items[] = array(
						'name' => $categories[0]->name,
						'url'  => get_category_link( $categories[0]->term_id ),
					);
				}
			} elseif ( 'product' === $post_type && taxonomy_exists( 'product_cat' ) ) {
				$terms = get_the_terms( $post_id, 'product_cat' );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					$items[] = array(
						'name' => $terms[0]->name,
						'url'  => get_term_link( $terms[0] ),
					);
				}
			}

			$items[] = array(
				'name' => get_the_title( $post_id ),
				'url'  => get_permalink( $post_id ),
			);
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
		}

		return $items;
	}
}
