<?php

namespace NovaToolsSEO\Sitemaps;

use NovaToolsSEO\Admin\Cornerstone;
use NovaToolsSEO\Admin\Settings;
use NovaToolsSEO\Core\Logger;
use NovaToolsSEO\Traits\Base;
use NovaToolsSEO\WooCommerce\Taxonomy\TaxonomyNoindexRepository;

defined( 'ABSPATH' ) || exit;

class Generator {

	use Base;

	/**
	 * Cached video-sitemap entries (id + data).
	 *
	 * @var array|null
	 */
	private $video_posts;

	/**
	 * Cached cornerstone post IDs (or empty array).
	 *
	 * @var int[]|null
	 */
	private $cornerstone_ids;

	public function init() {
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_sitemap_request' ), 0 );
		add_action( 'wseo_sitemap_rebuild', array( $this, 'rebuild_all' ) );
		add_action( 'save_post', array( $this, 'schedule_rebuild' ) );
		add_action( 'delete_post', array( $this, 'schedule_rebuild' ) );
		add_action( 'created_term', array( $this, 'schedule_rebuild' ) );
		add_action( 'edited_term', array( $this, 'schedule_rebuild' ) );
		add_action( 'delete_term', array( $this, 'schedule_rebuild' ) );

		// Disable WP core sitemaps (wp-sitemap.xml) while our own sitemap is
		// enabled, to avoid serving two competing sitemap indexes.
		add_filter( 'wp_sitemaps_enabled', array( $this, 'maybe_disable_core_sitemaps' ) );
	}

	/**
	 * Disable WordPress core sitemaps when this plugin's sitemap is enabled.
	 *
	 * @param bool $enabled Whether core sitemaps are enabled.
	 * @return bool
	 */
	public function maybe_disable_core_sitemaps( $enabled ) {
		if ( '1' === Settings::get_instance()->get( 'sitemap_enabled', '1' ) ) {
			return false;
		}

		return $enabled;
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
		// Respect the master sitemap toggle. When disabled, do not serve our
		// sitemap (lets core sitemaps fall back via maybe_disable_core_sitemaps).
		if ( '1' !== Settings::get_instance()->get( 'sitemap_enabled', '1' ) ) {
			return;
		}

		$sitemap = get_query_var( 'wseo_sitemap' );
		if ( empty( $sitemap ) ) {
			return;
		}

		$file = $this->get_sitemap_file_path( $sitemap );

		if ( ! file_exists( $file ) ) {
			$this->regenerate_sitemap_file( $sitemap );
		}

		if ( file_exists( $file ) ) {
			header( 'Content-Type: application/xml; charset=utf-8' );
			readfile( $file );
			exit;
		}

		$this->serve_dynamic_sitemap( $sitemap );
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

		if ( $this->video_has_entries() ) {
			$this->build_video_sitemap( $sitemap_dir );
		}

		if ( $this->cornerstone_has_entries() ) {
			$this->build_cornerstone_sitemap( $sitemap_dir );
		}

		$this->ping_search_engines();
	}

	public function schedule_rebuild( $id = 0 ) {
		if ( ! wp_next_scheduled( 'wseo_sitemap_rebuild' ) ) {
			wp_schedule_single_event( time() + 60, 'wseo_sitemap_rebuild' );
		}
	}

	public function rebuild_manually() {
		$this->rebuild_all();
	}

	private function get_sitemap_file_path( $sitemap ) {
		$upload_dir = wp_upload_dir();
		$sitemap_dir = $upload_dir['basedir'] . '/wseo-sitemaps';

		if ( 'index' === $sitemap ) {
			return $sitemap_dir . '/sitemap.xml';
		}

		return $sitemap_dir . '/sitemap-' . $sitemap . '.xml';
	}

	private function regenerate_sitemap_file( $sitemap ) {
		$upload_dir = wp_upload_dir();
		$sitemap_dir = $upload_dir['basedir'] . '/wseo-sitemaps';

		if ( ! file_exists( $sitemap_dir ) ) {
			wp_mkdir_p( $sitemap_dir );
		}

		if ( 'index' === $sitemap ) {
			$this->build_index( $sitemap_dir );
			return;
		}

		if ( 'video' === $sitemap ) {
			file_put_contents( $sitemap_dir . '/sitemap-video.xml', $this->get_video_sitemap_xml() );
			return;
		}

		if ( 'cornerstone' === $sitemap ) {
			file_put_contents( $sitemap_dir . '/sitemap-cornerstone.xml', $this->get_cornerstone_sitemap_xml() );
			return;
		}

		$post_types = $this->get_sitemap_post_types();
		if ( in_array( $sitemap, $post_types, true ) ) {
			$this->build_post_type_sitemap( $sitemap_dir, $sitemap );
			return;
		}

		$taxonomies = $this->get_sitemap_taxonomies();
		if ( in_array( $sitemap, $taxonomies, true ) ) {
			$this->build_taxonomy_sitemap( $sitemap_dir, $sitemap );
		}
	}

