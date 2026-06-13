<?php

namespace NovaToolsSEO\Redirects;

use NovaToolsSEO\Core\Logger;
use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

class FourOhFourLogger {

	use Base;

	public function init() {
		add_action( 'template_redirect', array( $this, 'log_404' ) );
	}

	public function log_404() {
		if ( ! is_404() ) {
			return;
		}

		if ( is_admin() || defined( 'DOING_CRON' ) || defined( 'DOING_AJAX' ) ) {
			return;
		}

		$request_uri = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_PATH );
		if ( empty( $request_uri ) || '/' === $request_uri ) {
			return;
		}

		if ( $this->is_bot_request() ) {
			return;
		}

		if ( $this->is_asset_request( $request_uri ) ) {
			return;
		}

		$existing = $this->find_existing_log( $request_uri );
		if ( $existing ) {
			$this->increment_hit_count( $existing['id'], $existing['hit_count'] );
		} else {
			Logger::log( '404', $request_uri, array(
				'referer'   => isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				'user_ip'   => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'hit_count' => 1,
			) );
		}
	}

	private function find_existing_log( $request_uri ) {
		global $wpdb;
		$table = Logger::table_name();

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, context FROM `{$table}` WHERE type = '404' AND message = %s ORDER BY created_at DESC LIMIT 1",
				$request_uri
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return null;
		}

		$context = json_decode( $existing['context'] ?? '{}', true );
		$hit_count = isset( $context['hit_count'] ) ? (int) $context['hit_count'] : 1;

		return array(
			'id'        => $existing['id'],
			'hit_count' => $hit_count,
		);
	}

	private function increment_hit_count( $log_id, $current_count ) {
		global $wpdb;
		$table = Logger::table_name();

		$new_count = $current_count + 1;

		$existing_row = $wpdb->get_row(
			$wpdb->prepare( "SELECT context FROM `{$table}` WHERE id = %d", $log_id ),
			ARRAY_A
		);

		$context = json_decode( $existing_row['context'] ?? '{}', true );
		$context['hit_count'] = $new_count;

		$wpdb->update(
			$table,
			array(
				'context'    => wp_json_encode( $context ),
				'created_at' => current_time( 'mysql' ),
			),
			array( 'id' => $log_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	private function is_bot_request() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( empty( $user_agent ) ) {
			return true;
		}

		$bots = array( 'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'facebookexternalhit', 'googlebot', 'bingbot', 'yandexbot', 'baiduspider', 'duckduckbot', 'semrushbot', 'ahrefsbot', 'dotbot' );
		$lower_agent = strtolower( $user_agent );

		foreach ( $bots as $bot ) {
			if ( strpos( $lower_agent, $bot ) !== false ) {
				return true;
			}
		}

		return false;
	}

	private function is_asset_request( $path ) {
		$extensions = array( '.js', '.css', '.map', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf', '.eot', '.webp', '.avif' );
		$lower_path = strtolower( $path );

		foreach ( $extensions as $ext ) {
			if ( substr( $lower_path, -strlen( $ext ) ) === $ext ) {
				return true;
			}
		}

		return false;
	}

	public static function get_aggregated_logs( $limit = 100, $offset = 0 ) {
		global $wpdb;
		$table = Logger::table_name();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, message AS url, context, created_at
				FROM `{$table}`
				WHERE type = '404'
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	public static function delete_log( $id ) {
		global $wpdb;
		$table = Logger::table_name();
		return $wpdb->delete( $table, array( 'id' => absint( $id ) ) );
	}

	public static function clear_all_logs() {
		global $wpdb;
		$table = Logger::table_name();
		return $wpdb->query( "DELETE FROM `{$table}` WHERE type = '404'" );
	}

	public static function get_suggestions( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( empty( $path ) ) {
			return array();
		}

		$path = trailingslashit( $path );
		$suggestions = array();

		$post_suggestions = self::get_post_suggestions( $path );
		$redirect_suggestions = self::get_redirect_suggestions( $path );

		$suggestions = array_merge( $post_suggestions, $redirect_suggestions );

		usort( $suggestions, function ( $a, $b ) {
			return $b['score'] - $a['score'];
		} );

		return array_slice( $suggestions, 0, 5 );
	}

	private static function get_post_suggestions( $path ) {
		$posts = get_posts( array(
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
		) );

		$suggestions = array();
		$path_segments = explode( '/', trim( $path, '/' ) );

		foreach ( $posts as $post_id ) {
			$permalink = get_permalink( $post_id );
			if ( ! $permalink ) {
				continue;
			}

			$post_path = wp_parse_url( $permalink, PHP_URL_PATH );
			if ( empty( $post_path ) ) {
				continue;
			}

			$score = self::calculate_similarity( $path, $post_path );

			if ( $score > 30 ) {
				$post_obj = get_post( $post_id );
				$suggestions[] = array(
					'url'         => $post_path,
					'title'       => $post_obj ? $post_obj->post_title : '',
					'type'        => 'post',
					'score'       => round( $score ),
					'post_id'     => $post_id,
				);
			}
		}

		return $suggestions;
	}

	private static function get_redirect_suggestions( $path ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wseo_redirects';

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $table_exists ) {
			return array();
		}

		$redirects = $wpdb->get_results(
			"SELECT source_url, destination_url FROM `{$table}` WHERE is_regex = 0",
			ARRAY_A
		);

		$suggestions = array();

		foreach ( $redirects as $redirect ) {
			$score = self::calculate_similarity( $path, $redirect['source_url'] );

			if ( $score > 30 ) {
				$suggestions[] = array(
					'url'         => $redirect['destination_url'],
					'title'       => '',
					'type'        => 'redirect',
					'score'       => round( $score ),
					'source_url'  => $redirect['source_url'],
				);
			}
		}

		return $suggestions;
	}

	private static function calculate_similarity( $path1, $path2 ) {
		$norm1 = strtolower( trailingslashit( $path1 ) );
		$norm2 = strtolower( trailingslashit( $path2 ) );

		if ( $norm1 === $norm2 ) {
			return 100;
		}

		$seg1 = explode( '/', trim( $norm1, '/' ) );
		$seg2 = explode( '/', trim( $norm2, '/' ) );

		$last1 = end( $seg1 );
		$last2 = end( $seg2 );

		$slug_score = 0;
		if ( $last1 && $last2 ) {
			similar_text( $last1, $last2, $slug_pct );
			$slug_score = $slug_pct;
		}

		similar_text( $norm1, $norm2, $full_pct );

		$lev = levenshtein( $norm1, $norm2 );
		$max_len = max( strlen( $norm1 ), strlen( $norm2 ) );
		$lev_score = $max_len > 0 ? max( 0, 100 - ( $lev / $max_len * 100 ) ) : 0;

		$common_seg = count( array_intersect( $seg1, $seg2 ) );
		$total_seg = max( count( $seg1 ), count( $seg2 ) );
		$seg_score = $total_seg > 0 ? ( $common_seg / $total_seg * 100 ) : 0;

		return ( $slug_score * 0.4 ) + ( $full_pct * 0.2 ) + ( $lev_score * 0.2 ) + ( $seg_score * 0.2 );
	}
}
