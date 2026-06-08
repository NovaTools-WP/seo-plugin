<?php

use NovaToolsSEO\Admin\BrandSameAs;
use NovaToolsSEO\Admin\ContentListSeoColumn;
use NovaToolsSEO\Admin\License;
use NovaToolsSEO\Admin\Menu;
use NovaToolsSEO\Admin\MetaBox;
use NovaToolsSEO\Admin\Settings;
use NovaToolsSEO\Assets\Admin;
use NovaToolsSEO\Core\Api;
use NovaToolsSEO\Frontend\Breadcrumbs;
use NovaToolsSEO\Frontend\HeadOutput;
use NovaToolsSEO\Frontend\IndexNow;
use NovaToolsSEO\Frontend\RobotsTxt;
use NovaToolsSEO\Frontend\Schema;
use NovaToolsSEO\Frontend\ProductSchema;
use NovaToolsSEO\Redirects\Manager as RedirectManager;
use NovaToolsSEO\Sitemaps\Generator;
use NovaToolsSEO\Sitemaps\StockTracker;
use NovaToolsSEO\GMC\RestController as GMCRestController;
use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

final class NovaToolsSEO {

	use Base;

	public function __construct() {
		define( 'NOVATOOLS_SEO_VERSION', '1.0.1' );
		define( 'NOVATOOLS_SEO_PLUGIN_FILE', __FILE__ );
		define( 'NOVATOOLS_SEO_DIR', plugin_dir_path( __FILE__ ) );
		define( 'NOVATOOLS_SEO_URL', plugin_dir_url( __FILE__ ) );
		define( 'NOVATOOLS_SEO_ASSETS_URL', NOVATOOLS_SEO_URL . 'assets' );
		define( 'NOVATOOLS_SEO_ROUTE_PREFIX', 'novatools-seo/v1' );
	}

	public function init() {
		if ( is_admin() ) {
			Menu::get_instance()->init();
			Admin::get_instance()->bootstrap();
			MetaBox::get_instance()->init();
			License::get_instance()->init();
			ContentListSeoColumn::get_instance()->init();
		}

		HeadOutput::get_instance()->init();

		if ( class_exists( 'WooCommerce' ) ) {
			\NovaToolsSEO\WooCommerce\ProductOG::get_instance()->init();
			\NovaToolsSEO\WooCommerce\PinterestNamespace::get_instance()->init();
			ProductSchema::get_instance()->init();
			\NovaToolsSEO\Admin\ProductMetaBox::get_instance()->init();
			\NovaToolsSEO\WooCommerce\AltTextController::get_instance()->init();
			\NovaToolsSEO\WooCommerce\PrimaryCategory::get_instance()->init();
			\NovaToolsSEO\WooCommerce\ProductListSeoColumn::get_instance()->init();
			\NovaToolsSEO\WooCommerce\Filters\FilterParamsRepository::get_instance()->seed_defaults();
			\NovaToolsSEO\WooCommerce\Filters\FilterDetector::get_instance()->init();
			\NovaToolsSEO\WooCommerce\Taxonomy\TaxonomyNoindexRepository::get_instance()->seed_defaults();
		}

		BrandSameAs::get_instance()->init();
		Schema::get_instance()->init();
		IndexNow::get_instance()->init();
		RobotsTxt::get_instance()->init();
		Breadcrumbs::get_instance()->init();
		RedirectManager::get_instance()->init();
		Api::get_instance()->init();
		GMCRestController::get_instance()->init();
		Generator::get_instance()->init();
		StockTracker::get_instance()->init();
		Settings::get_instance()->init();

		add_action( 'init', array( $this, 'i18n' ) );
	}

	public function i18n() {
		load_plugin_textdomain( 'novatools-seo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
}
