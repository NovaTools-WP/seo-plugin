<?php

namespace NovaToolsSEO\Assets;

use NovaToolsSEO\Core\DependencyCheck;
use NovaToolsSEO\Traits\Base;
use NovaToolsSEO\Libs\Assets;

defined( 'ABSPATH' ) || exit;

class Admin {

	use Base;

	const HANDLE         = 'novatools-seo';
	const ADDON_HANDLE   = 'novatools-seo-addon';
	const OBJ_NAME       = 'novaToolsSEO';
	const DEV_SCRIPT     = 'src/admin/main.jsx';
	const DEV_SCRIPT_ADDON = 'src/admin/addon-entry.jsx';

	private $allowed_screens = array(
		'toplevel_page_novatools-seo',
		'novatools_page_novatools-seo',
	);

	public function bootstrap() {
		if ( DependencyCheck::is_novatools_active() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'register_addon_script' ), 9 );
		} else {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script' ) );
		}
	}

	public function register_addon_script() {
		Assets\enqueue_asset(
			NOVATOOLS_SEO_DIR . '/assets/admin/dist',
			self::DEV_SCRIPT_ADDON,
			$this->get_addon_config()
		);

		wp_localize_script( self::ADDON_HANDLE, self::OBJ_NAME, $this->get_data() );
	}

	public function enqueue_script( $screen ) {
		if ( ! in_array( $screen, $this->allowed_screens, true ) ) {
			return;
		}

		Assets\enqueue_asset(
			NOVATOOLS_SEO_DIR . '/assets/admin/dist',
			self::DEV_SCRIPT,
			$this->get_config()
		);

		wp_localize_script( self::HANDLE, self::OBJ_NAME, $this->get_data() );
	}

	public function get_config() {
		return array(
			'dependencies' => array( 'react', 'react-dom' ),
			'handle'       => self::HANDLE,
			'in-footer'    => true,
		);
	}

	public function get_addon_config() {
		return array(
			'dependencies' => array( 'react', 'react-dom' ),
			'handle'       => self::ADDON_HANDLE,
			'in-footer'    => true,
		);
	}

	public function get_data() {
		return array(
			'apiUrl'         => rest_url(),
			'siteUrl'        => home_url( '/' ),
			'siteName'       => get_bloginfo( 'name' ),
			'siteTagline'    => get_bloginfo( 'description' ),
			'isAdmin'        => is_admin(),
			'version'        => NOVATOOLS_SEO_VERSION,
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'postTypes'      => $this->get_public_post_types(),
			'hasWooCommerce'        => function_exists( 'wc_get_page_id' ),
			'hasYoast'               => class_exists( 'WPSEO_Options' ),
			'actionSchedulerAvailable' => class_exists( 'ActionScheduler' ),
		);
	}

	private function get_public_post_types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		$result = array();
		foreach ( $types as $type ) {
			$result[] = array(
				'name'  => $type->name,
				'label' => $type->labels->singular_name,
			);
		}
		return $result;
	}
}
