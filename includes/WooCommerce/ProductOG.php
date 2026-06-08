<?php

namespace NovaToolsSEO\WooCommerce;

use NovaToolsSEO\Admin\Settings;
use NovaToolsSEO\Traits\Base;

class ProductOG {

	use Base;

	public function init() {}

	public function render( $product ) {
		$settings = Settings::get_instance();
		$pinterest_enabled = $settings->get( 'social_pinterest_rich_pins', '1' );

		$tags = array();

		$tags['og:type'] = 'product';
		$tags['og:title'] = $this->get_title( $product );
		$tags['og:description'] = $this->get_description( $product );
		$tags['og:url'] = $product->get_permalink();
		$tags['og:site_name'] = get_bloginfo( 'name' );
		$tags['og:locale'] = get_locale();

		$image = $this->get_image( $product, $settings );
		if ( $image ) {
			$tags['og:image'] = $image;
		}

		$tags['product:price:amount'] = $this->get_price( $product );
		$tags['product:price:currency'] = get_woocommerce_currency();
		$tags['product:availability'] = $this->get_availability( $product );

		if ( $pinterest_enabled === '1' ) {
			$sku = $product->get_sku();
			if ( $sku ) {
				$tags['product:retailer_item_id'] = $sku;
			}
		}

		foreach ( $tags as $property => $content ) {
			printf(
				'<meta property="%s" content="%s">' . "\n",
				esc_attr( $property ),
				esc_attr( $content )
			);
		}
	}

	private function get_title( $product ) {
		$post_id = $product->get_id();
		$seo_title = get_post_meta( $post_id, '_wseo_title', true );

		if ( ! empty( $seo_title ) ) {
			return $seo_title;
		}

		return $product->get_title();
	}

	private function get_description( $product ) {
		$post_id = $product->get_id();
		$seo_desc = get_post_meta( $post_id, '_wseo_description', true );

		if ( ! empty( $seo_desc ) ) {
			return $seo_desc;
		}

		return wp_strip_all_tags( $product->get_short_description() );
	}

	private function get_price( $product ) {
		if ( $product->is_type( 'variable' ) ) {
			return $product->get_variation_price( 'min', true );
		}

		if ( $product->is_on_sale() && $product->get_sale_price() ) {
			return wc_format_decimal( $product->get_sale_price(), '' );
		}

		return wc_format_decimal( $product->get_regular_price(), '' );
	}

	private function get_availability( $product ) {
		$status = $product->get_stock_status();

		$map = array(
			'instock'     => 'instock',
			'outofstock'  => 'oos',
			'onbackorder' => 'pending',
		);

		return $map[ $status ] ?? 'instock';
	}

	private function get_image( $product, $settings ) {
		$post_id = $product->get_id();

		// Custom OG image takes priority.
		$og_image = get_post_meta( $post_id, '_wseo_og_image', true );
		if ( ! empty( $og_image ) ) {
			return $og_image;
		}

		// Fall back to product featured image.
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			return wp_get_attachment_image_url( $image_id, 'full' );
		}

		return $settings->get( 'social_og_default_image', '' );
	}
}
