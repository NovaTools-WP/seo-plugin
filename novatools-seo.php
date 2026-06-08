<?php
/**
 * Plugin Name: NovaTools - SEO
 * Description: Comprehensive SEO add-on for NovaTools — meta management, schema, sitemaps, redirects, breadcrumbs, and more.
 * Author:
 * Author URI:
 * License: GPLv2
 * Version: 1.0.1
 * Text Domain: novatools-seo
 * Domain Path: /languages
 *
 * @package NovaToolsSEO
 */

use NovaToolsSEO\Core\Install;
use NovaToolsSEO\Core\DependencyCheck;

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'plugin.php';

/**
 * Initializes the NovaTools SEO add-on when plugins are loaded.
 *
 * @since 1.0.0
 * @return void
 */
function novatools_seo_init() {
	NovaToolsSEO::get_instance()->init();

	if ( ! DependencyCheck::is_novatools_active() ) {
		add_action( 'admin_notices', array( DependencyCheck::class, 'admin_notice' ) );
	}
}

add_action( 'plugins_loaded', 'novatools_seo_init' );

register_activation_hook( __FILE__, array( Install::get_instance(), 'init' ) );
