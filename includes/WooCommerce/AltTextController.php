<?php

namespace NovaToolsSEO\WooCommerce;

use NovaToolsSEO\Traits\Base;

class AltTextController {

	use Base;

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'novatools-seo/v1', '/products/(?P<id>\d+)/bulk-alt-text', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'bulk_generate' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );
	}

	public function check_permission( $request ) {
		$post_id = (int) $request['id'];
		return current_user_can( 'edit_post', $post_id );
	}

	public function bulk_generate( $request ) {
		$post_id  = (int) $request['id'];
		$product  = wc_get_product( $post_id );

		if ( ! $product ) {
			return new \WP_Error( 'not_found', 'Product not found', array( 'status' => 404 ) );
		}

		$gallery_ids   = $product->get_gallery_image_ids();
		$featured_id   = $product->get_image_id();

		// Include featured image in the set
		if ( $featured_id ) {
			array_unshift( $gallery_ids, $featured_id );
			$gallery_ids = array_unique( $gallery_ids );
		}

		if ( empty( $gallery_ids ) ) {
			return rest_ensure_response( array(
				'images'  => array(),
				'updated' => 0,
				'skipped' => 0,
			) );
		}

		$product_title = $product->get_title();
		$primary_cat   = $this->get_primary_category_name( $post_id );
		$updated       = 0;
		$skipped       = 0;
		$images        = array();

		$index = 1;
		foreach ( $gallery_ids as $attachment_id ) {
			$current_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$has_alt     = ! empty( trim( $current_alt ) );

			if ( ! $has_alt ) {
				// Always include a category segment per spec format:
				// [Product Title] - [Primary Category] - Image [Index]
				$category_name = $primary_cat ?: __( 'Uncategorized', 'novatools-seo' );
				$new_alt       = trim( $product_title ) . ' - ' . $category_name . ' - Image ' . $index;

				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $new_alt ) );
				$current_alt = $new_alt;
				$updated++;
			} else {
				$skipped++;
			}

			$images[] = array(
				'id'        => $attachment_id,
				'title'     => get_the_title( $attachment_id ),
				'alt'       => $current_alt,
				'sourceUrl' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) ?: '',
			);

			$index++;
		}

		return rest_ensure_response( array(
			'images'  => $images,
			'updated' => $updated,
			'skipped' => $skipped,
		) );
	}

	private function get_primary_category_name( $post_id ) {
		$primary_term_id = get_post_meta( $post_id, '_wseo_primary_category', true );

		if ( $primary_term_id ) {
			$term = get_term( $primary_term_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term->name;
			}
		}

		// Fallback to first category
		$terms = get_the_terms( $post_id, 'product_cat' );
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			return $terms[0]->name;
		}

		return '';
	}
}
