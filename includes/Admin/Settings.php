<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

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
			'wseo_sitemap_ping_enabled'      => '1',
			'wseo_sitemap_product_images'    => '1',
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
			'wseo_redirect_allowed_domains'  => array(),
			'wseo_cornerstone_separate_sitemap' => '',
			'wseo_setup_completed'           => '',
			'wseo_setup_skipped'             => '',
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
		$allowed_keys = $this->get_all_setting_keys();
		foreach ( $settings as $key => $value ) {
			if ( array_key_exists( $key, $allowed_keys ) ) {
				$sanitized_value = $this->sanitize_setting( $key, $value );
				if ( null !== $sanitized_value ) {
					update_option( $key, $sanitized_value );
				}
			}
		}
	}

	public function sanitize_setting( $key, $value ) {
		switch ( $key ) {
			case 'wseo_general_title_template':
			case 'wseo_general_desc_template':
			case 'wseo_general_robots_default':
			case 'wseo_social_twitter_card_type':
			case 'wseo_social_twitter_site':
			case 'wseo_social_pinterest_rich_pins':
			case 'wseo_sitemap_enabled':
			case 'wseo_sitemap_ping_enabled':
			case 'wseo_sitemap_product_images':
			case 'wseo_license_key':
			case 'wseo_indexnow_enabled':
			case 'wseo_indexnow_api_key':
			case 'wseo_page_suffix_separator':
			case 'wseo_cornerstone_separate_sitemap':
			case 'wseo_setup_completed':
			case 'wseo_setup_skipped':
				return sanitize_text_field( $value );

			case 'wseo_social_og_default_image':
				return esc_url_raw( $value );

			case 'wseo_outofstock_threshold':
				return absint( $value );

			case 'wseo_robots_txt_content':
				return sanitize_textarea_field( $value );

			case 'wseo_redirect_allowed_domains':
				if ( ! is_array( $value ) ) {
					return array();
				}
				$domains = array();
				foreach ( $value as $domain ) {
					$domain = strtolower( sanitize_text_field( $domain ) );
					$domain = preg_replace( '#^https?://#', '', $domain );
					$domain = rtrim( $domain, '/' );
					if ( ! empty( $domain ) ) {
						$domains[] = $domain;
					}
				}
				return array_values( array_unique( $domains ) );

			case 'wseo_ai_bot_rules':
				if ( ! is_array( $value ) ) {
					return array();
				}
				$sanitized = array(
					'preset_bots' => array(),
					'custom_bots' => array(),
					'path_rules'  => array(),
				);
				foreach ( array( 'preset_bots', 'custom_bots' ) as $bot_type ) {
					if ( ! empty( $value[ $bot_type ] ) && is_array( $value[ $bot_type ] ) ) {
						foreach ( $value[ $bot_type ] as $bot ) {
							$sanitized_bot = array(
								'user_agent' => sanitize_text_field( $bot['user_agent'] ?? '' ),
								'blocked'    => ! empty( $bot['blocked'] ) ? '1' : '',
							);
							if ( isset( $bot['path_rules'] ) && is_array( $bot['path_rules'] ) ) {
								$sanitized_bot['path_rules'] = array();
								foreach ( $bot['path_rules'] as $rule ) {
									$sanitized_bot['path_rules'][] = array(
										'path'  => sanitize_text_field( $rule['path'] ?? '' ),
										'allow' => ! empty( $rule['allow'] ) ? '1' : '',
									);
								}
							}
							$sanitized[ $bot_type ][] = $sanitized_bot;
						}
					}
				}
				return $sanitized;

			case 'wseo_local_seo':
				if ( ! is_array( $value ) ) {
					return array();
				}
				$sanitized = array(
					'business_name'        => sanitize_text_field( $value['business_name'] ?? '' ),
					'business_address'     => sanitize_text_field( $value['business_address'] ?? '' ),
					'business_phone'       => sanitize_text_field( $value['business_phone'] ?? '' ),
					'business_email'       => sanitize_email( $value['business_email'] ?? '' ),
					'sameas'               => array(),
					'geoshape_coordinates' => array(),
					'landmarks'            => array(),
				);
				if ( isset( $value['sameas'] ) && is_array( $value['sameas'] ) ) {
					foreach ( $value['sameas'] as $url ) {
						$url = esc_url_raw( $url );
						if ( $url ) {
							$sanitized['sameas'][] = $url;
						}
					}
				}
				if ( isset( $value['geoshape_coordinates'] ) && is_array( $value['geoshape_coordinates'] ) ) {
					foreach ( $value['geoshape_coordinates'] as $coord ) {
						$sanitized['geoshape_coordinates'][] = sanitize_text_field( $coord );
					}
				}
				if ( isset( $value['landmarks'] ) && is_array( $value['landmarks'] ) ) {
					foreach ( $value['landmarks'] as $landmark ) {
						$sanitized['landmarks'][] = sanitize_text_field( $landmark );
					}
				}
				return $sanitized;

			default:
				return null;
		}
	}
}
