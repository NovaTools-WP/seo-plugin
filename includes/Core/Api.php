<?php

namespace NovaToolsSEO\Core;

use NovaToolsSEO\Admin\License;
use NovaToolsSEO\Admin\Settings;
use NovaToolsSEO\Admin\YoastImport;
use NovaToolsSEO\Analysis\ContentAnalyzer;
use NovaToolsSEO\Analysis\LinkAnalyzer;
use NovaToolsSEO\Core\MetaKeys;
use NovaToolsSEO\Frontend\Schema\SchemaRegistry;
use NovaToolsSEO\Sitemaps\Generator;
use NovaToolsSEO\Redirects\FourOhFourLogger;
use NovaToolsSEO\Traits\Base;
use NovaToolsSEO\WooCommerce\Filters\FilterParamsRepository;
use NovaToolsSEO\WooCommerce\Taxonomy\TaxonomyNoindexRepository;

defined( 'ABSPATH' ) || exit;

class Api {

	use Base;

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_pre_dispatch', array( $this, 'verify_nonce_for_state_changes' ), 10, 3 );
	}

	public function verify_nonce_for_state_changes( $result, $server, $request ) {
		if ( ! in_array( $request->get_method(), array( 'POST', 'PUT', 'DELETE', 'PATCH' ), true ) ) {
			return $result;
		}

		if ( strpos( $request->get_route(), '/novatools-seo/' ) !== 0 ) {
			return $result;
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Invalid or missing nonce.', 'novatools-seo' ),
				array( 'status' => 403 )
			);
		}

		return $result;
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

		register_rest_route( $namespace, '/analyze', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'analyze_content' ),
			'permission_callback' => function( $request ) {
				$params = $request->get_json_params();
				$post_id = isset( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;
				return $post_id > 0 && current_user_can( 'edit_post', $post_id );
			},
			'args'                => array(
				'post_id'     => array( 'required' => true, 'type' => 'integer' ),
				'keyphrase'   => array( 'type' => 'string' ),
				'content'     => array( 'type' => 'string' ),
				'title'       => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
			),
		) );

		register_rest_route( $namespace, '/schema-types', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_schema_types' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			},
			'args'                => array(
				'post_type' => array( 'type' => 'string' ),
			),
		) );

		register_rest_route( $namespace, '/link-suggestions/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_link_suggestions' ),
			'permission_callback' => function( $request ) {
				return current_user_can( 'edit_post', (int) $request['id'] );
			},
			'args'                => array(
				'id'        => array( 'required' => true, 'type' => 'integer' ),
				'limit'     => array( 'type' => 'integer' ),
				'keyphrase' => array( 'type' => 'string' ),
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

		register_rest_route( $namespace, '/404-logs', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_404_logs' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'clear_404_logs' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
		) );

		register_rest_route( $namespace, '/404-logs/(?P<id>\d+)', array(
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_404_log' ),
				'permission_callback' => function() {
					return current_user_can( 'manage_options' );
				},
			),
		) );

		register_rest_route( $namespace, '/404-suggestions', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_404_suggestions' ),
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
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

		// Core site identity (blogname / blogdescription) feeds the
		// %%sitename%% / %%sitedesc%% title tokens; handled explicitly since
		// they are not wseo_-prefixed options.
		foreach ( array( 'blogname', 'blogdescription' ) as $identity_key ) {
			if ( array_key_exists( $identity_key, $params ) ) {
				update_option( $identity_key, sanitize_text_field( $params[ $identity_key ] ) );
			}
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_post_meta( $request ) {
		$post_id = (int) $request['id'];

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', 'You cannot edit this post.', array( 'status' => 403 ) );
		}

		$data = array();
		foreach ( MetaKeys::POST_ALL as $key ) {
			$data[ $key ] = get_post_meta( $post_id, $key, true );
		}

		// Structured schema data (array — handled separately from POST_ALL).
		$schema = get_post_meta( $post_id, '_wseo_schema', true );
		$data['_wseo_schema'] = is_array( $schema ) ? $schema : array();

		return rest_ensure_response( $data );
	}

	public function save_post_meta( $request ) {
		$post_id = (int) $request['id'];

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', 'You cannot edit this post.', array( 'status' => 403 ) );
		}

		$params = $request->get_json_params();

		foreach ( MetaKeys::POST_ALL as $key ) {
			if ( isset( $params[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( $params[ $key ] ) );
			}
		}

		// Structured schema data: array, sanitized per-type. array_key_exists
		// lets sending an empty array/null clear it. Do NOT declare this in the
		// route args — REST object/array typing rejects nested arrays.
		if ( array_key_exists( '_wseo_schema', $params ) ) {
			$clean = SchemaRegistry::sanitize_for_storage( $params['_wseo_schema'] );
			if ( empty( $clean ) ) {
				delete_post_meta( $post_id, '_wseo_schema' );
			} else {
				update_post_meta( $post_id, '_wseo_schema', $clean );
			}
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Return the schema-type registry configuration for the admin builder.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_schema_types( $request ) {
		$post_type = sanitize_text_field( $request['post_type'] ?? '' );
		return rest_ensure_response(
			SchemaRegistry::get_instance()->get_config( $post_type )
		);
	}

	/**
	 * Return ranked internal-link suggestions for a post.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_link_suggestions( $request ) {
		$post_id   = (int) $request['id'];
		$limit     = isset( $request['limit'] ) ? absint( $request['limit'] ) : 5;
		$keyphrase = isset( $request['keyphrase'] ) ? sanitize_text_field( $request['keyphrase'] ) : '';

		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return new \WP_Error( 'not_found', __( 'Post not found.', 'novatools-seo' ), array( 'status' => 404 ) );
		}

		$suggestions = LinkAnalyzer::get_instance()->get_suggestions( $post_id, $limit, $keyphrase );
		return rest_ensure_response( array( 'suggestions' => $suggestions ) );
	}

	/**
	 * Run content/readability/keyphrase analysis for a post.
	 *
	 * Accepts live overrides (content/title/description) from the editor so
	 * results update as the user types; falls back to the saved post. Results
	 * are transient-cached (keyed on the full param set) to avoid recomputing
	 * on repeated identical requests. Aggregate scores are persisted on
	 * save_post, not here, since this endpoint fires per keystroke.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function analyze_content( $request ) {
		$params  = $request->get_json_params();
		$post_id = absint( $params['post_id'] ?? 0 );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', 'You cannot edit this post.', array( 'status' => 403 ) );
		}

		$args = array( 'post_id' => $post_id );

		if ( isset( $params['keyphrase'] ) ) {
			$args['keyphrase'] = sanitize_text_field( $params['keyphrase'] );
		}
		if ( isset( $params['title'] ) ) {
			$args['title'] = sanitize_text_field( $params['title'] );
		}
		if ( isset( $params['description'] ) ) {
			$args['description'] = sanitize_text_field( $params['description'] );
		}
		if ( isset( $params['content'] ) && '' !== $params['content'] ) {
			// Neutralize anything nasty before the analyzer strips tags.
			$args['content'] = wp_kses_post( wp_unslash( $params['content'] ) );
		}

		// Short-lived cache keyed on the full param set.
		$cache_key = 'wseo_analysis_' . $post_id . '_' . md5( (string) wp_json_encode( $args ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return rest_ensure_response( $cached );
		}

		$result = ContentAnalyzer::get_instance()->analyze( $args );
		set_transient( $cache_key, $result, HOUR_IN_SECONDS );

		return rest_ensure_response( $result );
	}

	public function get_product_meta( $request ) {
		$post_id = (int) $request['id'];

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', 'You cannot edit this post.', array( 'status' => 403 ) );
		}

		$data = array();
		foreach ( MetaKeys::PRODUCT as $key ) {
			$data[ $key ] = get_post_meta( $post_id, $key, true );
		}

		return rest_ensure_response( $data );
	}

	public function save_product_meta( $request ) {
		$post_id = (int) $request['id'];

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', 'You cannot edit this post.', array( 'status' => 403 ) );
		}

		$params = $request->get_json_params();

		foreach ( MetaKeys::PRODUCT_TEXT as $key ) {
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

		if ( $data['is_regex'] && ! empty( $data['source_url'] ) ) {
			$valid = $this->validate_redirect_regex( $data['source_url'] );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}

		$warning = $this->check_redirect_domain( $data['destination_url'] );

		if ( ! empty( $params['id'] ) ) {
			$wpdb->update( $table, $data, array( 'id' => absint( $params['id'] ) ) );
		} else {
			$wpdb->insert( $table, $data );
		}

		wp_cache_delete( 'wseo_redirects', 'novatools-seo' );

		$response = array( 'success' => true );
		if ( $warning ) {
			$response['warning'] = $warning;
		}

		return rest_ensure_response( $response );
	}

	public function delete_redirect( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wseo_redirects';
		$wpdb->delete( $table, array( 'id' => absint( $request['id'] ) ) );
		wp_cache_delete( 'wseo_redirects', 'novatools-seo' );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_404_logs( $request ) {
		$limit  = absint( $request['limit'] ?? 100 );
		$offset = absint( $request['offset'] ?? 0 );
		$logs   = FourOhFourLogger::get_aggregated_logs( $limit, $offset );

		$processed = array();
		foreach ( $logs as $log ) {
			$context = json_decode( $log['context'] ?? '{}', true );
			$processed[] = array(
				'id'        => (int) $log['id'],
				'url'       => $log['url'],
				'hit_count' => isset( $context['hit_count'] ) ? (int) $context['hit_count'] : 1,
				'referer'   => $context['referer'] ?? '',
				'user_ip'   => $context['user_ip'] ?? '',
				'last_hit'  => $log['created_at'],
			);
		}

		usort( $processed, function ( $a, $b ) {
			return $b['hit_count'] - $a['hit_count'];
		} );

		return rest_ensure_response( $processed );
	}

	public function delete_404_log( $request ) {
		FourOhFourLogger::delete_log( $request['id'] );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function clear_404_logs() {
		FourOhFourLogger::clear_all_logs();
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_404_suggestions( $request ) {
		$url = sanitize_text_field( $request['url'] ?? '' );
		if ( empty( $url ) ) {
			return new \WP_Error( 'missing_url', 'URL parameter is required.', array( 'status' => 400 ) );
		}

		$suggestions = FourOhFourLogger::get_suggestions( $url );
		return rest_ensure_response( $suggestions );
	}

	private function validate_redirect_regex( $pattern ) {
		$result = @preg_match( $pattern, '' );
		if ( false === $result ) {
			return new \WP_Error(
				'invalid_regex',
				__( 'Invalid regex pattern.', 'novatools-seo' ),
				array( 'status' => 400 )
			);
		}

		$sample = '/sample/path/to/test?query=value&foo=bar';
		$start  = microtime( true );
		@preg_match( $pattern, $sample );
		$elapsed = microtime( true ) - $start;

		if ( $elapsed > 0.1 ) {
			return new \WP_Error(
				'regex_too_complex',
				__( 'Regex pattern is too complex and may cause performance issues.', 'novatools-seo' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	private function check_redirect_domain( $destination ) {
		if ( empty( $destination ) ) {
			return null;
		}

		$host = wp_parse_url( $destination, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return null;
		}

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( strtolower( $host ) === strtolower( $site_host ) ) {
			return null;
		}

		$allowed_domains = get_option( 'wseo_redirect_allowed_domains', array() );

		if ( ! empty( $allowed_domains ) && ! in_array( strtolower( $host ), array_map( 'strtolower', $allowed_domains ), true ) ) {
			return sprintf(
				/* translators: %s: domain name */
				__( 'Domain "%s" is not in the allowed redirect domains list. This redirect will be blocked at runtime.', 'novatools-seo' ),
				$host
			);
		}

		if ( empty( $allowed_domains ) ) {
			return sprintf(
				/* translators: %s: domain name */
				__( 'Redirecting to external domain "%s". Consider configuring an allowed domains list in settings.', 'novatools-seo' ),
				$host
			);
		}

		return null;
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
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
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
			'setup_completed' => get_option( 'wseo_setup_completed', '' ) === '1',
			'setup_skipped'   => get_option( 'wseo_setup_skipped', '' ) === '1',
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
