<?php

namespace NovaToolsSEO\WooCommerce;

use NovaToolsSEO\Admin\Settings;
use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

class PinterestNamespace {

	use Base;

	public function init() {
		add_filter( 'language_attributes', array( $this, 'add_product_namespace' ) );
	}

	public function add_product_namespace( $output ) {
		if ( is_singular( 'product' ) ) {
			$settings = Settings::get_instance();
			if ( $settings->get( 'social_pinterest_rich_pins', '1' ) === '1' ) {
				$output .= ' xmlns:product="http://ogp.me/ns/product#"';
			}
		}

		return $output;
	}
}
