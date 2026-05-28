<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Traits\Base;

class Import {

	use Base;

	public function import( $json_string ) {
		$data = json_decode( $json_string, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'invalid_json', __( 'Invalid JSON format.', 'novatools-seo' ) );
		}

		if ( empty( $data ) || ! is_array( $data ) ) {
			return new \WP_Error( 'empty_data', __( 'No settings data found.', 'novatools-seo' ) );
		}

		$settings = Settings::get_instance();
		unset( $data['_meta'] );
		$settings->import_all( $data );

		return true;
	}
}
