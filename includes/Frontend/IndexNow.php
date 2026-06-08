<?php

namespace NovaToolsSEO\Frontend;

use NovaToolsSEO\Admin\Settings;
use NovaToolsSEO\Traits\Base;

class IndexNow {

	use Base;

	const TRANSIENT_KEY = 'wseo_indexnow_queue';
	const ACTION_GROUP  = 'wseo_indexnow';
	const ACTION_HOOK   = 'wseo_indexnow_ping';
	const API_ENDPOINT  = 'https://api.indexnow.org/IndexNow';

	public function init() {
		add_action( 'publish_post', array( $this, 'queue_url' ) );
		add_action( 'publish_page', array( $this, 'queue_url' ) );
		add_action( 'woocommerce_product_update', array( $this, 'queue_url' ) );
		add_action( 'created_term', array( $this, 'queue_term_url' ), 10, 3 );
		add_action( 'edited_term', array( $this, 'queue_term_url' ), 10, 3 );

		add_action( self::ACTION_HOOK, array( $this, 'send_ping' ) );
	}

	public function queue_url( $post_id ) {
		if ( ! $this->is_enabled() || ! $this->action_scheduler_available() ) {
			return;
		}

		$url = get_permalink( $post_id );
		if ( $url ) {
			$this->add_to_queue( $url );
			$this->schedule_ping();
		}
	}

	public function queue_term_url( $term_id, $tt_id, $taxonomy ) {
		if ( ! $this->is_enabled() || ! $this->action_scheduler_available() ) {
			return;
		}

		$url = get_term_link( $term_id, $taxonomy );
		if ( $url && ! is_wp_error( $url ) ) {
			$this->add_to_queue( $url );
			$this->schedule_ping();
		}
	}

	public function send_ping() {
		$urls = get_transient( self::TRANSIENT_KEY );

		if ( empty( $urls ) || ! is_array( $urls ) ) {
			return;
		}

		delete_transient( self::TRANSIENT_KEY );

		$settings = Settings::get_instance();
		$key      = $settings->get( 'indexnow_api_key', '' );

		if ( empty( $key ) ) {
			return;
		}

		$body = array(
			'host'        => wp_parse_url( home_url(), PHP_URL_HOST ),
			'key'         => $key,
			'urlList'     => array_values( array_unique( $urls ) ),
			'keyLocation' => home_url( '/' . $key . '.txt' ),
		);

		$response = wp_remote_post( self::API_ENDPOINT, array(
			'timeout' => 10,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			$this->log( 'IndexNow ping failed', array(
				'error' => $response->get_error_message(),
				'urls'  => $body['urlList'],
			) );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			$this->log( 'IndexNow ping successful', array(
				'code' => $code,
				'urls' => $body['urlList'],
			) );
		} else {
			$this->log( 'IndexNow ping failed', array(
				'code' => $code,
				'urls' => $body['urlList'],
			) );
		}
	}

	public function is_action_scheduler_available() {
		return class_exists( 'ActionScheduler' );
	}

	private function is_enabled() {
		$settings = Settings::get_instance();
		return $settings->get( 'indexnow_enabled', '' ) === '1';
	}

	private function action_scheduler_available() {
		if ( ! $this->is_action_scheduler_available() ) {
			$this->log( 'Action Scheduler not available — skipping IndexNow scheduling' );
			return false;
		}
		return true;
	}

	private function add_to_queue( $url ) {
		$urls   = get_transient( self::TRANSIENT_KEY );
		$urls   = is_array( $urls ) ? $urls : array();
		$urls[] = $url;
		set_transient( self::TRANSIENT_KEY, $urls, 60 );
	}

	private function schedule_ping() {
		if ( ! as_has_scheduled_action( self::ACTION_HOOK ) ) {
			as_schedule_single_action( time() + 60, self::ACTION_HOOK, array(), self::ACTION_GROUP );
		}
	}

	private function log( $message, $context = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'wseo_logs';

		$wpdb->insert( $table, array(
			'type'    => 'indexnow',
			'message' => $message,
			'context' => wp_json_encode( $context ),
		) );
	}
}
