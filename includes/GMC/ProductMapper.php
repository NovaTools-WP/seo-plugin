<?php

namespace NovaToolsSEO\GMC;

defined( 'ABSPATH' ) || exit;

class ProductMapper {

	public static function map( $product ) {
		if ( ! $product || ! ( $product instanceof \WC_Product ) ) {
			return array();
		}

		if ( $product->is_type( 'variable' ) ) {
			return static::map_variable( $product );
		}

		return array( static::map_single( $product ) );
	}

	public static function map_single( $product, $parent_id = null ) {
		$mapping = array(
			'offerId'       => static::get_offer_id( $product ),
			'title'         => static::get_title( $product ),
			'description'   => static::get_description( $product ),
			'link'          => $product->get_permalink(),
			'imageLink'     => static::get_image( $product ),
			'price'         => static::get_price( $product ),
			'availability'  => static::get_availability( $product ),
			'condition'     => static::get_condition( $product ),
			'productTypes'  => static::get_product_types( $product ),
		);

		$brand = static::get_brand( $product );
		if ( $brand ) {
			$mapping['brand'] = $brand;
		}

		$gtin = static::get_gtin( $product );
		if ( $gtin ) {
			$mapping['gtin'] = $gtin;
		}

		$mpn = static::get_mpn( $product );
		if ( $mpn ) {
			$mapping['mpn'] = $mpn;
		}

		if ( $parent_id ) {
			$mapping['itemGroupId'] = (string) $parent_id;
		}

		return array_filter( $mapping, function ( $v ) {
			return null !== $v && '' !== $v;
		});
	}

	private static function map_variable( $product ) {
		$items     = array();
		$parent_id = $product->get_id();
		$variants  = $product->get_children();

		foreach ( $variants as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->exists() ) {
				continue;
			}

			$items[] = static::map_single( $variation, $parent_id );
		}

		return $items;
	}

	private static function get_offer_id( $product ) {
		$sku = $product->get_sku();
		return $sku ? $sku : (string) $product->get_id();
	}

	private static function get_title( $product ) {
		$seo_title = get_post_meta( $product->get_id(), '_wseo_title', true );
		if ( ! empty( $seo_title ) ) {
			return $seo_title;
		}
		return $product->get_title();
	}

	private static function get_description( $product ) {
		$short = $product->get_short_description();
		if ( ! empty( $short ) ) {
			return wp_strip_all_tags( $short );
		}

		$full = $product->get_description();
		if ( ! empty( $full ) ) {
			return wp_strip_all_tags( $full );
		}

		$parent = wc_get_product( $product->get_parent_id() );
		if ( $parent ) {
			$parent_short = $parent->get_short_description();
			if ( ! empty( $parent_short ) ) {
				return wp_strip_all_tags( $parent_short );
			}
			$parent_full = $parent->get_description();
			if ( ! empty( $parent_full ) ) {
				return wp_strip_all_tags( $parent_full );
			}
		}

		return '';
	}

	private static function get_image( $product ) {
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			return wp_get_attachment_url( $image_id );
		}

		$parent = wc_get_product( $product->get_parent_id() );
		if ( $parent ) {
			$parent_image = $parent->get_image_id();
			if ( $parent_image ) {
				return wp_get_attachment_url( $parent_image );
			}
		}

		return '';
	}

	private static function get_price( $product ) {
		return array(
			'value'    => $product->get_price(),
			'currency' => get_woocommerce_currency(),
		);
	}

	private static function get_availability( $product ) {
		$status = $product->get_stock_status();

		switch ( $status ) {
			case 'instock':
				return 'in stock';
			case 'onbackorder':
				return 'preorder';
			case 'outofstock':
			default:
				return 'out of stock';
		}
	}

	private static function get_brand( $product ) {
		$terms = get_the_terms( $product->get_id(), 'product_brand' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			return $terms[0]->name;
		}

		$brand = get_post_meta( $product->get_id(), '_brand', true );
		if ( ! empty( $brand ) ) {
			return $brand;
		}

		return '';
	}

	private static function get_gtin( $product ) {
		return get_post_meta( $product->get_id(), '_gtin', true ) ?: '';
	}

	private static function get_mpn( $product ) {
		return get_post_meta( $product->get_id(), '_mpn', true ) ?: '';
	}

	private static function get_condition( $product ) {
		$raw = get_post_meta( $product->get_id(), '_item_condition', true );

		$map = array(
			'new'         => 'new',
			'used'        => 'used',
			'refurbished' => 'refurbished',
		);

		return $map[ $raw ] ?? 'new';
	}

	private static function get_product_types( $product ) {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}

		$paths = array();
		foreach ( $terms as $term ) {
			$chain   = array();
			$ancestors = get_ancestors( $term->term_id, 'product_cat' );
			$chain[]   = $term->name;

			foreach ( array_reverse( $ancestors ) as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, 'product_cat' );
				if ( $ancestor && ! is_wp_error( $ancestor ) ) {
					$chain[] = $ancestor->name;
				}
			}

			$paths[] = implode( ' > ', array_reverse( $chain ) );
		}

		return implode( ' | ', $paths );
	}
}
