<?php

namespace NovaToolsSEO\WooCommerce\Filters;

use NovaToolsSEO\Traits\Base;

class FilterDetector {

	use Base;

	private $is_faceted = null;

	public function init() {
		add_action( 'template_redirect', array( $this, 'detect' ), 1 );
	}

	public function detect() {
		$params = FilterParamsRepository::get_instance()->get_params();
		$get_keys = array_keys( $_GET );

		foreach ( $params as $pattern ) {
			if ( substr( $pattern, -2 ) === '_*' ) {
				$prefix = substr( $pattern, 0, -1 );
				foreach ( $get_keys as $key ) {
					if ( strpos( $key, $prefix ) === 0 ) {
						$this->is_faceted = true;
						$this->send_noindex_header();
						return;
					}
				}
			} else {
				if ( isset( $_GET[ $pattern ] ) ) {
					$this->is_faceted = true;
					$this->send_noindex_header();
					return;
				}
			}
		}

		$this->is_faceted = false;
	}

	public function is_faceted_url() {
		return true === $this->is_faceted;
	}

	public function get_detected_params() {
		if ( ! $this->is_faceted_url() ) {
			return array();
		}

		$params = FilterParamsRepository::get_instance()->get_params();
		$detected = array();
		$get_keys = array_keys( $_GET );

		foreach ( $params as $pattern ) {
			if ( substr( $pattern, -2 ) === '_*' ) {
				$prefix = substr( $pattern, 0, -1 );
				foreach ( $get_keys as $key ) {
					if ( strpos( $key, $prefix ) === 0 ) {
						$detected[] = $key;
					}
				}
			} else {
				if ( isset( $_GET[ $pattern ] ) ) {
					$detected[] = $pattern;
				}
			}
		}

		return $detected;
	}

	private function send_noindex_header() {
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow' );
		}
	}
}
