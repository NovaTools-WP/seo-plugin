<?php

namespace NovaToolsSEO\WooCommerce\Taxonomy;

use NovaToolsSEO\Traits\Base;

class TaxonomyNoindexEnforcer {

	use Base;

	public function is_noindexed_taxonomy() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		if ( ! ( is_category() || is_tag() || is_tax() ) ) {
			return false;
		}

		$term = get_queried_object();
		if ( ! $term || ! isset( $term->taxonomy ) ) {
			return false;
		}

		$repo = TaxonomyNoindexRepository::get_instance();
		return $repo->is_noindexed( $term->taxonomy );
	}
}
