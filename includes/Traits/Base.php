<?php

namespace NovaToolsSEO\Traits;

defined( 'ABSPATH' ) || exit;

trait Base {

	private static $instance;

	public static function get_instance() {
		if ( ! static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}
}
