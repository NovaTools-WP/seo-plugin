<?php

namespace NovaToolsSEO\WooCommerce;

use NovaToolsSEO\Core\Logger;
use NovaToolsSEO\Traits\Base;

class PrimaryCategory {

	use Base;

	const REDIRECT_CAP_DEFAULT = 3;

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'save_post_product', array( $this, 'save_primary_category' ), 10, 2 );
		add_action( 'delete_term', array( $this, 'handle_term_deletion' ), 10, 4 );
		add_filter( 'post_type_link', array( $this, 'filter_product_permalink' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'redirect_non_primary_urls' ) );
	}

	public function save_primary_category( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// If primary category was explicitly set via POST
		if ( isset( $_POST['_wseo_primary_category'] ) ) {
			$term_id = absint( $_POST['_wseo_primary_category'] );
			if ( $term_id > 0 ) {
				update_post_meta( $post_id, '_wseo_primary_category', $term_id );
			} else {
				delete_post_meta( $post_id, '_wseo_primary_category' );
			}
			return;
		}

		// Auto-assign if only one category and no primary set
		$existing = get_post_meta( $post_id, '_wseo_primary_category', true );
		if ( empty( $existing ) ) {
			$terms = get_the_terms( $post_id, 'product_cat' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) && count( $terms ) === 1 ) {
				update_post_meta( $post_id, '_wseo_primary_category', $terms[0]->term_id );
			}
		}
	}

	public function handle_term_deletion( $term_id, $tt_id, $taxonomy, $deleted_term ) {
		if ( 'product_cat' !== $taxonomy ) {
			return;
		}

		global $wpdb;
		// Clear primary category for all products that used this term
		$wpdb->delete(
			$wpdb->postmeta,
			array(
				'meta_key'   => '_wseo_primary_category',
				'meta_value' => $term_id,
			),
			array( '%s', '%s' )
		);
	}

	public function filter_product_permalink( $permalink, $post ) {
		if ( 'product' !== $post->post_type || false === strpos( $permalink, '%product_cat%' ) ) {
			return $permalink;
		}

		$primary_term_id = get_post_meta( $post->ID, '_wseo_primary_category', true );
		$term            = null;

		if ( $primary_term_id ) {
			$term = get_term( $primary_term_id, 'product_cat' );
		}

		// Fallback to first category
		if ( ! $term || is_wp_error( $term ) ) {
			$terms = get_the_terms( $post->ID, 'product_cat' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$term = $terms[0];
			}
		}

		if ( $term && ! is_wp_error( $term ) ) {
			return str_replace( '%product_cat%', $term->slug, $permalink );
		}

		return $permalink;
	}

	public function redirect_non_primary_urls() {
		if ( is_admin() || ! is_singular( 'product' ) ) {
			return;
		}

		$post_id   = get_the_ID();
		$permalink = get_permalink( $post_id );
		$requested = $_SERVER['REQUEST_URI'] ?? '';

		// Parse paths for comparison
		$perm_path  = wp_parse_url( $permalink, PHP_URL_PATH );
		$req_path   = wp_parse_url( $requested, PHP_URL_PATH );

		if ( ! $perm_path || ! $req_path ) {
			return;
		}

		// If paths differ (ignoring trailing slash), redirect
		if ( trailingslashit( $req_path ) !== trailingslashit( $perm_path ) ) {
			// Verify this is actually a category-in-URL mismatch, not just query params
			$primary_term_id = get_post_meta( $post_id, '_wseo_primary_category', true );
			if ( ! $primary_term_id ) {
				return;
			}

			$term = get_term( $primary_term_id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				return;
			}

			// Compare path segments to avoid substring false-positives (e.g. "shoes" in "shoes-sale").
			$req_segments  = array_filter( explode( '/', trim( $req_path, '/' ) ) );
			$perm_segments = array_filter( explode( '/', trim( $perm_path, '/' ) ) );

			// Find the segment that differs — that's the category slug
			$uses_primary = false;
			foreach ( $req_segments as $i => $seg ) {
				if ( isset( $perm_segments[ $i ] ) && $perm_segments[ $i ] === $seg ) {
					continue;
				}
				// This differing segment is the category — check if it's the primary
				$uses_primary = ( $seg === $term->slug );
				break;
			}

			if ( ! $uses_primary ) {
				$this->register_redirect( $req_path, $perm_path );

				wp_redirect( esc_url_raw( $permalink ), 301 );
				exit;
			}
		}
	}

	/**
	 * Maximum redirect entries kept per destination URL.
	 * Filterable via `wseo_redirect_cap_per_destination`.
	 *
	 * @return int
	 */
	private function get_redirect_cap() {
		return apply_filters( 'wseo_redirect_cap_per_destination', self::REDIRECT_CAP_DEFAULT );
	}

	private function register_redirect( $source, $destination ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wseo_redirects';

		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			return;
		}

		// Check for existing redirect to avoid duplicates
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE source_url = %s LIMIT 1",
			$source
		) );

		if ( $existing ) {
			// Update destination if changed
			$wpdb->update( $table, array( 'destination_url' => $destination ), array( 'id' => $existing ) );
			return;
		}

		// Cap redirect entries per destination to prevent table bloat
		$cap   = $this->get_redirect_cap();
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE destination_url = %s",
			$destination
		) );

		if ( $count >= $cap ) {
			$deleted = $count - ( $cap - 1 );
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$table} WHERE destination_url = %s ORDER BY id ASC LIMIT %d",
				$destination,
				$deleted
			) );

			if ( class_exists( Logger::class ) ) {
				Logger::get_instance()->log( 'info', sprintf(
					'Primary category redirect cleanup: removed %d old redirect(s) for destination %s',
					$deleted,
					$destination
				) );
			}
		}

		$wpdb->insert( $table, array(
			'source_url'      => $source,
			'destination_url' => $destination,
			'status_code'     => 301,
			'is_regex'        => 0,
		) );
	}
}
