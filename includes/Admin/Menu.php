<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Core\DependencyCheck;
use NovaToolsSEO\Traits\Base;

class Menu {

	use Base;

	private $parent_slug = 'novatools';

	public function init() {
		if ( DependencyCheck::is_novatools_active() ) {
			add_filter( 'novatools_admin_routes', array( $this, 'register_routes' ) );
			add_filter( 'novatools_submenu_pages', array( $this, 'add_seo_submenu' ) );
		} else {
			add_action( 'admin_menu', array( $this, 'register_standalone_menu' ) );
		}
	}

	public function register_routes( $routes ) {
		$script_handle = 'novatools-seo-addon';

		$routes[] = array(
			'addonId'      => 'novatools-seo',
			'path'         => 'seo',
			'component'    => 'Dashboard',
			'navLabel'     => __( 'SEO', 'novatools-seo' ),
			'icon'         => 'Search',
			'scriptHandle' => $script_handle,
		);

		$sub_routes = array(
			array( 'path' => 'seo/general-settings', 'component' => 'GeneralSettings' ),
			array( 'path' => 'seo/sitemaps',         'component' => 'Sitemaps' ),
			array( 'path' => 'seo/social-media',      'component' => 'SocialMedia' ),
			array( 'path' => 'seo/redirects',         'component' => 'RedirectManager' ),
			array( 'path' => 'seo/tools',             'component' => 'Tools' ),
		);

		foreach ( $sub_routes as $sub ) {
			$routes[] = array(
				'addonId'      => 'novatools-seo',
				'path'         => $sub['path'],
				'component'    => $sub['component'],
				'navLabel'     => '',
				'icon'         => '',
				'scriptHandle' => $script_handle,
				'parent'       => 'seo',
			);
		}

		return $routes;
	}

	public function add_seo_submenu( $submenu_pages ) {
		$plugin_url = admin_url( '/admin.php?page=novatools' );

		$submenu_pages[] = array(
			'parent_slug' => $this->parent_slug,
			'page_title'  => __( 'SEO', 'novatools-seo' ),
			'menu_title'  => __( 'SEO', 'novatools-seo' ),
			'capability'  => 'manage_options',
			'menu_slug'   => $plugin_url . '#/seo',
			'function'    => null,
		);

		return $submenu_pages;
	}

	private function register_standalone_menu() {
		add_menu_page(
			__( 'SEO', 'novatools-seo' ),
			__( 'SEO', 'novatools-seo' ),
			'manage_options',
			'novatools-seo',
			array( $this, 'admin_page' ),
			'dashicons-search',
			3
		);
	}

	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div id="novatools-seo-app" class="novatools-seo-app"></div>
		<?php
	}
}
