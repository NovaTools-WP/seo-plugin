<?php

namespace NovaToolsSEO\GMC;

defined( 'ABSPATH' ) || exit;

class Crypto {

	private static function key() {
		return hash( 'sha256', AUTH_KEY . LOGGED_IN_KEY, true );
	}

	public static function encrypt( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv        = openssl_random_pseudo_bytes( $iv_length );
		$encrypted = openssl_encrypt( $data, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return '';
		}

		return base64_encode( $iv . $encrypted );
	}

	public static function decrypt( $encoded ) {
		if ( empty( $encoded ) ) {
			return '';
		}

		$raw       = base64_decode( $encoded, true );
		$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );

		if ( false === $raw || strlen( $raw ) < $iv_length + 1 ) {
			return '';
		}

		$iv        = substr( $raw, 0, $iv_length );
		$cipher    = substr( $raw, $iv_length );
		$decrypted = openssl_decrypt( $cipher, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );

		if ( false === $decrypted ) {
			return '';
		}

		return $decrypted;
	}
}
