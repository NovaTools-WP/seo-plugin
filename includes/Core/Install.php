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
		$this->set_default_options();
		$this->schedule_cron_events();
	}

	private function create_tables() {
		Logs::up();
		Redirects::up();
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
	}
}
