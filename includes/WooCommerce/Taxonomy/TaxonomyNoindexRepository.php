<?php

namespace NovaToolsSEO\WooCommerce\Taxonomy;

use NovaToolsSEO\Traits\Base;

class TaxonomyNoindexRepository {

	use Base;

	const OPTION_KEY = 'wseo_woo_taxonomy_noindex';

	public function get_all() {
		$data = get_option( self::OPTION_KEY );

		if ( false === $data ) {
			$this->seed_defaults();
			$data = $this->get_defaults();
		}

		return is_array( $data ) ? $data : $this->get_defaults();
	}

	public function save_all( array $taxonomies ) {
		$sanitized = array();
		$allowed_taxonomies = $this->get_woocommerce_taxonomies();
		foreach ( $taxonomies as $taxonomy => $noindex ) {
			if ( in_array( $taxonomy, $allowed_taxonomies, true ) ) {
				$sanitized[ $taxonomy ] = ! empty( $noindex ) ? true : false;
			}
		}
		return update_option( self::OPTION_KEY, $sanitized );
	}

	public function is_noindexed( $taxonomy_name ) {
		$all = $this->get_all();
		return ! empty( $all[ $taxonomy_name ] );
	}

	public function get_noindexed_taxonomies() {
		$all = $this->get_all();
		return array_keys( array_filter( $all ) );
	}

	public function seed_defaults() {
		if ( false === get_option( self::OPTION_KEY ) ) {
			add_option( self::OPTION_KEY, $this->get_defaults() );
		}
	}

	private function get_defaults() {
		$wc_taxonomies = $this->get_woocommerce_taxonomies();
		$defaults = array();

		foreach ( $wc_taxonomies as $tax ) {
			$defaults[ $tax ] = false;
		}

		return $defaults;
	}

	public function get_woocommerce_taxonomies() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$taxonomies = array( 'product_cat', 'product_tag', 'product_shipping_class' );

		$attribute_taxonomies = wc_get_attribute_taxonomy_names();
		$taxonomies = array_merge( $taxonomies, $attribute_taxonomies );

		return apply_filters( 'wseo_woo_taxonomy_noindex_list', $taxonomies );
	}
}