	private function serve_dynamic_sitemap( $sitemap ) {
		header( 'Content-Type: application/xml; charset=utf-8' );

		if ( 'index' === $sitemap ) {
			echo $this->get_index_xml();
		} else {
			echo $this->get_child_sitemap_xml( $sitemap );
		}

		exit;
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

		// Video sitemap (posts with a VideoObject in _wseo_schema).
		if ( $this->video_has_entries() ) {
			$child_sitemaps[] = $url . 'sitemap-video.xml';
		}

		// Cornerstone sitemap (posts flagged _wseo_cornerstone).
		if ( $this->cornerstone_has_entries() ) {
			$child_sitemaps[] = $url . 'sitemap-cornerstone.xml';
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
		if ( 'video' === $type ) {
			return $this->get_video_sitemap_xml();
		}

		if ( 'cornerstone' === $type ) {
			return $this->get_cornerstone_sitemap_xml();
		}

		$is_product     = ( 'product' === $type );
		$include_images = $is_product && '1' === Settings::get_instance()->get( 'sitemap_product_images', '1' );

		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
		if ( $include_images ) {
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
					$product_id = $include_images ? $post->ID : 0;
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

	/**
	 * Collect published posts that have a usable VideoObject in `_wseo_schema`.
	 *
	 * A "usable" video needs at least a title and a thumbnail (the minimum the
	 * sitemap <video:video> entry emits). Results are cached per instance.
	 *
	 * @return array Array of [ 'id' => int, 'data' => array ].
	 */
	private function get_video_posts() {
		if ( isset( $this->video_posts ) ) {
			return $this->video_posts;
		}

		$post_types = $this->get_sitemap_post_types();
		if ( empty( $post_types ) ) {
			$this->video_posts = array();
			return $this->video_posts;
		}

		$query = new \WP_Query( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 5000,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => '_wseo_schema',
					'value'   => '"video"',
					'compare' => 'LIKE',
				),
			),
		) );

		$videos = array();
		foreach ( $query->posts as $pid ) {
			$schema = get_post_meta( $pid, '_wseo_schema', true );
			if ( ! is_array( $schema ) || empty( $schema['video'] ) || ! is_array( $schema['video'] ) ) {
				continue;
			}
			$v     = $schema['video'];
			$name  = trim( (string) ( $v['name'] ?? '' ) );
			$thumb = trim( (string) ( $v['thumbnail_url'] ?? '' ) );
			if ( '' === $name || '' === $thumb ) {
				continue;
			}
			$videos[] = array(
				'id'   => $pid,
				'data' => $v,
			);
		}

		$this->video_posts = $videos;
		return $this->video_posts;
	}

	/**
	 * Whether any publishable video entries exist.
	 *
	 * @return bool
	 */
	private function video_has_entries() {
		return ! empty( $this->get_video_posts() );
	}

	/**
	 * Build the video sitemap XML.
	 *
	 * @return string
	 */
	private function get_video_sitemap_xml() {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

		foreach ( $this->get_video_posts() as $entry ) {
			$pid = $entry['id'];
			$v   = $entry['data'];

			$xml .= "  <url>\n";
			$xml .= '    <loc>' . esc_url( get_permalink( $pid ) ) . '</loc>' . "\n";
			$xml .= "    <video:video>\n";

			$xml .= '      <video:title>' . esc_html( trim( (string) ( $v['name'] ?? '' ) ) ) . '</video:title>' . "\n";

			$desc = trim( (string) ( $v['description'] ?? '' ) );
			if ( '' !== $desc ) {
				$xml .= '      <video:description>' . esc_html( $desc ) . '</video:description>' . "\n";
			}

			$xml .= '      <video:thumbnail_loc>' . esc_url( trim( (string) ( $v['thumbnail_url'] ?? '' ) ) ) . '</video:thumbnail_loc>' . "\n";

			if ( ! empty( $v['content_url'] ) ) {
				$xml .= '      <video:content_loc>' . esc_url( trim( (string) $v['content_url'] ) ) . '</video:content_loc>' . "\n";
			}
			if ( ! empty( $v['embed_url'] ) ) {
				$xml .= '      <video:player_loc>' . esc_url( trim( (string) $v['embed_url'] ) ) . '</video:player_loc>' . "\n";
			}
			if ( ! empty( $v['duration'] ) ) {
				$secs = $this->iso8601_duration_to_seconds( $v['duration'] );
				if ( $secs > 0 ) {
					$xml .= '      <video:duration>' . intval( $secs ) . '</video:duration>' . "\n";
				}
			}
			if ( ! empty( $v['upload_date'] ) ) {
				$xml .= '      <video:publication_date>' . esc_html( $v['upload_date'] ) . '</video:publication_date>' . "\n";
			}

			$xml .= "    </video:video>\n";
			$xml .= "  </url>\n";
		}

		$xml .= '</urlset>';
		return $xml;
	}

