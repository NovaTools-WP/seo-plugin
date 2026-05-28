<?php

namespace NovaToolsSEO\Core;

class Logger {

	public static function log( $type, $message, $context = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'wseo_logs';

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
		$table = $wpdb->prefix . 'wseo_logs';

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
				$log['created_at'],
				$log['type'],
				str_replace( '"', '""', $log['message'] ),
				str_replace( '"', '""', $log['context'] ?? '' )
			);
		}

		return $csv;
	}
}
