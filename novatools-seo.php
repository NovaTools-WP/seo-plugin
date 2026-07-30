<?php
/**
 * Plugin Name: NovaTools - SEO
 * Description: Comprehensive SEO add-on for NovaTools — meta management, schema, sitemaps, redirects, breadcrumbs, and more.
 * Author: NovaTools
 * Author URI: https://novatools.ww0.dev
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 1.0.1
 * Text Domain: novatools-seo
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: novatools
 *
 * @package NovaToolsSEO
 */

use NovaToolsSEO\Core\Install;
use NovaToolsSEO\Core\DependencyCheck;
use NovaToolsSEO\Database\Migrations\Redirects;
use NovaToolsSEO\Sitemaps\Generator;

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . 'plugin.php';

/**
 * Check dependencies on activation.
 */
function novatools_seo_activate() {
	DependencyCheck::check_activation();
	Install::get_instance()->init();
	// Backfill link counts for existing content (computed on save for new posts).
	NovaToolsSEO\Analysis\LinkCounter::get_instance()->backfill();

	// Register and persist the sitemap rewrite rules so the sitemap URLs
	// resolve immediately without a manual permalink flush.
	Generator::get_instance()->add_rewrite_rules();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'novatools_seo_activate' );

/**
 * Flush rewrite rules on deactivation so the sitemap rules are removed.
 */
function novatools_seo_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'novatools_seo_deactivate' );

/**
 * Run schema migrations on update without reactivation.
 */
add_action( 'plugins_loaded', function() {
	if ( ! DependencyCheck::is_novatools_active() ) {
		return;
	}
	$installed_version = get_option( Redirects::DB_VERSION_OPTION, '1.0.0' );
	if ( version_compare( $installed_version, Redirects::DB_VERSION, '<' ) ) {
		Redirects::migrate();
	}
}, 5 );

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

/**
 * Add settings link to the plugin action links.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'novatools_seo_add_settings_link' );
function novatools_seo_add_settings_link( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'admin.php?page=novatools#/seo' ),
		esc_html__( 'Settings', 'novatools-seo' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}

