<?php

namespace NovaToolsSEO\Redirects;

use NovaToolsSEO\Core\Logger;
use NovaToolsSEO\Database\Migrations\Redirects;
use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

class Manager {

	use Base;

	public function init() {
		$this->maybe_run_schema_migration();
		$this->check_redirects();
	}

	private function maybe_run_schema_migration() {
		$installed_version = get_option( Redirects::DB_VERSION_OPTION, '1.0.0' );
		if ( version_compare( $installed_version, Redirects::DB_VERSION, '<' ) ) {
			Redirects::migrate();
		}
	}

	public function check_redirects() {
		if ( is_admin() || defined( 'DOING_CRON' ) || defined( 'DOING_AJAX' ) ) {
			return;
		}

		$request_uri = $this->get_request_path();

		if ( empty( $request_uri ) || '/' === $request_uri ) {
			return;
		}

		$table_exists = get_transient( 'wseo_redirects_table_exists' );
		if ( false === $table_exists ) {
			global $wpdb;
			$table = $wpdb->prefix . 'wseo_redirects';
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			set_transient( 'wseo_redirects_table_exists', $table_exists ? 'yes' : 'no', DAY_IN_SECONDS );
			if ( ! $table_exists ) {
				return;
			}
		} elseif ( 'no' === $table_exists ) {
			return;
		}

		$redirects = wp_cache_get( 'wseo_redirects', 'novatools-seo' );
		if ( false === $redirects ) {
			global $wpdb;
			$table = $wpdb->prefix . 'wseo_redirects';
			$redirects = $wpdb->get_results( "SELECT * FROM `" . esc_sql( $table ) . "`" );
			wp_cache_set( 'wseo_redirects', $redirects, 'novatools-seo', HOUR_IN_SECONDS );
		}

		foreach ( $redirects as $redirect ) {
			$matched = false;

			if ( $redirect->is_regex ) {
				$result = preg_match( $redirect->source_url, $request_uri );
				if ( false === $result ) {
					Logger::log( 'warning', 'Invalid redirect regex pattern.', array(
						'redirect_id' => (int) $redirect->id,
						'pattern'     => $redirect->source_url,
						'error_code'  => preg_last_error(),
					) );
					continue;
				}
				if ( $result ) {
					$matched = true;
					$destination = preg_replace( $redirect->source_url, $redirect->destination_url, $request_uri );
					if ( null === $destination ) {
						Logger::log( 'warning', 'Regex replace failed for redirect.', array(
							'redirect_id' => (int) $redirect->id,
							'pattern'     => $redirect->source_url,
							'error_code'  => preg_last_error(),
						) );
						continue;
					}
				}
			} else {
				if ( trailingslashit( $request_uri ) === trailingslashit( $redirect->source_url ) ) {
					$matched = true;
					$destination = $redirect->destination_url;
				}
			}

			if ( $matched && ! empty( $destination ) ) {
				$site_host      = wp_parse_url( home_url(), PHP_URL_HOST );
				$dest_host      = wp_parse_url( $destination, PHP_URL_HOST );
				$is_same_domain = $dest_host && strtolower( $dest_host ) === strtolower( $site_host );

				if ( ! $is_same_domain ) {
					$stored_domains = get_option( 'wseo_redirect_allowed_domains', array() );
					$allowed_domains = apply_filters( 'wseo_allowed_redirect_domains', $stored_domains );

					if ( ! empty( $allowed_domains ) ) {
						$allowed_lower = array_map( 'strtolower', $allowed_domains );
						if ( ! in_array( strtolower( $dest_host ), $allowed_lower, true ) ) {
							Logger::log( 'warning', 'Redirect blocked by domain allowlist.', array(
								'redirect_id' => (int) $redirect->id,
								'destination' => $destination,
								'host'        => $dest_host,
							) );
							continue;
						}
					}
				}

				$status_code = (int) $redirect->status_code;
				if ( ! in_array( $status_code, array( 301, 302, 303, 307, 308 ), true ) ) {
					$status_code = 301;
				}

				wp_redirect( esc_url_raw( $destination ), $status_code );
				exit;
			}
		}
	}

	private function get_request_path() {
		$parsed = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
		return $parsed['path'] ?? '';
	}
}
