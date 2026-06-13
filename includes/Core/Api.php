<?php

namespace NovaToolsSEO\Core;

use NovaToolsSEO\Admin\License;
use NovaToolsSEO\Admin\Settings;
use NovaToolsSEO\Admin\YoastImport;
use NovaToolsSEO\Sitemaps\Generator;
use NovaToolsSEO\Traits\Base;
use NovaToolsSEO\WooCommerce\Filters\FilterParamsRepository;
use NovaToolsSEO\WooCommerce\Taxonomy\TaxonomyNoindexRepository;

defined( 'ABSPATH' ) || exit;

class Api {

	use Base;

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		$namespace = 'novatools-seo/v1';

		register_rest_route( $namespace, '/local-business-types', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_local_business_types' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( $namespace, '/pages', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_pages' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( $namespace, '/settings', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_settings' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
		) );

		register_rest_route( $namespace, '/post-meta/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_post_meta' ),
				'permission_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_post_meta' ),
				'permission_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			),
		) );

		register_rest_route( $namespace, '/product-meta/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_product_meta' ),
				'permission_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_product_meta' ),
				'permission_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			),
		) );

		register_rest_route( $namespace, '/woo-attributes', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_woo_attributes' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( $namespace, '/attribute-mappings', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_attribute_mappings' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_attribute_mappings' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
		) );

		register_rest_route( $namespace, '/redirects', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_redirects' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_redirect' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
		) );

		register_rest_route( $namespace, '/redirects/(?P<id>\d+)', array(
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_redirect' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
		) );

		register_rest_route( $namespace, '/export', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'export_settings' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( $namespace, '/import', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'import_settings' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( $namespace, '/logs', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_logs' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( $namespace, '/logs/export', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'export_logs' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( $namespace, '/yoast-import/start', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'start_yoast_import' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( $namespace, '/yoast-import/progress', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_yoast_import_progress' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( $namespace, '/yoast-import/migrate-settings', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'migrate_yoast_settings' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( $namespace, '/yoast-import/process-batch', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'process_yoast_import_batch' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( $namespace, '/sitemap/rebuild', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rebuild_sitemap' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( $namespace, '/license', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_license_status' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_and_validate_license' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
		) );

		register_rest_route( $namespace, '/dashboard', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_dashboard' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		) );

		register_rest_route( $namespace, '/woo/filter-params', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_woo_filter_params' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_woo_filter_params' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
		) );

		register_rest_route( $namespace, '/woo/taxonomy-noindex', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_woo_taxonomy_noindex' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_woo_taxonomy_noindex' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
		) );
	}

	public function get_settings() {
		$settings = Settings::get_instance();
		return rest_ensure_response( $settings->export_all() );
	}

	public function save_settings( $request ) {
		$params = $request->get_json_params();
		$settings = Settings::get_instance();
		$allowed_keys = $settings->get_all_setting_keys();

		foreach ( $params as $key => $value ) {
			if ( array_key_exists( $key, $allowed_keys ) ) {
				$sanitized_value = $settings->sanitize_setting( $key, $value );
				if ( null !== $sanitized_value ) {
					update_option( $key, $sanitized_value );
				}
			}
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_post_meta( $request ) {
		$post_id = (int) $request['id'];
		$meta_keys = array( '_wseo_title', '_wseo_description', '_wseo_canonical', '_wseo_robots', '_wseo_og_title', '_wseo_og_description', '_wseo_og_image', '_wseo_twitter_card', '_wseo_twitter_title', '_wseo_twitter_description', '_wseo_twitter_image', '_wseo_local_business' );

		$data = array();
		foreach ( $meta_keys as $key ) {
			$data[ $key ] = get_post_meta( $post_id, $key, true );
		}

		return rest_ensure_response( $data );
	}

	public function save_post_meta( $request ) {
		$post_id = (int) $request['id'];
		$params = $request->get_json_params();

		$allowed_keys = array( '_wseo_title', '_wseo_description', '_wseo_canonical', '_wseo_robots', '_wseo_og_title', '_wseo_og_description', '_wseo_og_image', '_wseo_twitter_card', '_wseo_twitter_title', '_wseo_twitter_description', '_wseo_twitter_image', '_wseo_local_business' );

		foreach ( $allowed_keys as $key ) {
			if ( isset( $params[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( $params[ $key ] ) );
			}
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_product_meta( $request ) {
		$post_id = (int) $request['id'];
		$meta_keys = array( '_wseo_gtin', '_wseo_mpn', '_wseo_isbn', '_wseo_brand', '_wseo_item_condition', '_wseo_faq', '_wseo_local_inventory' );

		$data = array();
		foreach ( $meta_keys as $key ) {
			$data[ $key ] = get_post_meta( $post_id, $key, true );
		}

		return rest_ensure_response( $data );
	}

	public function save_product_meta( $request ) {
		$post_id = (int) $request['id'];
		$params = $request->get_json_params();

		$text_keys = array( '_wseo_gtin', '_wseo_mpn', '_wseo_isbn', '_wseo_brand', '_wseo_item_condition' );

		foreach ( $text_keys as $key ) {
			if ( isset( $params[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( $params[ $key ] ) );
			}
		}

		if ( isset( $params['_wseo_faq'] ) ) {
			$faq = $params['_wseo_faq'];
			if ( is_array( $faq ) ) {
				$sanitized = array();
				foreach ( $faq as $item ) {
					$sanitized[] = array(
						'question' => sanitize_text_field( $item['question'] ?? '' ),
						'answer'   => sanitize_textarea_field( $item['answer'] ?? '' ),
					);
				}
				update_post_meta( $post_id, '_wseo_faq', $sanitized );
			} else {
				delete_post_meta( $post_id, '_wseo_faq' );
			}
		}

		if ( isset( $params['_wseo_local_inventory'] ) ) {
			update_post_meta( $post_id, '_wseo_local_inventory', $params['_wseo_local_inventory'] ? '1' : '' );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_woo_attributes() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return rest_ensure_response( array() );
		}

		$attributes = wc_get_attribute_taxonomy_names();
		$result = array();

		foreach ( $attributes as $slug ) {
			$taxonomy = get_taxonomy( $slug );
			if ( $taxonomy ) {
				$result[] = array(
					'slug'  => $slug,
					'label' => $taxonomy->labels->singular_name,
				);
			}
		}

		return rest_ensure_response( $result );
	}

	public function get_attribute_mappings() {
		$mappings = get_option( 'wseo_attribute_mappings', array() );
		return rest_ensure_response( $mappings );
	}

	public function save_attribute_mappings( $request ) {
		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			return new \WP_Error( 'invalid_data', 'Expected an array of mappings.' );
		}

		$sanitized = array();
		foreach ( $params as $mapping ) {
			if ( isset( $mapping['attribute_slug'] ) && isset( $mapping['schema_property'] ) ) {
				$sanitized[] = array(
					'attribute_slug'  => sanitize_text_field( $mapping['attribute_slug'] ),
					'schema_property' => sanitize_text_field( $mapping['schema_property'] ),
				);
			}
		}

		update_option( 'wseo_attribute_mappings', $sanitized );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_redirects( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wseo_redirects';

		$redirects = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY id DESC" ),
			ARRAY_A
		);
		return rest_ensure_response( $redirects ?: array() );
	}

	public function save_redirect( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wseo_redirects';
		$params = $request->get_json_params();

		$data = array(
			'source_url'      => sanitize_text_field( $params['source_url'] ?? '' ),
			'destination_url' => sanitize_text_field( $params['destination_url'] ?? '' ),
			'status_code'     => absint( $params['status_code'] ?? 301 ),
			'is_regex'        => absint( $params['is_regex'] ?? 0 ),
		);

		if ( ! empty( $params['id'] ) ) {
			$wpdb->update( $table, $data, array( 'id' => absint( $params['id'] ) ) );
		} else {
			$wpdb->insert( $table, $data );
		}

		wp_cache_delete( 'wseo_redirects', 'novatools-seo' );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function delete_redirect( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wseo_redirects';
		$wpdb->delete( $table, array( 'id' => absint( $request['id'] ) ) );
		wp_cache_delete( 'wseo_redirects', 'novatools-seo' );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function export_settings() {
		$settings = Settings::get_instance();
		return rest_ensure_response( $settings->export_all() );
	}

	public function import_settings( $request ) {
		$params = $request->get_json_params();
		$settings = Settings::get_instance();
		$settings->import_all( $params );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_logs( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wseo_logs';

		if ( ! empty( $request['type'] ) ) {
			$logs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE type = %s ORDER BY created_at DESC LIMIT 200",
					sanitize_text_field( $request['type'] )
				),
				ARRAY_A
			);
		} else {
			$logs = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT 200" ),
				ARRAY_A
			);
		}

		return rest_ensure_response( $logs ?: array() );
	}

	public function export_logs( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wseo_logs';
		$logs = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY created_at DESC" ),
			ARRAY_A
		);
		return rest_ensure_response( $logs ?: array() );
	}

	public function start_yoast_import() {
		$importer = YoastImport::get_instance();
		$result = $importer->start_import();
		return rest_ensure_response( $result );
	}

	public function get_yoast_import_progress() {
		$importer = YoastImport::get_instance();
		return rest_ensure_response( $importer->get_progress() );
	}

	public function process_yoast_import_batch( $request ) {
		$params = $request->get_json_params();
		$offset = absint( $params['offset'] ?? 0 );
		$limit  = absint( $params['limit'] ?? 50 );

		$importer = YoastImport::get_instance();
		$result = $importer->process_batch( $offset, $limit );

		return rest_ensure_response( array(
			'success'   => true,
			'processed' => $result['processed'],
			'progress'  => $result['progress'],
		) );
	}

	public function migrate_yoast_settings() {
		$importer = YoastImport::get_instance();
		$importer->migrate_global_settings();
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function rebuild_sitemap() {
		$generator = Generator::get_instance();
		$generator->rebuild_manually();
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_license_status() {
		$key = get_option( 'wseo_license_key', '' );
		$invalid = get_transient( 'wseo_license_invalid' );

		$status = 'empty';
		if ( ! empty( $key ) ) {
			$status = $invalid ? 'invalid' : 'valid';
		}

		$masked = '';
		if ( ! empty( $key ) ) {
			$masked = 'XXXX-XXXX-' . substr( $key, -4 );
		}

		return rest_ensure_response( array(
			'key'    => $masked,
			'status' => $status,
		) );
	}

	public function save_and_validate_license( $request ) {
		$params = $request->get_json_params();
		$key = sanitize_text_field( $params['license_key'] ?? '' );

		$license = License::get_instance();
		$license->save_license_key( $key );
		$license->validate_license();

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_dashboard() {
		global $wpdb;

		$sitemap_enabled = get_option( 'wseo_sitemap_enabled', '1' ) === '1';

		$redirect_count = 0;
		$table = $wpdb->prefix . 'wseo_redirects';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
			$redirect_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		$license_key = get_option( 'wseo_license_key', '' );
		$license_invalid = get_transient( 'wseo_license_invalid' );
		$license_status = 'empty';
		if ( ! empty( $license_key ) ) {
			$license_status = $license_invalid ? 'invalid' : 'valid';
		}

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$types_list = array();
		foreach ( $post_types as $type ) {
			$types_list[] = $type->labels->singular_name;
		}

		return rest_ensure_response( array(
			'sitemap_enabled' => $sitemap_enabled,
			'redirect_count'  => $redirect_count,
			'license_status'  => $license_status,
			'post_types'      => $types_list,
		) );
	}

	public function get_local_business_types() {
		$types = array(
			'Store'            => 'Store',
			'Restaurant'       => 'Restaurant',
			'Dentist'          => 'Dentist',
			'AutoRepair'       => 'Auto Repair',
			'MedicalClinic'    => 'Medical Clinic',
			'Hotel'            => 'Hotel',
			'Bakery'           => 'Bakery',
			'Cafe'             => 'Cafe',
			'Gym'              => 'Gym',
			'Salon'            => 'Salon / Hair Salon',
			'VeterinaryCare'   => 'Veterinary Care',
			'Physician'        => 'Physician',
			'Pharmacy'         => 'Pharmacy',
			'GroceryStore'     => 'Grocery Store',
			'InsuranceAgency'  => 'Insurance Agency',
			'RealEstateAgent'  => 'Real Estate Agent',
			'Attorney'         => 'Attorney',
			'AccountingFirm'   => 'Accounting Firm',
			'Plumber'          => 'Plumber',
			'Electrician'      => 'Electrician',
			'LocalBusiness'    => 'Local Business (Generic)',
		);

		$types = apply_filters( 'wseo_local_business_types', $types );
		return rest_ensure_response( $types );
	}

	public function get_pages() {
		$pages = get_pages( array(
			'sort_order'   => 'ASC',
			'sort_column'  => 'post_title',
			'hierarchical' => 0,
			'post_status'  => 'publish',
		) );

		$result = array();
		foreach ( $pages as $page ) {
			$result[] = array(
				'id'    => $page->ID,
				'title' => $page->post_title,
			);
		}

		return rest_ensure_response( $result );
	}

	public function get_woo_filter_params() {
		$repo = FilterParamsRepository::get_instance();
		return rest_ensure_response( array(
			'params' => $repo->get_params(),
		) );
	}

	public function save_woo_filter_params( $request ) {
		$params = $request->get_json_params();
		$repo = FilterParamsRepository::get_instance();
		$repo->save_params( $params['params'] ?? array() );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_woo_taxonomy_noindex() {
		$repo = TaxonomyNoindexRepository::get_instance();
		return rest_ensure_response( array(
			'taxonomies' => $repo->get_all(),
		) );
	}

	public function save_woo_taxonomy_noindex( $request ) {
		$params = $request->get_json_params();
		$repo = TaxonomyNoindexRepository::get_instance();
		$repo->save_all( $params['taxonomies'] ?? array() );
		return rest_ensure_response( array( 'success' => true ) );
	}
}
