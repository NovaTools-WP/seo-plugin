<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Traits\Base;

class Settings {

	use Base;

	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings() {
		$settings = $this->get_all_setting_keys();

		foreach ( $settings as $key => $default ) {
			register_setting( 'novatools-seo', $key );
		}
	}

	public function get( $key, $default = '' ) {
		$value = get_option( 'wseo_' . $key, $default );
		return $value;
	}

	public function set( $key, $value ) {
		return update_option( 'wseo_' . $key, $value );
	}

	public function delete( $key ) {
		return delete_option( 'wseo_' . $key );
	}

	public function get_post_type_defaults( $post_type ) {
		return array(
			'title_template'    => $this->get( "general_{$post_type}_title_template", $this->get( 'general_title_template', '%%title%% %%sep%% %%sitename%%' ) ),
			'desc_template'     => $this->get( "general_{$post_type}_desc_template", $this->get( 'general_desc_template', '' ) ),
			'robots_default'    => $this->get( "general_{$post_type}_robots_default", $this->get( 'general_robots_default', 'index,follow' ) ),
			'sitemap_visibility' => $this->get( "general_{$post_type}_sitemap_visibility", '1' ),
		);
	}

	public function get_all_setting_keys() {
		return array(
			'wseo_general_title_template'    => '%%title%% %%sep%% %%sitename%%',
			'wseo_general_desc_template'     => '',
			'wseo_general_robots_default'    => 'index,follow',
			'wseo_social_og_default_image'   => '',
			'wseo_social_twitter_card_type'  => 'summary_large_image',
			'wseo_social_twitter_site'       => '',
			'wseo_social_pinterest_rich_pins' => '1',
			'wseo_sitemap_enabled'           => '1',
			'wseo_outofstock_threshold'      => '30',
			'wseo_robots_txt_content'        => '',
			'wseo_license_key'               => '',
			'wseo_indexnow_enabled'          => '',
			'wseo_indexnow_api_key'          => '',
			'wseo_ai_bot_rules'              => array(
				'preset_bots' => array(),
				'custom_bots' => array(),
				'path_rules'  => array(),
			),
			'wseo_local_seo'                 => array(
				'business_name'        => '',
				'business_address'     => '',
				'business_phone'       => '',
				'business_email'       => '',
				'sameas'               => array(),
				'geoshape_coordinates' => array(),
				'landmarks'            => array(),
			),
			'wseo_page_suffix_separator'     => '–',
		);
	}

	public function export_all() {
		$settings = array();
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				'wseo_%'
			)
		);

		foreach ( $results as $row ) {
			$settings[ $row->option_name ] = maybe_unserialize( $row->option_value );
		}

		return $settings;
	}

	public function import_all( $settings ) {
		foreach ( $settings as $key => $value ) {
			if ( 0 === strpos( $key, 'wseo_' ) ) {
				update_option( $key, $value );
			}
		}
	}
}
