<?php
/**
 * Plugin Name: NovaTools - SEO
 * Description: Comprehensive SEO add-on for NovaTools — meta management, schema, sitemaps, redirects, breadcrumbs, and more.
 * Author: Siim Liimand
 * Author URI:
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 1.0.1
 * Text Domain: novatools-seo
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package NovaToolsSEO
 */

use NovaToolsSEO\Core\Install;
use NovaToolsSEO\Core\DependencyCheck;

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'plugin.php';

/**
 * Check dependencies on activation.
 */
function novatools_seo_activate() {
	DependencyCheck::check_activation();
	Install::get_instance()->init();
}
register_activation_hook( __FILE__, 'novatools_seo_activate' );

/**
 * Initializes the NovaTools SEO add-on when plugins are loaded.
 *
 * @since 1.0.0
 * @return void
 */
function novatools_seo_init() {
	if ( ! DependencyCheck::is_novatools_active() ) {
		add_action( 'admin_notices', array( DependencyCheck::class, 'admin_notice' ) );
		return;
	}

	NovaToolsSEO::get_instance()->init();
}

add_action( 'plugins_loaded', 'novatools_seo_init' );
