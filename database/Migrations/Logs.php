<?php

namespace NovaToolsSEO\Database\Migrations;

class Logs {

	public static function up() {
		global $wpdb;
		$table = $wpdb->prefix . 'wseo_logs';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			type varchar(50) NOT NULL DEFAULT 'general',
			message text NOT NULL,
			context longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY type (type),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function down() {
		global $wpdb;
		// Sanitize prefix to prevent SQL injection via table name.
		$prefix = preg_replace( '/[^a-z0-9_]/i', '', $wpdb->prefix );
		$wpdb->query( "DROP TABLE IF EXISTS {$prefix}wseo_logs" );
	}
}
