<?php

namespace NovaToolsSEO\Core;

use NovaToolsSEO\Database\Migrations\Redirects;
use NovaToolsSEO\Database\Migrations\Logs;
use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

class Install {

	use Base;

	public function init() {
		$this->create_tables();
		$this->migrate_tables();
		$this->set_default_options();
		$this->schedule_cron_events();
		$this->disable_autoload_for_gmc_options();
	}

	private function create_tables() {
		Logs::up();
		Redirects::up();
	}

	private function migrate_tables() {
		Redirects::migrate();
	}

	private function set_default_options() {
		$defaults = array(
			'wseo_general_title_template'    => '%%title%% %%sep%% %%sitename%%',
			'wseo_general_desc_template'     => '',
			'wseo_general_robots_default'    => 'index,follow',
			'wseo_social_og_default_image'   => '',
			'wseo_social_twitter_card_type'  => 'summary_large_image',
			'wseo_social_twitter_site'       => '',
			'wseo_sitemap_enabled'           => '1',
			'wseo_robots_txt_content'        => "User-agent: *\nDisallow: /wp-admin/\nDisallow: /wp-includes/\nDisallow: /wp-login.php\nDisallow: /wp-register.php\nDisallow: /trackback/\nDisallow: /feed/\nDisallow: /comments/",
			'wseo_log_retention_days'        => '30',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	private function schedule_cron_events() {
		if ( ! wp_next_scheduled( 'wseo_sitemap_rebuild' ) ) {
			wp_schedule_event( time(), 'daily', 'wseo_sitemap_rebuild' );
		}

		if ( ! wp_next_scheduled( 'wseo_license_check' ) ) {
			wp_schedule_event( time(), 'weekly', 'wseo_license_check' );
		}

		if ( ! wp_next_scheduled( 'wseo_logs_cleanup' ) ) {
			wp_schedule_event( time(), 'weekly', 'wseo_logs_cleanup' );
		}
	}

	private function disable_autoload_for_gmc_options() {
		global $wpdb;

		$option_names = array(
			'wseo_gmc_sync_state',
			'wseo_gmc_access_token',
			'wseo_gmc_token_expires',
			'wseo_gmc_refresh_token',
			'wseo_gmc_account_email',
			'wseo_gmc_client_id',
			'wseo_gmc_client_secret',
			'wseo_gmc_merchant_id',
			'wseo_gmc_realtime_sync',
			'wseo_gmc_sync_schedule',
			'wseo_gmc_token_revoked',
		);

		$placeholders = implode( ',', array_fill( 0, count( $option_names ), '%s' ) );

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->options} SET autoload = 'no' WHERE option_name IN ( {$placeholders} ) AND autoload = 'yes'",
			$option_names
		) );
	}
}
