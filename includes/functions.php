<?php

namespace NovaToolsSEO\Core;

defined( 'ABSPATH' ) || exit;

class DependencyCheck {

	public static function is_novatools_active() {
		return class_exists( 'NovaTools' );
	}

	public static function admin_notice() {
		if ( ! self::is_novatools_active() ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html__( 'NovaTools - SEO works best with NovaTools core installed and active. Some features may be limited.', 'novatools-seo' )
			);
		}
	}
}
