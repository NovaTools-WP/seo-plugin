<?php

namespace NovaToolsSEO\Core;

defined( 'ABSPATH' ) || exit;

class DependencyCheck {

	public static function is_novatools_active() {
		return class_exists( 'NovaTools' );
	}

	public static function check_activation() {
		if ( ! self::is_novatools_active() ) {
			wp_die( esc_html__( 'NovaTools - SEO requires the NovaTools core plugin to be installed and activated. Please activate NovaTools first.', 'novatools-seo' ), 'Plugin Dependency Error', array( 'back_link' => true ) );
		}
	}

	public static function admin_notice() {
		if ( ! self::is_novatools_active() ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'NovaTools - SEO requires the NovaTools core plugin to be installed and active. The SEO features have been disabled.', 'novatools-seo' )
			);
		}
	}
}
