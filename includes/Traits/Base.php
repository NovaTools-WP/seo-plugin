<?php

namespace NovaToolsSEO\Traits;

defined( 'ABSPATH' ) || exit;

trait Base {

	private static $instance;

	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
