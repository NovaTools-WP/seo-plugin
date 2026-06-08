<?php

namespace NovaToolsSEO\Sitemaps;

use NovaToolsSEO\Traits\Base;

class StockTracker {

	use Base;

	public function init() {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return;
		}

		add_action( 'woocommerce_product_set_stock_status', array( $this, 'handle_stock_status_change' ), 10, 2 );
	}

	public function handle_stock_status_change( $product_id, $status ) {
		if ( 'outofstock' === $status ) {
			update_post_meta( $product_id, '_wseo_outofstock_since', time() );
		} else {
			delete_post_meta( $product_id, '_wseo_outofstock_since' );
		}
	}
}
