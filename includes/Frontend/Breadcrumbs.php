<?php

namespace NovaToolsSEO\Frontend;

use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

class Breadcrumbs {

	use Base;

	public function init() {
		add_shortcode( 'wseo_breadcrumbs', array( $this, 'render_shortcode' ) );
	}

	public function render( $echo = true ) {
		$items = $this->get_breadcrumb_items();

		if ( empty( $items ) ) {
			return '';
		}

		$html = '<nav class="wseo-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'novatools-seo' ) . '">';
		$html .= '<ol class="wseo-breadcrumbs-list" itemscope itemtype="https://schema.org/BreadcrumbList">';

		foreach ( $items as $index => $item ) {
			$position = $index + 1;
			$html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';

			if ( ! empty( $item['url'] ) ) {
				$html .= '<a itemprop="item" href="' . esc_url( $item['url'] ) . '">';
				$html .= '<span itemprop="name">' . esc_html( $item['name'] ) . '</span>';
				$html .= '</a>';
			} else {
				$html .= '<span itemprop="name">' . esc_html( $item['name'] ) . '</span>';
			}

			$html .= '<meta itemprop="position" content="' . esc_attr( $position ) . '" />';
			$html .= '</li>';
		}

		$html .= '</ol>';
		$html .= '</nav>';

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function render_shortcode( $atts ) {
		return $this->render( false );
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
					$primary_cat = $this->get_primary_category( $post_id );
					$cat = $primary_cat ? $primary_cat : $categories[0];
					$items[] = array(
						'name' => $cat->name,
						'url'  => get_category_link( $cat->term_id ),
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
				'url'  => '',
			);
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$items[] = array(
				'name' => $term->name,
				'url'  => '',
			);
		} elseif ( is_post_type_archive() ) {
			$items[] = array(
				'name' => post_type_archive_title( '', false ),
				'url'  => '',
			);
		} elseif ( is_home() && ! is_front_page() ) {
			$items[] = array(
				'name' => single_post_title( '', false ),
				'url'  => '',
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

	private function get_primary_category( $post_id ) {
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

function wseo_breadcrumbs( $echo = true ) {
	return Breadcrumbs::get_instance()->render( $echo );
}
