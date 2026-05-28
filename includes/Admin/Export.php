<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Traits\Base;

class Export {

	use Base;

	public function export() {
		$settings = Settings::get_instance();
		$data = $settings->export_all();

		$data['_meta'] = array(
			'plugin'    => 'novatools-seo',
			'version'   => NOVATOOLS_SEO_VERSION,
			'exported'  => date( 'c' ),
		);

		return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}
}
