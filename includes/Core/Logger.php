<?php

namespace NovaToolsSEO\Core;

defined( 'ABSPATH' ) || exit;

class Logger {

	public static function log( $type, $message, $context = null ) {
		global $wpdb;
		$table = self::table_name();

		$data = array(
			'type'    => sanitize_text_field( $type ),
			'message' => sanitize_text_field( $message ),
		);

		if ( $context !== null ) {
			$data['context'] = wp_json_encode( $context );
		}

		$wpdb->insert( $table, $data );
	}

	public static function get_logs( $type = '', $limit = 200, $offset = 0 ) {
		global $wpdb;
		$table = self::table_name();

		if ( ! empty( $type ) ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE type = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$type,
					$limit,
					$offset
				),
				ARRAY_A
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	public static function export_csv() {
		$logs = self::get_logs( '', 9999 );

		if ( empty( $logs ) ) {
			return '';
		}

		$csv = "ID,Date,Type,Message,Context\n";
		foreach ( $logs as $log ) {
			$csv .= sprintf(
				"%s,%s,%s,%s,%s\n",
				$log['id'],
				self::escape_csv_cell( $log['created_at'] ),
				self::escape_csv_cell( $log['type'] ),
				self::escape_csv_cell( $log['message'] ),
				self::escape_csv_cell( $log['context'] ?? '' )
			);
		}

		return $csv;
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wseo_logs';
	}

	public static function cleanup( $days = null ) {
		if ( $days === null ) {
			$days = (int) get_option( 'wseo_log_retention_days', 30 );
		}

		if ( $days < 1 ) {
			return;
		}

		global $wpdb;
		$table = self::table_name();
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM `{$table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days
		) );
	}

	private static function escape_csv_cell( $value ) {
		$value = str_replace( '"', '""', $value );
		if ( strlen( $value ) > 0 && in_array( $value[0], [ '=', '+', '-', '@', "\t" ], true ) ) {
			$value = "\t" . $value;
		}
		return $value;
	}
}
