<?php

namespace NovaToolsSEO\Redirects;

use NovaToolsSEO\Traits\Base;

class Manager {

	use Base;

	public function init() {
		$this->check_redirects();
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
				if ( @preg_match( $redirect->source_url, $request_uri ) ) {
					$matched = true;
					$destination = @preg_replace( $redirect->source_url, $redirect->destination_url, $request_uri );
				}
			} else {
				if ( trailingslashit( $request_uri ) === trailingslashit( $redirect->source_url ) ) {
					$matched = true;
					$destination = $redirect->destination_url;
				}
			}

			if ( $matched && ! empty( $destination ) ) {
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
