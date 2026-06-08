<?php

namespace NovaToolsSEO\Frontend;

use NovaToolsSEO\Admin\Settings;
use NovaToolsSEO\Traits\Base;
use NovaToolsSEO\WooCommerce\Filters\FilterParamsRepository;

class RobotsTxt {

	use Base;

	public function init() {
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 10, 2 );
	}

	public function filter_robots_txt( $output, $public ) {
		if ( '0' === $public ) {
			return $output;
		}

		$settings = Settings::get_instance();
		$custom = $settings->get( 'robots_txt_content', '' );

		if ( ! empty( $custom ) ) {
			$output = $custom;
		}

		if ( class_exists( 'WooCommerce' ) ) {
			$output .= $this->get_filter_param_rules();
		}

		$output .= $this->get_ai_bot_rules();
		$output .= $this->get_sitemap_url();

		return $output;
	}

	private function get_filter_param_rules() {
		$repo = FilterParamsRepository::get_instance();
		$params = $repo->get_params();

		if ( empty( $params ) ) {
			return '';
		}

		$rules = "\n# NovaTools SEO - WooCommerce Filter Parameters\n";
		$rules .= "User-agent: *\n";

		foreach ( $params as $param ) {
			$rules .= sprintf( "Disallow: /*?*%s*\n", $param );
		}

		return $rules;
	}

	private function get_ai_bot_rules() {
		$rules_data = get_option( 'wseo_ai_bot_rules', array() );

		if ( empty( $rules_data ) || ! is_array( $rules_data ) ) {
			return '';
		}

		$output = "\n# NovaTools SEO - AI Bot Rules\n";

		// Preset bots
		$preset_bots = $rules_data['preset_bots'] ?? array();
		foreach ( $preset_bots as $bot ) {
			if ( ! empty( $bot['user_agent'] ) && ! empty( $bot['blocked'] ) ) {
				$output .= "User-agent: {$bot['user_agent']}\n";
				$output .= "Disallow: /\n";

				$path_rules = $bot['path_rules'] ?? array();
				foreach ( $path_rules as $rule ) {
					if ( ! empty( $rule['path'] ) ) {
						$directive = ! empty( $rule['allow'] ) ? 'Allow' : 'Disallow';
						$output .= "{$directive}: {$rule['path']}\n";
					}
				}
				$output .= "\n";
			}
		}

		// Custom bots
		$custom_bots = $rules_data['custom_bots'] ?? array();
		foreach ( $custom_bots as $bot ) {
			if ( ! empty( $bot['user_agent'] ) && ! empty( $bot['blocked'] ) ) {
				$output .= "User-agent: {$bot['user_agent']}\n";
				$output .= "Disallow: /\n";

				$path_rules = $bot['path_rules'] ?? array();
				foreach ( $path_rules as $rule ) {
					if ( ! empty( $rule['path'] ) ) {
						$directive = ! empty( $rule['allow'] ) ? 'Allow' : 'Disallow';
						$output .= "{$directive}: {$rule['path']}\n";
					}
				}
				$output .= "\n";
			}
		}

		return $output;
	}

	private function get_sitemap_url() {
		return "\nSitemap: " . home_url( '/sitemap.xml' ) . "\n";
	}
}
