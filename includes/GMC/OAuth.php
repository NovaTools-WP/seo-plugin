<?php

namespace NovaToolsSEO\GMC;

use NovaToolsSEO\Core\Logger;

defined( 'ABSPATH' ) || exit;

class OAuth {

	const OPTION_REFRESH_TOKEN = 'wseo_gmc_refresh_token';
	const OPTION_ACCESS_TOKEN  = 'wseo_gmc_access_token';
	const OPTION_TOKEN_EXPIRES = 'wseo_gmc_token_expires';
	const OPTION_ACCOUNT_EMAIL = 'wseo_gmc_account_email';
	const OPTION_CLIENT_ID     = 'wseo_gmc_client_id';
	const OPTION_CLIENT_SECRET = 'wseo_gmc_client_secret';

	const OPTION_TOKEN_REVOKED = 'wseo_gmc_token_revoked';

	const SCOPES = array( 'https://www.googleapis.com/auth/content' );

	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_callback_route' ) );
	}

	public function register_callback_route() {
		register_rest_route( 'novatools-seo/v1', '/gmc/callback', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_callback' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function get_auth_url() {
		$client_id = get_option( self::OPTION_CLIENT_ID, '' );
		if ( empty( $client_id ) ) {
			return '';
		}

		$state = wp_generate_password( 32, false );
		set_transient( 'wseo_gmc_oauth_state_' . $state, true, 5 * MINUTE_IN_SECONDS );

		$redirect_uri = rest_url( 'novatools-seo/v1/gmc/callback' );
		$params       = array(
			'client_id'             => $client_id,
			'redirect_uri'          => $redirect_uri,
			'response_type'         => 'code',
			'scope'                 => implode( ' ', self::SCOPES ),
			'access_type'           => 'offline',
			'prompt'                => 'consent',
			'state'                 => $state,
		);

		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
	}

	public function handle_callback( $request ) {
		$code  = $request->get_param( 'code' );
		$error = $request->get_param( 'error' );
		$state = $request->get_param( 'state' );

		$admin_url = admin_url( 'admin.php?page=novatools-seo#/integrations' );

		if ( ! $state || ! get_transient( 'wseo_gmc_oauth_state_' . $state ) ) {
			Logger::log( 'gmc_auth', 'OAuth callback failed: invalid state parameter' );
			wp_redirect( $admin_url . '?gmc=error' );
			exit;
		}

		delete_transient( 'wseo_gmc_oauth_state_' . $state );

		if ( ! empty( $error ) || empty( $code ) ) {
			Logger::log( 'gmc_auth', 'OAuth callback failed: ' . ( $error ?? 'no code' ) );
			wp_redirect( $admin_url . '?gmc=error' );
			exit;
		}

		$result = $this->exchange_code( $code );

		if ( is_wp_error( $result ) ) {
			Logger::log( 'gmc_auth', 'Token exchange failed: ' . $result->get_error_message() );
			wp_redirect( $admin_url . '?gmc=error' );
			exit;
		}

		wp_redirect( $admin_url . '?gmc=connected' );
		exit;
	}

	public function exchange_code( $code ) {
		$client_id     = get_option( self::OPTION_CLIENT_ID, '' );
		$client_secret = Crypto::decrypt( get_option( self::OPTION_CLIENT_SECRET, '' ) );
		$redirect_uri  = rest_url( 'novatools-seo/v1/gmc/callback' );

		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'body' => array(
				'code'          => $code,
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'redirect_uri'  => $redirect_uri,
				'grant_type'    => 'authorization_code',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['refresh_token'] ) ) {
			return new \WP_Error( 'gmc_no_refresh_token', 'No refresh token in response' );
		}

		$this->store_tokens( $body );

		return true;
	}

	public function store_tokens( $token_data ) {
		if ( ! empty( $token_data['refresh_token'] ) ) {
			update_option( self::OPTION_REFRESH_TOKEN, Crypto::encrypt( $token_data['refresh_token'] ), 'no' );
		}

		if ( ! empty( $token_data['access_token'] ) ) {
			update_option( self::OPTION_ACCESS_TOKEN, Crypto::encrypt( $token_data['access_token'] ), 'no' );
		}

		if ( ! empty( $token_data['expires_in'] ) ) {
			update_option( self::OPTION_TOKEN_EXPIRES, time() + (int) $token_data['expires_in'], 'no' );
		}

		$this->fetch_account_email( $token_data['access_token'] ?? '' );
		delete_option( self::OPTION_TOKEN_REVOKED );
	}

	private function fetch_account_email( $access_token ) {
		$response = wp_remote_get( 'https://www.googleapis.com/oauth2/v2/userinfo', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
		) );

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['email'] ) ) {
				update_option( self::OPTION_ACCOUNT_EMAIL, sanitize_email( $body['email'] ), 'no' );
			}
		}
	}

	public function is_connected() {
		$encrypted = get_option( self::OPTION_REFRESH_TOKEN, '' );
		return ! empty( $encrypted );
	}

	public function needs_reconnect() {
		return get_option( self::OPTION_TOKEN_REVOKED, '0' ) === '1';
	}

	public function get_account_email() {
		return get_option( self::OPTION_ACCOUNT_EMAIL, '' );
	}

	public function get_access_token() {
		$expires = (int) get_option( self::OPTION_TOKEN_EXPIRES, 0 );

		if ( time() < $expires - 60 ) {
			$encrypted = get_option( self::OPTION_ACCESS_TOKEN, '' );
			$token     = Crypto::decrypt( $encrypted );
			if ( ! empty( $token ) ) {
				return $token;
			}
		}

		return $this->refresh_access_token();
	}

	private function refresh_access_token() {
		$encrypted = get_option( self::OPTION_REFRESH_TOKEN, '' );
		$refresh   = Crypto::decrypt( $encrypted );

		if ( empty( $refresh ) ) {
			update_option( self::OPTION_TOKEN_REVOKED, '1', 'no' );
			return false;
		}

		$client_id     = get_option( self::OPTION_CLIENT_ID, '' );
		$client_secret = Crypto::decrypt( get_option( self::OPTION_CLIENT_SECRET, '' ) );

		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'body' => array(
				'refresh_token' => $refresh,
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'grant_type'    => 'refresh_token',
			),
		) );

		if ( is_wp_error( $response ) ) {
			Logger::log( 'gmc_error', 'Token refresh failed: ' . $response->get_error_message() );
			update_option( self::OPTION_TOKEN_REVOKED, '1', 'no' );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['error'] ) ) {
			Logger::log( 'gmc_error', 'Token refresh error: ' . wp_json_encode( $body ) );
			update_option( self::OPTION_TOKEN_REVOKED, '1', 'no' );
			return false;
		}

		if ( ! empty( $body['access_token'] ) ) {
			update_option( self::OPTION_ACCESS_TOKEN, Crypto::encrypt( $body['access_token'] ), 'no' );
			update_option( self::OPTION_TOKEN_EXPIRES, time() + (int) ( $body['expires_in'] ?? 3600 ), 'no' );
			return $body['access_token'];
		}

		return false;
	}

	public function disconnect() {
		delete_option( self::OPTION_REFRESH_TOKEN );
		delete_option( self::OPTION_ACCESS_TOKEN );
		delete_option( self::OPTION_TOKEN_EXPIRES );
		delete_option( self::OPTION_ACCOUNT_EMAIL );
		delete_option( self::OPTION_TOKEN_REVOKED );
		Logger::log( 'gmc_auth', 'Google account disconnected' );
	}
}