	/**
	 * Write the video sitemap file to disk.
	 *
	 * @param string $dir Sitemap directory.
	 * @return void
	 */
	private function build_video_sitemap( $dir ) {
		file_put_contents( $dir . '/sitemap-video.xml', $this->get_video_sitemap_xml() );
	}

	/**
	 * Collect published post IDs flagged as cornerstone.
	 *
	 * @return int[] Cached on the instance.
	 */
	private function get_cornerstone_posts() {
		if ( isset( $this->cornerstone_ids ) ) {
			return $this->cornerstone_ids;
		}

		$post_types = $this->get_sitemap_post_types();
		if ( empty( $post_types ) ) {
			$this->cornerstone_ids = array();
			return $this->cornerstone_ids;
		}

		$query = new \WP_Query( array(
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => 5000,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'   => Cornerstone::META_KEY,
					'value' => '1',
				),
			),
		) );

		$this->cornerstone_ids = array_map( 'absint', $query->posts );
		return $this->cornerstone_ids;
	}

	/**
	 * Whether any cornerstone posts exist.
	 *
	 * @return bool
	 */
	private function cornerstone_has_entries() {
		return ! empty( $this->get_cornerstone_posts() );
	}

	/**
	 * Build the cornerstone sitemap XML.
	 *
	 * @return string
	 */
	private function get_cornerstone_sitemap_xml() {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $this->get_cornerstone_posts() as $pid ) {
			$post = get_post( $pid );
			if ( ! $post ) {
				continue;
			}
			$xml .= $this->get_url_entry( get_permalink( $pid ), $post->post_modified_gmt, 'weekly', '1.0' );
		}

		$xml .= '</urlset>';
		return $xml;
	}

	/**
	 * Write the cornerstone sitemap file to disk.
	 *
	 * @param string $dir Sitemap directory.
	 * @return void
	 */
	private function build_cornerstone_sitemap( $dir ) {
		file_put_contents( $dir . '/sitemap-cornerstone.xml', $this->get_cornerstone_sitemap_xml() );
	}

	/**
	 * Convert an ISO 8601 duration (e.g. PT1M30S) to whole seconds.
	 *
	 * Only the time components (H/M/S) and days are counted — video durations
	 * never meaningfully use years/months, and the M-before-T token (months)
	 * is ignored to avoid ambiguity with minutes.
	 *
	 * @param string $duration ISO 8601 duration.
	 * @return int
	 */
	private function iso8601_duration_to_seconds( $duration ) {
		if ( ! preg_match( '/^P(\d+Y)?(\d+M)?(\d+D)?(?:T(\d+H)?(\d+M)?(\d+S)?)?$/', (string) $duration, $m ) ) {
			return 0;
		}

		$days    = isset( $m[3] ) ? (int) $m[3] : 0;
		$hours   = isset( $m[4] ) ? (int) $m[4] : 0;
		$minutes = isset( $m[5] ) ? (int) $m[5] : 0;
		$seconds = isset( $m[6] ) ? (int) $m[6] : 0;

		return $days * 86400 + $hours * 3600 + $minutes * 60 + $seconds;
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

		// When cornerstone posts have a dedicated sitemap, keep them out of the
		// per-type sitemaps so they aren't listed twice.
		$settings = Settings::get_instance();
		if ( $settings->get( 'cornerstone_separate_sitemap', '' ) === '1' && $this->cornerstone_has_entries() ) {
			$ids = array_merge( $ids, $this->get_cornerstone_posts() );
		}

		return $ids;
	}

	private function post_type_has_entries( $post_type ) {
		// Apply the same exclusions used when building a child sitemap (e.g.
		// WooCommerce cart/checkout/out-of-stock, and cornerstone posts when
		// they have a dedicated sitemap) so an index entry isn't emitted for a
		// child sitemap that would be empty.
		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'post__not_in'   => $this->get_excluded_ids(),
		);

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

	private function ping_search_engines() {
		$settings = Settings::get_instance();
		if ( $settings->get( 'sitemap_ping_enabled', '1' ) !== '1' ) {
			return;
		}

		$sitemap_url = home_url( '/sitemap.xml' );
		$pings = array(
			'https://www.google.com/ping?sitemap=' . urlencode( $sitemap_url ),
			'https://www.bing.com/indexnow?url=' . urlencode( home_url( '/' ) ) . '&sitemap=' . urlencode( $sitemap_url ),
		);

		foreach ( $pings as $ping_url ) {
			$response = wp_remote_get( $ping_url, array(
				'timeout'  => 5,
				'blocking' => false,
			) );

			if ( is_wp_error( $response ) ) {
				Logger::log( 'sitemap_ping', 'Ping failed: ' . $ping_url, array(
					'error' => $response->get_error_message(),
				) );
			}
		}

		Logger::log( 'sitemap_ping', 'Search engines pinged', array(
			'sitemap_url' => $sitemap_url,
		) );
	}
}
