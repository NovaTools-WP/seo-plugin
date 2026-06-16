<?php

namespace NovaToolsSEO\Frontend;

use NovaToolsSEO\Admin\Settings;
use NovaToolsSEO\Core\MetaKeys;
use NovaToolsSEO\Core\Tokens;
use NovaToolsSEO\Traits\Base;
use NovaToolsSEO\WooCommerce\ProductOG;
use NovaToolsSEO\WooCommerce\Filters\FilterDetector;
use NovaToolsSEO\WooCommerce\Taxonomy\TaxonomyNoindexEnforcer;

defined( 'ABSPATH' ) || exit;

class HeadOutput {

	use Base;

	private $post_seo_cache = null;
	private $term_seo_cache = null;

	public function init() {
		add_action( 'wp_head', array( $this, 'output' ), 1 );
		add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ), 10 );
		add_filter( 'wp_robots', array( $this, 'filter_robots' ) );
		remove_action( 'wp_head', 'rel_canonical' );
	}

	public function output() {
		$this->output_description();
		$this->output_canonical();
		$this->output_pagination();
		$this->output_opengraph();
		$this->output_twitter();
	}

	private function get_post_seo_data() {
		if ( null !== $this->post_seo_cache ) {
			return $this->post_seo_cache;
		}

		if ( ! is_singular() ) {
			$this->post_seo_cache = array();
			return $this->post_seo_cache;
		}

		$post_id = get_queried_object_id();
		$keys = MetaKeys::POST_SEO;

		$data = array();
		foreach ( $keys as $key ) {
			$data[ $key ] = get_post_meta( $post_id, $key, true );
		}

		$this->post_seo_cache = $data;
		return $this->post_seo_cache;
	}

	private function get_term_seo_data() {
		if ( null !== $this->term_seo_cache ) {
			return $this->term_seo_cache;
		}

		if ( ! ( is_category() || is_tag() || is_tax() ) ) {
			$this->term_seo_cache = array();
			return $this->term_seo_cache;
		}

		$term_id = get_queried_object_id();
		$keys = MetaKeys::TERM_SEO;

		$data = array();
		foreach ( $keys as $key ) {
			$data[ $key ] = get_term_meta( $term_id, $key, true );
		}

		$this->term_seo_cache = $data;
		return $this->term_seo_cache;
	}

	public function filter_document_title( $title ) {
		$seo_title = $this->get_seo_title();
		return $seo_title ?: $title;
	}

	private function get_seo_title() {
		$meta = $this->get_post_seo_data();
		$term_meta = $this->get_term_seo_data();

		$title = '';
		if ( ! empty( $meta['_wseo_title'] ) ) {
			// Per-post SEO title supports tokens (%%title%%, %%sep%%, %%sitename%%, …).
			$title = Tokens::replace( $meta['_wseo_title'] );
		} elseif ( ! empty( $term_meta['_wseo_title'] ) ) {
			$title = Tokens::replace( $term_meta['_wseo_title'] );
		}

		$settings = Settings::get_instance();
		$template = '';

		if ( empty( $title ) ) {
			$post_type = get_post_type();
			if ( $post_type ) {
				$template = $settings->get( "general_{$post_type}_title_template", $settings->get( 'general_title_template', '%%title%% %%sep%% %%sitename%%' ) );
			} else {
				$template = $settings->get( 'general_title_template', '%%title%% %%sep%% %%sitename%%' );
			}

			if ( ( is_archive() || is_search() ) && strpos( $template, '%%title%%' ) === false ) {
				$template = '%%title%% %%sep%% %%sitename%%';
			}

			$title = Tokens::replace( $template );
		}

		if ( is_paged() ) {
			$paged = (int) get_query_var( 'paged', 1 );
			$has_page_token = ! empty( $template ) && strpos( $template, '%%page%%' ) !== false;
			if ( ! $has_page_token ) {
				$page_suffix = sprintf( __( 'Page %d', 'novatools-seo' ), $paged );
				$sep = $settings->get( 'page_suffix_separator', '–' );
				$injected = $sep . ' ' . $page_suffix;

				if ( ! empty( $template ) ) {
					$sep_val  = Tokens::replace( '%%sep%%' );
					$site_val = Tokens::replace( '%%sitename%%' );
					$tail     = $sep_val . ' ' . $site_val;
					$pos      = strrpos( $title, $tail );
					if ( $pos !== false ) {
						$title = substr( $title, 0, $pos ) . ' ' . $injected . ' ' . substr( $title, $pos );
					} else {
						$title .= ' ' . $injected;
					}
				} else {
					$title .= ' ' . $injected;
				}
			}
		}

		return $title;
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

	public function filter_robots( array $robots ) {
		// Faceted URLs → noindex, nofollow.
		$detector = FilterDetector::get_instance();
		if ( $detector->is_faceted_url() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			return $robots;
		}

		// WooCommerce noindexed taxonomies → noindex, nofollow.
		if ( class_exists( 'WooCommerce' ) && ( is_category() || is_tag() || is_tax() ) ) {
			$enforcer = TaxonomyNoindexEnforcer::get_instance();
			if ( $enforcer->is_noindexed_taxonomy() ) {
				$robots['noindex']  = true;
				$robots['nofollow'] = true;
				return $robots;
			}
		}

		// Get robots value from post/term meta or settings.
		$meta      = $this->get_post_seo_data();
		$term_meta = $this->get_term_seo_data();

		$robots_value = '';
		if ( ! empty( $meta['_wseo_robots'] ) ) {
			$robots_value = $meta['_wseo_robots'];
		} elseif ( ! empty( $term_meta['_wseo_robots'] ) ) {
			$robots_value = $term_meta['_wseo_robots'];
		}

		if ( empty( $robots_value ) ) {
			$settings  = Settings::get_instance();
			$post_type = get_post_type();
			if ( $post_type ) {
				$robots_value = $settings->get( "general_{$post_type}_robots_default", $settings->get( 'general_robots_default', 'index,follow' ) );
			} else {
				$robots_value = $settings->get( 'general_robots_default', 'index,follow' );
			}
		}

		// Parse comma-separated directives into wp_robots array format.
		$directives = array_map( 'trim', explode( ',', $robots_value ) );
		foreach ( $directives as $directive ) {
			switch ( $directive ) {
				case 'noindex':
					$robots['noindex'] = true;
					break;
				case 'nofollow':
					$robots['nofollow'] = true;
					break;
				case 'noarchive':
					$robots['noarchive'] = true;
					break;
				case 'index':
					unset( $robots['noindex'] );
					break;
				case 'follow':
					unset( $robots['nofollow'] );
					break;
			}
		}

		return $robots;
	}

	private function output_canonical() {
		$meta = $this->get_post_seo_data();

		$url = '';
		if ( ! empty( $meta['_wseo_canonical'] ) ) {
			$url = $meta['_wseo_canonical'];
		} else {
			$url = $this->get_current_canonical_url();
		}

		$detector = FilterDetector::get_instance();
		if ( $detector->is_faceted_url() && ! empty( $meta['_wseo_canonical'] ) ) {
			$url = $this->strip_filter_params( $url );
		} elseif ( $detector->is_faceted_url() ) {
			$url = $this->strip_filter_params( $this->get_current_canonical_url() );
		}

		if ( ! empty( $url ) ) {
			printf(
				'<link rel="canonical" href="%s">' . "\n",
			 esc_url( $url )
			);
		}
	}

	private function strip_filter_params( $url ) {
		$detector = FilterDetector::get_instance();
		$detected = $detector->get_detected_params();
		if ( empty( $detected ) ) {
			return $url;
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['query'] ) ) {
			return $url;
		}

		parse_str( $parsed['query'], $query_params );
		foreach ( $detected as $param ) {
			unset( $query_params[ $param ] );
		}

		$base = ( isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '' )
			. ( $parsed['host'] ?? '' )
			. ( $parsed['path'] ?? '' );

		if ( ! empty( $query_params ) ) {
			$base .= '?' . http_build_query( $query_params );
		}

		return $base;
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

	private function output_pagination() {
		global $wp_query;
		$paged = (int) get_query_var( 'paged', 1 );
		$max = isset( $wp_query ) ? (int) $wp_query->max_num_pages : 0;

		if ( $max <= 1 ) {
			return;
		}

		if ( $paged > 1 ) {
			printf(
				'<link rel="prev" href="%s">' . "\n",
				esc_url( get_pagenum_link( $paged - 1 ) )
			);
		}

		if ( $paged < $max ) {
			printf(
				'<link rel="next" href="%s">' . "\n",
				esc_url( get_pagenum_link( $paged + 1 ) )
			);
		}
	}

	private function output_opengraph() {
		// WooCommerce product pages: delegate to ProductOG
		if ( class_exists( 'WooCommerce' ) && is_singular( 'product' ) ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( $product ) {
				ProductOG::get_instance()->render( $product );
				return;
			}
		}

		$meta = $this->get_post_seo_data();
		$settings = Settings::get_instance();

		// WooCommerce archive pages
		if ( class_exists( 'WooCommerce' ) && ( is_shop() || is_product_category() || is_product_tag() ) ) {
			$this->output_woo_archive_og( $settings );
			return;
		}

		$title = $meta['_wseo_og_title'] ?? '';
		if ( empty( $title ) ) {
			$title = Tokens::replace( $meta['_wseo_title'] ?? '' );
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
			$title = Tokens::replace( $meta['_wseo_title'] ?? '' );
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

	private function output_woo_archive_og( $settings ) {
		$tags = array();
		$tags['og:type'] = 'website';

		if ( is_shop() ) {
			$page_id = wc_get_page_id( 'shop' );
			$tags['og:title'] = $page_id ? get_the_title( $page_id ) : __( 'Shop', 'novatools-seo' );
			$description = '';
			if ( $page_id ) {
				$shop_post = get_post( $page_id );
				$description = $shop_post ? wp_strip_all_tags( $shop_post->post_excerpt ) : '';
			}
		} elseif ( is_product_category() ) {
			$term = get_queried_object();
			$tags['og:title'] = $term->name ?? '';
			$description = $term->description ?? '';
		} elseif ( is_product_tag() ) {
			$term = get_queried_object();
			$tags['og:title'] = $term->name ?? '';
			$description = $term->description ?? '';
		}

		if ( ! empty( $description ) ) {
			$tags['og:description'] = $description;
		}

		$tags['og:url'] = $this->get_current_canonical_url();
		$tags['og:site_name'] = get_bloginfo( 'name' );
		$tags['og:locale'] = get_locale();

		$image = $settings->get( 'social_og_default_image', '' );
		if ( $image ) {
			$tags['og:image'] = $image;
		}

		foreach ( $tags as $property => $content ) {
			printf(
				'<meta property="%s" content="%s">' . "\n",
				esc_attr( $property ),
				esc_attr( $content )
			);
		}
	}
}
