<?php

namespace NovaToolsSEO\Frontend;

use NovaToolsSEO\Admin\Settings;
use NovaToolsSEO\Core\Tokens;
use NovaToolsSEO\Traits\Base;

class HeadOutput {

	use Base;

	public function init() {
		add_action( 'wp_head', array( $this, 'output' ), 1 );
	}

	public function output() {
		$this->output_title();
		$this->output_description();
		$this->output_robots();
		$this->output_canonical();
		$this->output_opengraph();
		$this->output_twitter();
	}

	private function get_post_seo_data() {
		if ( ! is_singular() ) {
			return array();
		}

		$post_id = get_queried_object_id();
		$keys = array( '_wseo_title', '_wseo_description', '_wseo_canonical', '_wseo_robots', '_wseo_og_title', '_wseo_og_description', '_wseo_og_image', '_wseo_twitter_card', '_wseo_twitter_title', '_wseo_twitter_description', '_wseo_twitter_image' );

		$data = array();
		foreach ( $keys as $key ) {
			$data[ $key ] = get_post_meta( $post_id, $key, true );
		}

		return $data;
	}

	private function get_term_seo_data() {
		if ( ! ( is_category() || is_tag() || is_tax() ) ) {
			return array();
		}

		$term_id = get_queried_object_id();
		$keys = array( '_wseo_title', '_wseo_description', '_wseo_robots' );

		$data = array();
		foreach ( $keys as $key ) {
			$data[ $key ] = get_term_meta( $term_id, $key, true );
		}

		return $data;
	}

	private function output_title() {
		$meta = $this->get_post_seo_data();
		$term_meta = $this->get_term_seo_data();

		$title = '';
		if ( ! empty( $meta['_wseo_title'] ) ) {
			$title = $meta['_wseo_title'];
		} elseif ( ! empty( $term_meta['_wseo_title'] ) ) {
			$title = $term_meta['_wseo_title'];
		}

		if ( empty( $title ) ) {
			$settings = Settings::get_instance();
			$post_type = get_post_type();
			if ( $post_type ) {
				$template = $settings->get( "general_{$post_type}_title_template", $settings->get( 'general_title_template', '%%title%% %%sep%% %%sitename%%' ) );
			} else {
				$template = $settings->get( 'general_title_template', '%%title%% %%sep%% %%sitename%%' );
			}
			$title = Tokens::replace( $template );
		}

		if ( ! empty( $title ) ) {
			printf(
				'<meta name="title" content="%s">' . "\n",
				esc_attr( $title )
			);
		}
	}

	private function output_description() {
		$meta = $this->get_post_seo_data();
		$term_meta = $this->get_term_seo_data();

		$description = '';
		if ( ! empty( $meta['_wseo_description'] ) ) {
			$description = $meta['_wseo_description'];
		} elseif ( ! empty( $term_meta['_wseo_description'] ) ) {
			$description = $term_meta['_wseo_description'];
		}

		if ( ! empty( $description ) ) {
			printf(
				'<meta name="description" content="%s">' . "\n",
				esc_attr( $description )
			);
		}
	}

	private function output_robots() {
		$meta = $this->get_post_seo_data();
		$term_meta = $this->get_term_seo_data();

		$robots = '';
		if ( ! empty( $meta['_wseo_robots'] ) ) {
			$robots = $meta['_wseo_robots'];
		} elseif ( ! empty( $term_meta['_wseo_robots'] ) ) {
			$robots = $term_meta['_wseo_robots'];
		}

		if ( empty( $robots ) ) {
			$settings = Settings::get_instance();
			$post_type = get_post_type();
			if ( $post_type ) {
				$robots = $settings->get( "general_{$post_type}_robots_default", $settings->get( 'general_robots_default', 'index,follow' ) );
			} else {
				$robots = $settings->get( 'general_robots_default', 'index,follow' );
			}
		}

		printf(
			'<meta name="robots" content="%s">' . "\n",
			esc_attr( $robots )
		);
	}

