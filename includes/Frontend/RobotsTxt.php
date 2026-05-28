<?php

namespace NovaToolsSEO\Frontend;

use NovaToolsSEO\Admin\Settings;
use NovaToolsSEO\Traits\Base;

class RobotsTxt {

	use Base;

	public function init() {
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 10, 2 );
	}

	public function filter_robots_txt( $output, $public ) {
		if ( '0' === $public ) {
			return $output;
		}

		$settings = Settings::get_instance();
		$custom = $settings->get( 'robots_txt_content', '' );

		if ( ! empty( $custom ) ) {
			return $custom;
		}

		return $output;
	}
}
