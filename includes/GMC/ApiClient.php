<?php

namespace NovaToolsSEO\GMC;

use NovaToolsSEO\Core\Logger;

defined( 'ABSPATH' ) || exit;

class ApiClient {

	const MAX_RETRIES    = 3;
	const BASE_DELAYS    = array( 30, 60, 120 );

	private $oauth;

	public function __construct( OAuth $oauth ) {
		$this->oauth = $oauth;
	}

	public function insert_product( $merchant_id, array $product ) {
		return $this->call_api( 'POST', $merchant_id, 'products', $product );
	}

	public function update_product( $merchant_id, array $product ) {
		$offer_id = $product['offerId'] ?? '';
		return $this->call_api( 'PUT', $merchant_id, 'products/' . rawurlencode( $offer_id ), $product );
	}

	public function get_product( $merchant_id, $offer_id ) {
		return $this->call_api( 'GET', $merchant_id, 'products/' . rawurlencode( $offer_id ) );
	}

	public function delete_product( $merchant_id, $offer_id ) {
		return $this->call_api( 'DELETE', $merchant_id, 'products/' . rawurlencode( $offer_id ) );
	}

	public function upsert_product( $merchant_id, array $product ) {
		$existing = $this->get_product( $merchant_id, $product['offerId'] ?? '' );

		if ( is_array( $existing ) && ! empty( $existing['id'] ) ) {
			return $this->update_product( $merchant_id, $product );
		}

		return $this->insert_product( $merchant_id, $product );
	}

	private function call_api( $method, $merchant_id, $path, $body = null ) {
		$access_token = $this->oauth->get_access_token();

		if ( false === $access_token ) {
			return new \WP_Error( 'gmc_not_connected', 'Google account not connected or token refresh failed.' );
		}

		$url = sprintf(
			'https://shopping-content.googleapis.com/content/v2.1/%s/%s',
			rawurlencode( $merchant_id ),
			$path
		);

		for ( $attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++ ) {
			if ( $attempt > 0 ) {
				$delay = self::BASE_DELAYS[ $attempt - 1 ] ?? 120;
				Logger::log( 'gmc_sync', "Retry attempt {$attempt}, waiting {$delay}s" );
				sleep( $delay );
			}

			$args = array(
				'method'  => $method,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			);

			if ( null !== $body ) {
				$args['body'] = wp_json_encode( $body );
			}

			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				if ( $attempt < self::MAX_RETRIES ) {
					continue;
				}
				return $response;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$resp_body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 429 === $code ) {
				if ( $attempt >= self::MAX_RETRIES ) {
					return new \WP_Error( 'gmc_rate_limit', 'Google API rate limit exceeded after retries.' );
				}
				$retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
				if ( $retry_after > 0 ) {
					Logger::log( 'gmc_sync', "Rate limited, Retry-After: {$retry_after}s" );
					sleep( $retry_after );
				}
				continue;
			}

			if ( $code >= 500 && $attempt < self::MAX_RETRIES ) {
				continue;
			}

			if ( $code >= 400 ) {
				$error_msg = $resp_body['error']['message'] ?? "HTTP {$code}";
				return new \WP_Error( 'gmc_api_error', $error_msg, array( 'status' => $code ) );
			}

			return $resp_body;
		}

		return new \WP_Error( 'gmc_max_retries', 'Max retries exceeded.' );
	}
}
