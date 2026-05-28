<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Core\Logger;
use NovaToolsSEO\Traits\Base;

class License {

	use Base;

	public function init() {
		add_action( 'wseo_license_check', array( $this, 'validate_license' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
	}

	public function get_license_key() {
		return get_option( 'wseo_license_key', '' );
	}

	public function save_license_key( $key ) {
		update_option( 'wseo_license_key', sanitize_text_field( $key ) );
	}

	public function validate_license() {
		$key = $this->get_license_key();

		if ( empty( $key ) ) {
			return;
		}

		$response = $this->api_request( 'validate', array( 'license' => $key ) );

		if ( is_wp_error( $response ) ) {
			Logger::log( 'license', 'License validation failed: ' . $response->get_error_message() );
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['valid'] ) ) {
			set_transient( 'wseo_license_invalid', true, DAY_IN_SECONDS );
			Logger::log( 'license', 'License key is invalid or expired' );
		} else {
			delete_transient( 'wseo_license_invalid' );
		}
	}

	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$key = $this->get_license_key();
		if ( empty( $key ) ) {
			return $transient;
		}

		$response = $this->api_request( 'update', array(
			'license' => $key,
			'version' => NOVATOOLS_SEO_VERSION,
		) );

		if ( is_wp_error( $response ) ) {
			return $transient;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['new_version'] ) && version_compare( $body['new_version'], NOVATOOLS_SEO_VERSION, '>' ) ) {
			$plugin_basename = plugin_basename( NOVATOOLS_SEO_PLUGIN_FILE );

			$transient->response[ $plugin_basename ] = (object) array(
				'new_version' => $body['new_version'],
				'package'     => $body['package'] ?? '',
				'url'         => $body['url'] ?? '',
			);
		}

		return $transient;
	}

	private function api_request( $action, $params ) {
		$url = apply_filters( 'wseo_license_api_url', 'https://example.com/api/license' );

		$params['action'] = $action;
		$params['site']   = home_url();

		$response = wp_remote_post( $url, array(
			'timeout' => 15,
			'body'    => $params,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error( 'api_error', 'API returned status ' . $code );
		}

		return $response;
	}
}
