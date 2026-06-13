<?php

namespace NovaToolsSEO\Frontend;

use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

class ProductSchema {

	use Base;

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_filter( 'wseo_register_schemas', array( $this, 'register_schemas' ) );

		// Prevent WooCommerce from outputting duplicate structured data.
		// NovaTools SEO handles Product, BreadcrumbList, WebSite, and more.
		add_action( 'wp', function () {
			if ( isset( WC()->structured_data ) ) {
				remove_action( 'wp_footer', array( WC()->structured_data, 'output_structured_data' ), 10 );
			}
		} );
	}

	public function register_schemas( $schemas ) {
		if ( ! is_singular( 'product' ) ) {
			return $schemas;
		}

		$enabled = apply_filters( 'wseo_product_schema_enabled', true );
		if ( ! $enabled ) {
			return $schemas;
		}

		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product ) {
			return $schemas;
		}

		$schema = $product->is_type( 'variable' )
			? $this->generate_product_group_schema( $product )
			: $this->generate_simple_product_schema( $product );

		if ( ! empty( $schema ) ) {
			$schemas[] = apply_filters( 'wseo_product_schema_data', $schema, $product );
		}

		// FAQPage schema.
		$faq_schema = $this->get_faq_schema( $product );
		if ( ! empty( $faq_schema ) ) {
			$schemas[] = $faq_schema;
		}

		// LocalInventory schema.
		$inventory_schema = $this->get_local_inventory_schema( $product );
		if ( ! empty( $inventory_schema ) ) {
			$schemas[] = $inventory_schema;
		}

		return $schemas;
	}

	private function generate_simple_product_schema( $product ) {
		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $product->get_title(),
			'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
			'sku'         => $product->get_sku() ?: '',
			'image'       => wp_get_attachment_url( $product->get_image_id() ) ?: '',
		);

		$schema = $this->add_identifiers( $schema, $product->get_id() );
		$schema = $this->add_brand( $schema, $product->get_id() );

		$offer = $this->generate_offer( $product );
		if ( $offer ) {
			$schema['offers'] = $offer;
		}

		$schema = $this->add_reviews( $schema, $product->get_id() );

		return array_filter( $schema, function ( $v ) {
			return $v !== '' && $v !== array();
		} );
	}

	private function generate_product_group_schema( $product ) {
		$variations = $this->get_purchasable_variations( $product );

		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'ProductGroup',
			'name'        => $product->get_title(),
			'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
			'image'       => wp_get_attachment_url( $product->get_image_id() ) ?: '',
		);

		$schema = $this->add_brand( $schema, $product->get_id() );

		// AggregateOffer
		$aggregate_offer = $this->generate_aggregate_offer( $product, $variations );
		if ( $aggregate_offer ) {
			$schema['offers'] = $aggregate_offer;
		}

		// variesBy
		$varies_by = $this->generate_varies_by( $product );
		if ( ! empty( $varies_by ) ) {
			$schema['variesBy'] = $varies_by;
		}

		// hasVariant
		$variants = $this->generate_has_variants( $product, $variations );
		if ( ! empty( $variants ) ) {
			$schema['hasVariant'] = $variants;
		}

		// Reviews on the parent product
		$schema = $this->add_reviews( $schema, $product->get_id() );

		return array_filter( $schema, function ( $v ) {
			return $v !== '' && $v !== array();
		} );
	}

	private function add_identifiers( $schema, $post_id ) {
		$gtin = get_post_meta( $post_id, '_wseo_gtin', true );
		$mpn  = get_post_meta( $post_id, '_wseo_mpn', true );
		$isbn = get_post_meta( $post_id, '_wseo_isbn', true );

		if ( $gtin ) {
			$schema['gtin'] = $gtin;
		}
		if ( $mpn ) {
			$schema['mpn'] = $mpn;
		}
		if ( $isbn ) {
			$schema['isbn'] = $isbn;
		}

		return $schema;
	}

	private function add_brand( $schema, $post_id ) {
		$brand = get_post_meta( $post_id, '_wseo_brand', true );
		if ( $brand ) {
			$brand_data = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);

			// sameAs from Brand term meta
			$brand_terms = get_the_terms( $post_id, 'product_brand' );
			if ( empty( $brand_terms ) || is_wp_error( $brand_terms ) ) {
				$brand_terms = get_the_terms( $post_id, 'brand' );
			}
			if ( ! empty( $brand_terms ) && ! is_wp_error( $brand_terms ) ) {
				$sameas = get_term_meta( $brand_terms[0]->term_id, '_wseo_sameas', true );
				if ( ! empty( $sameas ) && is_array( $sameas ) ) {
					$brand_data['sameAs'] = array_values( array_filter( $sameas ) );
				}
			}

			$schema['brand'] = $brand_data;
		}
		return $schema;
	}

	private function generate_offer( $product ) {
		$price    = $product->get_price();
		$currency = get_woocommerce_currency();

		if ( '' === $price ) {
			return null;
		}

		$offer = array(
			'@type'         => 'Offer',
			'price'         => $price,
			'priceCurrency' => $currency,
			'availability'  => $this->get_availability( $product ),
		);

		$item_condition = get_post_meta( $product->get_id(), '_wseo_item_condition', true );
		if ( $item_condition ) {
			$offer['itemCondition'] = 'https://schema.org/' . $item_condition;
		}

		$sale_end = $product->get_date_on_sale_to();
		if ( $sale_end ) {
			$offer['priceValidUntil'] = $sale_end->date( 'c' );
		}

		return $offer;
	}

	private function get_availability( $product ) {
		if ( ! $product->is_in_stock() ) {
			return 'https://schema.org/OutOfStock';
		}

		if ( $product->is_on_backorder( 1 ) ) {
			return 'https://schema.org/PreOrder';
		}

		return 'https://schema.org/InStock';
	}

	private function get_purchasable_variations( $product ) {
		$variation_ids = $product->get_children();
		if ( empty( $variation_ids ) ) {
			return array();
		}

		$variations = array();

		$variation_objects = wc_get_products( array(
			'include' => $variation_ids,
			'limit'   => -1,
			'return'  => 'objects',
		) );

		foreach ( $variation_objects as $variation ) {
			if ( $variation && $variation->is_published() && $variation->is_purchasable() && '' !== $variation->get_price() ) {
				$variations[] = $variation;
			}
		}

		return $variations;
	}

	private function generate_aggregate_offer( $product, $variations ) {
		if ( empty( $variations ) ) {
			return null;
		}

		$prices = array();
		foreach ( $variations as $v ) {
			$p = $v->get_price();
			if ( '' !== $p ) {
				$prices[] = (float) $p;
			}
		}

		if ( empty( $prices ) ) {
			return null;
		}

		return array(
			'@type'         => 'AggregateOffer',
			'lowPrice'      => (string) min( $prices ),
			'highPrice'     => (string) max( $prices ),
			'priceCurrency' => get_woocommerce_currency(),
			'offerCount'    => count( $prices ),
		);
	}

	private function generate_has_variants( $product, $variations ) {
		$mappings = $this->get_attribute_mappings();
		$variants = array();

		foreach ( $variations as $variation ) {
			$variant_schema = array(
				'@type' => 'Product',
				'name'  => $this->get_variation_name( $product, $variation ),
				'sku'   => $variation->get_sku() ?: '',
			);

			$image = wp_get_attachment_url( $variation->get_image_id() );
			if ( $image ) {
				$variant_schema['image'] = $image;
			}

			// Add mapped attributes
			$attributes = $variation->get_attributes();
			foreach ( $attributes as $attr_slug => $value ) {
				if ( ! $value ) {
					continue;
				}
				$taxonomy = sanitize_title( $attr_slug );
				if ( isset( $mappings[ $taxonomy ] ) ) {
					$term = get_term_by( 'slug', $value, $taxonomy );
					$variant_schema[ $mappings[ $taxonomy ] ] = $term ? $term->name : $value;
				}
			}

			$offer = $this->generate_offer( $variation );
			if ( $offer ) {
				$variant_schema['offers'] = $offer;
			}

			$variants[] = array_filter( $variant_schema, function ( $v ) {
				return $v !== '' && $v !== array();
			} );
		}

		return $variants;
	}

	private function generate_varies_by( $product ) {
		$mappings = $this->get_attribute_mappings();
		$varies_by = array();

		$attributes = $product->get_attributes();
		foreach ( $attributes as $attribute ) {
			if ( ! $attribute instanceof \WC_Product_Attribute ) {
				continue;
			}
			if ( ! $attribute->get_variation() ) {
				continue;
			}

			$slug = $attribute->get_name();
			if ( isset( $mappings[ $slug ] ) ) {
				$varies_by[] = 'https://schema.org/' . $mappings[ $slug ];
			}
		}

		return $varies_by;
	}

	private function get_variation_name( $parent, $variation ) {
		$attributes = $variation->get_attributes();
		$parts = array();

		foreach ( $attributes as $slug => $value ) {
			if ( ! $value ) {
				continue;
			}
			$term = get_term_by( 'slug', $value, $slug );
			$parts[] = $term ? $term->name : $value;
		}

		return $parent->get_title() . ( ! empty( $parts ) ? ' - ' . implode( ', ', $parts ) : '' );
	}

	private function add_reviews( $schema, $product_id ) {
		$review_data = $this->get_product_reviews( $product_id );

		if ( $review_data['count'] > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) round( $review_data['average'], 1 ),
				'reviewCount' => $review_data['count'],
				'bestRating'  => '5',
			);

			$review_objects = $this->generate_review_objects( $product_id );
			if ( ! empty( $review_objects ) ) {
				$schema['review'] = $review_objects;
			}
		}

		return $schema;
	}

	private function get_product_reviews( $product_id ) {
		global $wpdb;

		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) as review_count, AVG(CAST(m.meta_value AS UNSIGNED)) as avg_rating
			 FROM {$wpdb->comments} c
			 INNER JOIN {$wpdb->commentmeta} m ON c.comment_ID = m.comment_ID AND m.meta_key = 'rating'
			 WHERE c.comment_post_ID = %d AND c.comment_approved = '1' AND c.comment_type = 'review'",
			$product_id
		) );

		if ( ! $stats || 0 === (int) $stats->review_count ) {
			return array( 'average' => 0, 'count' => 0 );
		}

		return array(
			'average' => (float) $stats->avg_rating,
			'count'   => (int) $stats->review_count,
		);
	}

	private function generate_review_objects( $product_id ) {
		$args = array(
			'post_id' => $product_id,
			'status'  => 'approve',
			'type'    => 'review',
			'meta_key'   => 'rating',
			'meta_value' => 4,
			'meta_compare' => '>=',
			'number'  => 5,
			'orderby' => 'comment_date',
			'order'   => 'DESC',
		);

		$reviews = get_comments( $args );
		$objects = array();

		if ( ! empty( $reviews ) ) {
			update_meta_cache( 'comment', wp_list_pluck( $reviews, 'comment_ID' ) );
		}

		foreach ( $reviews as $review ) {
			$rating = (int) get_comment_meta( $review->comment_ID, 'rating', true );

			$objects[] = array(
				'@type'         => 'Review',
				'author'        => array(
					'@type' => 'Person',
					'name'  => $review->comment_author,
				),
				'datePublished' => date( 'c', strtotime( $review->comment_date ) ),
				'reviewRating'  => array(
					'@type'       => 'Rating',
					'ratingValue' => (string) $rating,
					'bestRating'  => '5',
				),
				'reviewBody'    => wp_strip_all_tags( $review->comment_content ),
			);
		}

		return $objects;
	}

	private function get_faq_schema( $product ) {
		$faq = get_post_meta( $product->get_id(), '_wseo_faq', true );
		if ( empty( $faq ) || ! is_array( $faq ) ) {
			return array();
		}

		$entities = array();
		foreach ( $faq as $item ) {
			if ( ! empty( $item['question'] ) && ! empty( $item['answer'] ) ) {
				$entities[] = array(
					'@type'          => 'Question',
					'name'           => $item['question'],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $item['answer'],
					),
				);
			}
		}

		if ( empty( $entities ) ) {
			return array();
		}

		return array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
	}

	private function get_local_inventory_schema( $product ) {
		$enabled = get_post_meta( $product->get_id(), '_wseo_local_inventory', true );
		if ( ! $enabled ) {
			return array();
		}

		if ( ! $product->managing_stock() ) {
			return array();
		}

		$local_seo = get_option( 'wseo_local_seo', array() );
		if ( empty( $local_seo['business_name'] ) ) {
			return array();
		}

		$stock = $product->get_stock_quantity();
		if ( null === $stock ) {
			return array();
		}

		return array(
			'@context' => 'https://schema.org',
			'@type'    => 'LocalInventory',
			'itemOffered' => array(
				'@type' => 'Product',
				'sku'   => $product->get_sku() ?: '',
			),
			'inventoryLevel' => array(
				'@type'  => 'QuantitativeValue',
				'value'  => $stock,
			),
			'availableAtOrFrom' => array(
				'@type' => 'LocalBusiness',
				'name'  => $local_seo['business_name'],
			),
		);
	}

	private function get_attribute_mappings() {
		$mappings = get_option( 'wseo_attribute_mappings', array() );
		$map = array();

		if ( is_array( $mappings ) ) {
			foreach ( $mappings as $mapping ) {
				if ( isset( $mapping['attribute_slug'] ) && isset( $mapping['schema_property'] ) ) {
					$map[ $mapping['attribute_slug'] ] = $mapping['schema_property'];
				}
			}
		}

		return $map;
	}
}
