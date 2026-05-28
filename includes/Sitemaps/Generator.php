<?php

namespace NovaToolsSEO\Sitemaps;

use NovaToolsSEO\Traits\Base;

class Generator {

	use Base;

	public function init() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_sitemap_request' ) );
		add_action( 'wseo_sitemap_rebuild', array( $this, 'rebuild_all' ) );
		add_action( 'save_post', array( $this, 'schedule_rebuild' ) );
		add_action( 'delete_post', array( $this, 'schedule_rebuild' ) );
		add_action( 'created_term', array( $this, 'schedule_rebuild' ) );
		add_action( 'edited_term', array( $this, 'schedule_rebuild' ) );
		add_action( 'delete_term', array( $this, 'schedule_rebuild' ) );
	}

	public function add_rewrite_rules() {
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?wseo_sitemap=index', 'top' );
		add_rewrite_rule( '^sitemap-([a-z]+)\.xml$', 'index.php?wseo_sitemap=$matches[1]', 'top' );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'wseo_sitemap';
		return $vars;
	}

	public function handle_sitemap_request() {
		$sitemap = get_query_var( 'wseo_sitemap' );
		if ( empty( $sitemap ) ) {
			return;
		}

		header( 'Content-Type: application/xml; charset=utf-8' );

		if ( 'index' === $sitemap ) {
			echo $this->get_index_xml();
		} else {
			echo $this->get_child_sitemap_xml( $sitemap );
		}

		exit;
	}

	public function rebuild_all() {
		$upload_dir = wp_upload_dir();
		$sitemap_dir = $upload_dir['basedir'] . '/wseo-sitemaps';

		if ( ! file_exists( $sitemap_dir ) ) {
			wp_mkdir_p( $sitemap_dir );
		}

		$this->build_index( $sitemap_dir );

		$post_types = $this->get_sitemap_post_types();
		foreach ( $post_types as $post_type ) {
			$this->build_post_type_sitemap( $sitemap_dir, $post_type );
		}

		$taxonomies = $this->get_sitemap_taxonomies();
		foreach ( $taxonomies as $taxonomy ) {
			$this->build_taxonomy_sitemap( $sitemap_dir, $taxonomy );
		}
	}

	public function schedule_rebuild( $id = 0 ) {
		if ( ! wp_next_scheduled( 'wseo_sitemap_rebuild' ) ) {
			wp_schedule_single_event( time() + 60, 'wseo_sitemap_rebuild' );
		}
	}

	public function rebuild_manually() {
		$this->rebuild_all();
	}

	private function get_index_xml() {
		$child_sitemaps = array();
		$url = home_url( '/' );

		$post_types = $this->get_sitemap_post_types();
		foreach ( $post_types as $post_type ) {
			$child_sitemaps[] = $url . 'sitemap-' . $post_type . '.xml';
		}

		$taxonomies = $this->get_sitemap_taxonomies();
		foreach ( $taxonomies as $taxonomy ) {
			$child_sitemaps[] = $url . 'sitemap-' . $taxonomy . '.xml';
		}

		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $child_sitemaps as $loc ) {
			$xml .= '  <sitemap>' . "\n";
			$xml .= '    <loc>' . esc_url( $loc ) . '</loc>' . "\n";
			$xml .= '    <lastmod>' . date( 'c' ) . '</lastmod>' . "\n";
			$xml .= '  </sitemap>' . "\n";
		}

		$xml .= '</sitemapindex>';

		return $xml;
	}

	private function get_child_sitemap_xml( $type ) {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		$excluded_ids = $this->get_excluded_ids();

		$post_types = $this->get_sitemap_post_types();
		if ( in_array( $type, $post_types, true ) ) {
			$posts = get_posts( array(
				'post_type'      => $type,
				'posts_per_page' => 1000,
				'post_status'    => 'publish',
				'post__not_in'   => $excluded_ids,
			) );

			foreach ( $posts as $post ) {
				$xml .= $this->get_url_entry( get_permalink( $post ), $post->post_modified_gmt, 'weekly', $this->get_priority( $type ) );
			}
		}

		$taxonomies = $this->get_sitemap_taxonomies();
		if ( in_array( $type, $taxonomies, true ) ) {
			$terms = get_terms( array(
				'taxonomy'   => $type,
				'hide_empty' => true,
			) );

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$xml .= $this->get_url_entry( get_term_link( $term ), date( 'c' ), 'weekly', '0.6' );
				}
			}
		}

		$xml .= '</urlset>';

		return $xml;
	}

	private function get_url_entry( $url, $lastmod, $changefreq, $priority ) {
		$entry = '  <url>' . "\n";
		$entry .= '    <loc>' . esc_url( $url ) . '</loc>' . "\n";
		$entry .= '    <lastmod>' . esc_html( date( 'c', strtotime( $lastmod ) ) ) . '</lastmod>' . "\n";
		$entry .= '    <changefreq>' . esc_html( $changefreq ) . '</changefreq>' . "\n";
		$entry .= '    <priority>' . esc_html( $priority ) . '</priority>' . "\n";
		$entry .= '  </url>' . "\n";

		return $entry;
	}

	private function get_priority( $post_type ) {
		$priorities = array(
			'page'    => '1.0',
			'post'    => '0.8',
			'product' => '0.8',
		);

		return $priorities[ $post_type ] ?? '0.6';
	}

	private function get_sitemap_post_types() {
		$types = get_post_types( array( 'public' => true ) );
		unset( $types['attachment'] );
		return array_values( $types );
	}

	private function get_sitemap_taxonomies() {
		return get_taxonomies( array( 'public' => true ) );
	}

	private function get_excluded_ids() {
		$ids = array();

		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
				$page_id = wc_get_page_id( $page );
				if ( $page_id > 0 ) {
					$ids[] = $page_id;
				}
			}
		}

		return $ids;
	}

	private function build_index( $dir ) {
		$xml = $this->get_index_xml();
		file_put_contents( $dir . '/sitemap.xml', $xml );
	}

	private function build_post_type_sitemap( $dir, $post_type ) {
		$xml = $this->get_child_sitemap_xml( $post_type );
		file_put_contents( $dir . '/sitemap-' . $post_type . '.xml', $xml );
	}

	private function build_taxonomy_sitemap( $dir, $taxonomy ) {
		$xml = $this->get_child_sitemap_xml( $taxonomy );
		file_put_contents( $dir . '/sitemap-' . $taxonomy . '.xml', $xml );
	}
}
