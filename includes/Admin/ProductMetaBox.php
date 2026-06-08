<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Traits\Base;

class ProductMetaBox {

	use Base;

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_filter( 'woocommerce_product_data_tabs', array( $this, 'register_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_meta' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function register_tab( $tabs ) {
		$tabs['wseo_schema'] = array(
			'label'    => __( 'SEO & Schema', 'novatools-seo' ),
			'target'   => 'wseo_schema_product_data',
			'class'    => array(),
			'priority' => 80,
		);
		return $tabs;
	}

	public function render_panel() {
		global $post;
		$meta = $this->get_product_seo_data( $post->ID );
		?>
		<div id="wseo_schema_product_data" class="panel woocommerce_options_panel">
			<div id="wseo-product-schema-tab"
				data-post-id="<?php echo esc_attr( $post->ID ); ?>"
				data-meta="<?php echo esc_attr( wp_json_encode( $meta ) ); ?>">
			</div>
		</div>
		<?php
	}

	public function save_meta( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'_wseo_gtin'             => 'sanitize_text_field',
			'_wseo_mpn'              => 'sanitize_text_field',
			'_wseo_isbn'             => 'sanitize_text_field',
			'_wseo_brand'            => 'sanitize_text_field',
			'_wseo_item_condition'   => 'sanitize_text_field',
			'_wseo_primary_category' => 'absint',
			// General SEO fields (score dependencies) — ensures persistence
			// via woocommerce_process_product_meta even when the SEO meta
			// box nonce is absent from the POST body.
			'_wseo_title'            => 'sanitize_text_field',
			'_wseo_description'      => 'sanitize_text_field',
			'_wseo_og_image'         => 'sanitize_text_field',
		);

		foreach ( $fields as $key => $sanitizer ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, call_user_func( $sanitizer, $_POST[ $key ] ) );
			}
		}

		// FAQ array meta (supports both JSON string and array form data)
		if ( isset( $_POST['_wseo_faq'] ) ) {
			$raw = $_POST['_wseo_faq'];
			$items = null;

			if ( is_string( $raw ) ) {
				$decoded = json_decode( wp_unslash( $raw ), true );
				if ( is_array( $decoded ) ) {
					$items = $decoded;
				}
			} elseif ( is_array( $raw ) ) {
				$items = $raw;
			}

			if ( is_array( $items ) ) {
				$faq = array();
				foreach ( $items as $item ) {
					$q = sanitize_text_field( $item['question'] ?? '' );
					$a = sanitize_textarea_field( $item['answer'] ?? '' );
					if ( $q || $a ) {
						$faq[] = array( 'question' => $q, 'answer' => $a );
					}
				}
				update_post_meta( $post_id, '_wseo_faq', $faq );
			}
		}

		// LocalInventory toggle
		if ( isset( $_POST['_wseo_local_inventory'] ) ) {
			update_post_meta( $post_id, '_wseo_local_inventory', $_POST['_wseo_local_inventory'] ? '1' : '' );
		}
	}

	public function enqueue_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		if ( get_post_type() !== 'product' ) {
			return;
		}

		$has_brand_taxonomy = taxonomy_exists( 'product_brand' );

		wp_localize_script( 'novatools-seo-addon', 'wseoProductTab', array(
			'hasBrandTaxonomy' => $has_brand_taxonomy,
		) );
	}

	private function get_product_seo_data( $post_id ) {
		$keys = array( '_wseo_gtin', '_wseo_mpn', '_wseo_isbn', '_wseo_brand', '_wseo_item_condition', '_wseo_primary_category', '_wseo_faq', '_wseo_local_inventory' );

		$data = array();
		foreach ( $keys as $key ) {
			$data[ $key ] = get_post_meta( $post_id, $key, true );
		}

		// Add gallery image IDs for alt-text scanning
		$product = wc_get_product( $post_id );
		if ( $product ) {
			$gallery_ids = $product->get_gallery_image_ids();
			$featured_id = $product->get_image_id();
			if ( $featured_id ) {
				array_unshift( $gallery_ids, $featured_id );
				$gallery_ids = array_unique( $gallery_ids );
			}
			$data['_product_image_gallery'] = implode( ',', $gallery_ids );
		}

		return $data;
	}
}