	private function output_canonical() {
		$meta = $this->get_post_seo_data();

		$url = '';
		if ( ! empty( $meta['_wseo_canonical'] ) ) {
			$url = $meta['_wseo_canonical'];
		} else {
			$url = $this->get_current_canonical_url();
		}

		if ( ! empty( $url ) ) {
			printf(
				'<link rel="canonical" href="%s">' . "\n",
			 esc_url( $url )
			);
		}
	}

	private function get_current_canonical_url() {
		if ( is_singular() ) {
			return get_permalink();
		} elseif ( is_category() || is_tag() || is_tax() ) {
			return get_term_link( get_queried_object() );
		} elseif ( is_post_type_archive() ) {
			return get_post_type_archive_link( get_post_type() );
		} elseif ( is_home() ) {
			return home_url( '/' );
		}

		return '';
	}

	private function output_opengraph() {
		$meta = $this->get_post_seo_data();
		$settings = Settings::get_instance();

		$title = $meta['_wseo_og_title'] ?? '';
		if ( empty( $title ) ) {
			$title = $meta['_wseo_title'] ?? '';
		}

		$description = $meta['_wseo_og_description'] ?? '';
		if ( empty( $description ) ) {
			$description = $meta['_wseo_description'] ?? '';
		}

		$image = $meta['_wseo_og_image'] ?? '';
		if ( empty( $image ) && is_singular() ) {
			$image = get_the_post_thumbnail_url( get_queried_object_id(), 'full' );
		}
		if ( empty( $image ) ) {
			$image = $settings->get( 'social_og_default_image', '' );
		}

		$url = $this->get_current_canonical_url();

		$type = 'website';
		if ( is_singular( 'post' ) ) {
			$type = 'article';
		}

		$tags = array();
		if ( $title ) {
			$tags['og:title'] = $title;
		}
		if ( $description ) {
			$tags['og:description'] = $description;
		}
		if ( $url ) {
			$tags['og:url'] = $url;
		}
		$tags['og:type'] = $type;
		if ( $image ) {
			$tags['og:image'] = $image;
		}
		$tags['og:site_name'] = get_bloginfo( 'name' );
		$tags['og:locale'] = get_locale();

		foreach ( $tags as $property => $content ) {
			printf(
				'<meta property="%s" content="%s">' . "\n",
				esc_attr( $property ),
				esc_attr( $content )
			);
		}
	}

	private function output_twitter() {
		$meta = $this->get_post_seo_data();
		$settings = Settings::get_instance();

		$card_type = $meta['_wseo_twitter_card'] ?? $settings->get( 'social_twitter_card_type', 'summary_large_image' );

		$title = $meta['_wseo_twitter_title'] ?? '';
		if ( empty( $title ) ) {
			$title = $meta['_wseo_og_title'] ?? '';
		}
		if ( empty( $title ) ) {
			$title = $meta['_wseo_title'] ?? '';
		}

		$description = $meta['_wseo_twitter_description'] ?? '';
		if ( empty( $description ) ) {
			$description = $meta['_wseo_og_description'] ?? '';
		}
		if ( empty( $description ) ) {
			$description = $meta['_wseo_description'] ?? '';
		}

		$image = $meta['_wseo_twitter_image'] ?? '';
		if ( empty( $image ) ) {
			$image = $meta['_wseo_og_image'] ?? '';
		}

		$site = $settings->get( 'social_twitter_site', '' );

		$tags = array();
		$tags['twitter:card'] = $card_type;
		if ( $title ) {
			$tags['twitter:title'] = $title;
		}
		if ( $description ) {
			$tags['twitter:description'] = $description;
		}
		if ( $image ) {
			$tags['twitter:image'] = $image;
		}
		if ( $site ) {
			$tags['twitter:site'] = $site;
		}

		foreach ( $tags as $name => $content ) {
			printf(
				'<meta name="%s" content="%s">' . "\n",
				esc_attr( $name ),
				esc_attr( $content )
			);
		}
	}
}
