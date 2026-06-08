<?php

namespace NovaToolsSEO\GMC;

use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

class RestController {

	use Base;

	private $oauth;
	private $client;
	private $sync_engine;

	public function init() {
		$this->oauth       = new OAuth();
		$this->client      = new ApiClient( $this->oauth );
		$this->sync_engine = new SyncEngine( $this->client );

		$this->oauth->init();
		$this->sync_engine->init();
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		$ns = 'novatools-seo/v1/gmc';

		register_rest_route( $ns, '/auth', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_auth_status' ),
				'permission_callback' => array( $this, 'check_admin' ),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'disconnect' ),
				'permission_callback' => array( $this, 'check_admin' ),
			),
		) );

		register_rest_route( $ns, '/auth/url', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_auth_url' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );

		register_rest_route( $ns, '/settings', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'check_admin' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_settings' ),
				'permission_callback' => array( $this, 'check_admin' ),
			),
		) );

		register_rest_route( $ns, '/sync', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'start_sync' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );

		register_rest_route( $ns, '/sync-status', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_sync_status' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );

		register_rest_route( $ns, '/sync/cancel', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'cancel_sync' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );

		register_rest_route( $ns, '/sync/pause', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'pause_sync' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );

		register_rest_route( $ns, '/sync/resume', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'resume_sync' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );

		register_rest_route( $ns, '/logs', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_gmc_logs' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
	}

	public function check_admin() {
		return current_user_can( 'manage_options' );
	}

	public function get_auth_status() {
		$connected   = $this->oauth->is_connected();
		$email       = $connected ? $this->oauth->get_account_email() : null;
		$reconnect   = $connected && $this->oauth->needs_reconnect();

		return rest_ensure_response( array(
			'connected'          => $connected && ! $reconnect,
			'reconnect_required' => $reconnect,
			'email'              => $email,
		) );
	}

	public function get_auth_url() {
		$url = $this->oauth->get_auth_url();

		if ( empty( $url ) ) {
			return new \WP_Error( 'gmc_no_credentials', 'Google OAuth credentials not configured.', array( 'status' => 400 ) );
		}

		return rest_ensure_response( array( 'url' => $url ) );
	}

	public function disconnect() {
		$this->oauth->disconnect();
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_settings() {
		return rest_ensure_response( array(
			'merchant_id'       => get_option( 'wseo_gmc_merchant_id', '' ),
			'realtime_sync'     => get_option( 'wseo_gmc_realtime_sync', '0' ) === '1',
			'sync_schedule'     => get_option( 'wseo_gmc_sync_schedule', 'disabled' ),
			'client_id'         => get_option( OAuth::OPTION_CLIENT_ID, '' ),
			'client_secret_set' => ! empty( get_option( OAuth::OPTION_CLIENT_SECRET, '' ) ),
		) );
	}

	public function save_settings( $request ) {
		$params = $request->get_json_params();

		if ( isset( $params['merchant_id'] ) ) {
			$merchant_id = sanitize_text_field( $params['merchant_id'] );
			if ( ! empty( $merchant_id ) && ! is_numeric( $merchant_id ) ) {
				return new \WP_Error( 'gmc_invalid_merchant_id', 'Merchant Center Account ID must be numeric.', array( 'status' => 400 ) );
			}
			update_option( 'wseo_gmc_merchant_id', $merchant_id );
		}

		if ( isset( $params['realtime_sync'] ) ) {
			update_option( 'wseo_gmc_realtime_sync', $params['realtime_sync'] ? '1' : '0' );
		}

		if ( isset( $params['sync_schedule'] ) ) {
			$this->update_sync_schedule( sanitize_text_field( $params['sync_schedule'] ) );
		}

		if ( isset( $params['client_id'] ) ) {
			update_option( OAuth::OPTION_CLIENT_ID, sanitize_text_field( $params['client_id'] ) );
		}

		if ( isset( $params['client_secret'] ) && ! empty( $params['client_secret'] ) ) {
			update_option( OAuth::OPTION_CLIENT_SECRET, Crypto::encrypt( $params['client_secret'] ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	private function update_sync_schedule( $schedule ) {
		$allowed = array( 'disabled', 'daily', 'weekly' );
		if ( ! in_array( $schedule, $allowed, true ) ) {
			$schedule = 'disabled';
		}

		update_option( 'wseo_gmc_sync_schedule', $schedule );
		$this->sync_engine->update_recurring_schedule( $schedule );
	}

	public function start_sync() {
		if ( ! $this->oauth->is_connected() ) {
			return new \WP_Error( 'gmc_not_connected', 'Google account not connected.', array( 'status' => 400 ) );
		}

		$merchant_id = get_option( 'wseo_gmc_merchant_id', '' );
		if ( empty( $merchant_id ) ) {
			return new \WP_Error( 'gmc_no_merchant_id', 'Merchant Center Account ID not set.', array( 'status' => 400 ) );
		}

		$state = $this->sync_engine->start_batch_sync( $merchant_id );
		return rest_ensure_response( $state );
	}

	public function get_sync_status() {
		$state = $this->sync_engine->get_sync_state();
		return rest_ensure_response( $state );
	}

	public function cancel_sync() {
		$this->sync_engine->cancel_sync();
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function pause_sync() {
		$this->sync_engine->pause_sync();
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function resume_sync() {
		$this->sync_engine->resume_sync();
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function get_gmc_logs( $request ) {
		$limit = absint( $request->get_param( 'limit' ) ) ?: 50;
		$logs  = \NovaToolsSEO\Core\Logger::get_logs( 'gmc_sync', $limit );
		$errors = \NovaToolsSEO\Core\Logger::get_logs( 'gmc_error', $limit );

		$all = array_merge( $logs, $errors );
		usort( $all, function ( $a, $b ) {
			return strtotime( $b['created_at'] ) - strtotime( $a['created_at'] );
		});

		return rest_ensure_response( array_slice( $all, 0, $limit ) );
	}
}
