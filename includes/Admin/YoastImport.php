<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Core\Logger;
use NovaToolsSEO\Traits\Base;

class YoastImport {

	use Base;

	private $meta_mapping = array(
		'_yoast_wpseo_title'          => '_wseo_title',
		'_yoast_wpseo_metadesc'       => '_wseo_description',
		'_yoast_wpseo_canonical'      => '_wseo_canonical',
		'_yoast_wpseo_meta-robots-noindex' => '_wseo_robots',
		'_yoast_wpseo_opengraph-title'     => '_wseo_og_title',
		'_yoast_wpseo_opengraph-description' => '_wseo_og_description',
		'_yoast_wpseo_opengraph-image'     => '_wseo_og_image',
		'_yoast_wpseo_twitter-title'       => '_wseo_twitter_title',
		'_yoast_wpseo_twitter-description' => '_wseo_twitter_description',
		'_yoast_wpseo_twitter-image'       => '_wseo_twitter_image',
	);

	public function start_import() {
		global $wpdb;

		$total = $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key LIKE '_yoast_wpseo_%'"
		);

		if ( empty( $total ) ) {
			return array( 'total' => 0, 'message' => 'No Yoast SEO data found.' );
		}

		set_transient( 'wseo_yoast_import_total', $total, HOUR_IN_SECONDS );
		set_transient( 'wseo_yoast_import_progress', 0, HOUR_IN_SECONDS );

		Logger::log( 'yoast_import', "Started Yoast import for {$total} posts" );

		return array(
			'total'   => (int) $total,
			'message' => "Ready to import {$total} posts.",
		);
	}

	public function process_batch( $offset = 0, $limit = 50 ) {
		global $wpdb;

		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key LIKE '_yoast_wpseo_%%' LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		foreach ( $post_ids as $post_id ) {
			$this->migrate_post_meta( $post_id );
		}

		$progress = (int) get_transient( 'wseo_yoast_import_progress' );
		$progress += count( $post_ids );
		set_transient( 'wseo_yoast_import_progress', $progress, HOUR_IN_SECONDS );

		return array(
			'processed' => count( $post_ids ),
			'progress'  => $progress,
		);
	}

	public function migrate_post_meta( $post_id ) {
		foreach ( $this->meta_mapping as $yoast_key => $wseo_key ) {
			$value = get_post_meta( $post_id, $yoast_key, true );
			if ( ! empty( $value ) ) {
				$mapped_value = $this->map_value( $yoast_key, $value );
				update_post_meta( $post_id, $wseo_key, $mapped_value );
			}
		}
	}

	public function migrate_global_settings() {
		$yoast_titles = get_option( 'wpseo_titles', array() );

		if ( ! empty( $yoast_titles ) && is_array( $yoast_titles ) ) {
			if ( ! empty( $yoast_titles['title-home-wpseo'] ) ) {
				$mapped = $this->convert_yoast_template( $yoast_titles['title-home-wpseo'] );
				update_option( 'wseo_general_title_template', $mapped );
			}

			if ( ! empty( $yoast_titles['title-post'] ) ) {
				$mapped = $this->convert_yoast_template( $yoast_titles['title-post'] );
				update_option( 'wseo_general_post_title_template', $mapped );
			}
		}

		Logger::log( 'yoast_import', 'Migrated Yoast global settings' );
	}

	public function get_progress() {
		$total = get_transient( 'wseo_yoast_import_total' );
		$progress = get_transient( 'wseo_yoast_import_progress' );

		if ( empty( $total ) ) {
			return array( 'total' => 0, 'progress' => 0, 'percentage' => 0 );
		}

		return array(
			'total'      => (int) $total,
			'progress'   => (int) $progress,
			'percentage' => round( ( $progress / $total ) * 100, 1 ),
		);
	}

	private function map_value( $yoast_key, $value ) {
		if ( '_yoast_wpseo_meta-robots-noindex' === $yoast_key ) {
			return '1' === $value ? 'noindex,follow' : 'index,follow';
		}

		return $value;
	}

	private function convert_yoast_template( $template ) {
		$mapping = array(
			'%%title%%'        => '%%title%%',
			'%%sitename%%'     => '%%sitename%%',
			'%%sep%%'          => '%%sep%%',
			'%%category%%'     => '%%category%%',
			'%%page%%'         => '%%page%%',
			'%%primary_category%%' => '%%category%%',
			'%%cf_%%'          => '',
			'%%ct_desc_%%'     => '',
		);

		return str_replace( array_keys( $mapping ), array_values( $mapping ), $template );
	}
}
