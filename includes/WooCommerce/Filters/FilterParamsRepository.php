<?php

namespace NovaToolsSEO\WooCommerce\Filters;

use NovaToolsSEO\Traits\Base;

class FilterParamsRepository {

	use Base;

	const OPTION_KEY = 'wseo_woo_filter_params';

	private $defaults = array(
		'min_price',
		'max_price',
		'filter_*',
		'query_type_*',
		'orderby',
		'rating_filter',
	);

	public function get_params() {
		$params = get_option( self::OPTION_KEY );

		if ( false === $params ) {
			$this->seed_defaults();
			$params = $this->defaults;
		}

		return is_array( $params ) ? $params : $this->defaults;
	}

	public function save_params( array $params ) {
		$sanitized = array_map( 'sanitize_text_field', $params );
		return update_option( self::OPTION_KEY, $sanitized );
	}

	public function seed_defaults() {
		if ( false === get_option( self::OPTION_KEY ) ) {
			add_option( self::OPTION_KEY, $this->defaults );
		}
	}
}
