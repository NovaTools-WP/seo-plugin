<?php

namespace NovaToolsSEO\Database\Migrations;

class Redirects {

	public static function up() {
		global $wpdb;
		$table = $wpdb->prefix . 'wseo_redirects';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_url varchar(500) NOT NULL,
			destination_url varchar(500) NOT NULL,
			status_code smallint(3) unsigned NOT NULL DEFAULT 301,
			is_regex tinyint(1) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY source_url (source_url(191))
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function down() {
		global $wpdb;
		// Sanitize prefix to prevent SQL injection via table name.
		$prefix = preg_replace( '/[^a-z0-9_]/i', '', $wpdb->prefix );
		$wpdb->query( "DROP TABLE IF EXISTS {$prefix}wseo_redirects" );
	}
}
