<?php

namespace NovaToolsSEO\Sitemaps;

use NovaToolsSEO\Traits\Base;
use NovaToolsSEO\WooCommerce\Taxonomy\TaxonomyNoindexRepository;

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
		add_rewrite_rule( '^sitemap-([a-z_]+)\.xml$', 'index.php?wseo_sitemap=$matches[1]', 'top' );
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
			if ( $this->post_type_has_entries( $post_type ) ) {
				$child_sitemaps[] = $url . 'sitemap-' . $post_type . '.xml';
			}
		}

		$taxonomies = $this->get_sitemap_taxonomies();
		foreach ( $taxonomies as $taxonomy ) {
			if ( $this->taxonomy_has_entries( $taxonomy ) ) {
				$child_sitemaps[] = $url . 'sitemap-' . $taxonomy . '.xml';
			}
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
		$is_product = ( 'product' === $type );

		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
		if ( $is_product ) {
			$xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
		}
		$xml .= '>' . "\n";

		$excluded_ids = $this->get_excluded_ids();

		$post_types = $this->get_sitemap_post_types();
		if ( in_array( $type, $post_types, true ) ) {
			$offset = 0;
			$batch_size = 1000;

			while ( true ) {
				$posts = get_posts( array(
					'post_type'      => $type,
					'posts_per_page' => $batch_size,
					'offset'         => $offset,
					'post_status'    => 'publish',
					'post__not_in'   => $excluded_ids,
				) );

				if ( empty( $posts ) ) {
					break;
				}

				foreach ( $posts as $post ) {
					$product_id = $is_product ? $post->ID : 0;
					$xml .= $this->get_url_entry( get_permalink( $post ), $post->post_modified_gmt, 'weekly', $this->get_priority( $type ), $product_id );
				}

				$offset += $batch_size;
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

	private function get_url_entry( $url, $lastmod, $changefreq, $priority, $product_id = 0 ) {
		$entry = '  <url>' . "\n";
		$entry .= '    <loc>' . esc_url( $url ) . '</loc>' . "\n";
		$entry .= '    <lastmod>' . esc_html( date( 'c', strtotime( $lastmod ) ) ) . '</lastmod>' . "\n";
		$entry .= '    <changefreq>' . esc_html( $changefreq ) . '</changefreq>' . "\n";
		$entry .= '    <priority>' . esc_html( $priority ) . '</priority>' . "\n";

		if ( $product_id > 0 && function_exists( 'wc_get_page_id' ) ) {
			$entry .= $this->get_image_entries( $product_id );
		}

		$entry .= '  </url>' . "\n";

		return $entry;
	}

	private function get_image_entries( $product_id ) {
		$image_ids = array();

		$thumbnail_id = (int) get_post_meta( $product_id, '_thumbnail_id', true );
		if ( $thumbnail_id > 0 ) {
			$image_ids[] = $thumbnail_id;
		}

		$gallery = get_post_meta( $product_id, '_product_image_gallery', true );
		if ( ! empty( $gallery ) ) {
			$gallery_ids = array_filter( array_map( 'absint', explode( ',', $gallery ) ) );
			$image_ids = array_merge( $image_ids, $gallery_ids );
		}

		$product = wc_get_product( $product_id );
		if ( $product && $product->is_type( 'variable' ) ) {
			$variation_ids = array_map( 'absint', $product->get_children() );

			if ( ! empty( $variation_ids ) ) {
				$image_ids = array_merge( $image_ids, $this->get_variation_thumbnail_ids( $variation_ids ) );
			}
		}

		$image_ids = array_unique( $image_ids );

		$xml = '';
		foreach ( $image_ids as $attachment_id ) {
			$xml .= $this->get_image_xml( $attachment_id );
		}

		return $xml;
	}

	private function get_variation_thumbnail_ids( $variation_ids ) {
		global $wpdb;

		$ids   = array_map( 'absint', $variation_ids );
		$in    = implode( ',', $ids );
		$thumbs = $wpdb->get_col( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			"SELECT pm.meta_value FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
			WHERE p.ID IN ({$in})
			AND p.post_status = 'publish'
			AND pm.meta_value > 0"
		);

		return array_map( 'absint', array_filter( $thumbs ) );
	}

	private function get_image_xml( $attachment_id ) {
		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			return '';
		}

		$attachment = get_post( $attachment_id );
		$title = $attachment ? get_the_title( $attachment ) : '';
		$caption = $attachment ? get_the_excerpt( $attachment ) : '';

		$xml = '    <image:image>' . "\n";
		$xml .= '      <image:loc>' . esc_url( $url ) . '</image:loc>' . "\n";
		if ( $title ) {
			$xml .= '      <image:title>' . esc_html( $title ) . '</image:title>' . "\n";
		}
		if ( $caption ) {
			$xml .= '      <image:caption>' . esc_html( $caption ) . '</image:caption>' . "\n";
		}
		$xml .= '    </image:image>' . "\n";

		return $xml;
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
		$taxonomies = get_taxonomies( array( 'public' => true ) );

		if ( class_exists( 'WooCommerce' ) ) {
			$repo = TaxonomyNoindexRepository::get_instance();
			$noindexed = $repo->get_noindexed_taxonomies();
			$taxonomies = array_diff( $taxonomies, $noindexed );
		}

		return array_values( $taxonomies );
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

			$ids = array_merge( $ids, $this->get_outofstock_ids() );
		}

		return $ids;
	}

	private function post_type_has_entries( $post_type ) {
		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		if ( 'product' === $post_type ) {
			$args['post__not_in'] = $this->get_excluded_ids();
		}

		return get_posts( $args ) ? true : false;
	}

	private function taxonomy_has_entries( $taxonomy ) {
		$count = wp_count_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		) );

		return $count > 0;
	}

	private function get_outofstock_ids() {
		global $wpdb;

		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return array();
		}

		$threshold = (int) get_option( 'wseo_outofstock_threshold', 30 );

		$sql = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} ms ON p.ID = ms.post_id
			AND ms.meta_key = '_stock_status' AND ms.meta_value = 'outofstock'
			INNER JOIN {$wpdb->postmeta} mt ON p.ID = mt.post_id
			AND mt.meta_key = '_wseo_outofstock_since'
			WHERE p.post_type = 'product' AND p.post_status = 'publish'";

		if ( $threshold > 0 ) {
			$cutoff = time() - ( $threshold * DAY_IN_SECONDS );
			$sql .= $wpdb->prepare( ' AND mt.meta_value < %d', $cutoff );
		}

		$ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map( 'absint', $ids );
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
