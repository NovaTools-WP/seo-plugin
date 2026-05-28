<?php

namespace NovaToolsSEO\Core;

use NovaToolsSEO\Admin\License;
use NovaToolsSEO\Admin\Settings;
use NovaToolsSEO\Admin\YoastImport;
use NovaToolsSEO\Sitemaps\Generator;
use NovaToolsSEO\Traits\Base;

class Api {

	use Base;

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		$namespace = 'novatools-seo/v1';

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
	}

	public function get_settings() {
		$settings = Settings::get_instance();
		return rest_ensure_response( $settings->export_all() );
	}

	public function save_settings( $request ) {
		$params = $request->get_json_params();
		$settings = Settings::get_instance();

		foreach ( $params as $key => $value ) {
			if ( 0 === strpos( $key, 'wseo_' ) ) {
				update_option( $key, $value );
			}
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_post_meta( $request ) {
		$post_id = (int) $request['id'];
		$meta_keys = array( '_wseo_title', '_wseo_description', '_wseo_canonical', '_wseo_robots', '_wseo_og_title', '_wseo_og_description', '_wseo_og_image', '_wseo_twitter_card', '_wseo_twitter_title', '_wseo_twitter_description', '_wseo_twitter_image' );

		$data = array();
		foreach ( $meta_keys as $key ) {
			$data[ $key ] = get_post_meta( $post_id, $key, true );
		}

		return rest_ensure_response( $data );
	}

	public function save_post_meta( $request ) {
		$post_id = (int) $request['id'];
		$params = $request->get_json_params();

		$allowed_keys = array( '_wseo_title', '_wseo_description', '_wseo_canonical', '_wseo_robots', '_wseo_og_title', '_wseo_og_description', '_wseo_og_image', '_wseo_twitter_card', '_wseo_twitter_title', '_wseo_twitter_description', '_wseo_twitter_image' );

		foreach ( $allowed_keys as $key ) {
			if ( isset( $params[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( $params[ $key ] ) );
			}
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_redirects( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wseo_redirects';

		$redirects = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );
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

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function delete_redirect( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wseo_redirects';
		$wpdb->delete( $table, array( 'id' => absint( $request['id'] ) ) );
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

		$where = '';
		if ( ! empty( $request['type'] ) ) {
			$where = $wpdb->prepare( ' WHERE type = %s', sanitize_text_field( $request['type'] ) );
		}

		$logs = $wpdb->get_results( "SELECT * FROM {$table}{$where} ORDER BY created_at DESC LIMIT 200", ARRAY_A );
		return rest_ensure_response( $logs ?: array() );
	}

	public function export_logs( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wseo_logs';
		$logs = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
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

		return rest_ensure_response( array(
			'key'    => $key,
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
}
