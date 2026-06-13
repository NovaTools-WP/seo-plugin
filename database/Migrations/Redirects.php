<?php

namespace NovaToolsSEO\Database\Migrations;

class Redirects {

	const DB_VERSION = '1.1.0';
	const DB_VERSION_OPTION = 'wseo_db_version';

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

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public static function migrate() {
		global $wpdb;
		$installed_version = get_option( self::DB_VERSION_OPTION, '1.0.0' );

		if ( version_compare( $installed_version, self::DB_VERSION, '>=' ) ) {
			return;
		}

		$table = $wpdb->prefix . 'wseo_redirects';

		if ( ! self::table_exists( $table ) ) {
			return;
		}

		$columns = self::get_column_names( $table );

		if ( version_compare( $installed_version, '1.1.0', '<' ) ) {
			if ( ! in_array( 'created_at', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER id" );
			}
			if ( ! in_array( 'updated_at', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at" );
			}
			if ( ! in_array( 'enabled', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN enabled tinyint(1) unsigned NOT NULL DEFAULT 1 AFTER is_regex" );
			}
			if ( ! self::index_exists( $table, 'enabled' ) ) {
				$wpdb->query( "ALTER TABLE `{$table}` ADD KEY enabled (enabled)" );
			}
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public static function validate_schema() {
		global $wpdb;
		$installed_version = get_option( self::DB_VERSION_OPTION, '1.0.0' );

		if ( version_compare( $installed_version, self::DB_VERSION, '>=' ) ) {
			return true;
		}

		$table = $wpdb->prefix . 'wseo_redirects';

		if ( ! self::table_exists( $table ) ) {
			return false;
		}

		$columns = self::get_column_names( $table );
		$required = array( 'id', 'source_url', 'destination_url', 'status_code', 'is_regex' );

		foreach ( $required as $col ) {
			if ( ! in_array( $col, $columns, true ) ) {
				return false;
			}
		}

		return true;
	}

	public static function down() {
		global $wpdb;
		// Sanitize prefix to prevent SQL injection via table name.
		$prefix = preg_replace( '/[^a-z0-9_]/i', '', $wpdb->prefix );
		$wpdb->query( "DROP TABLE IF EXISTS {$prefix}wseo_redirects" );
		delete_option( self::DB_VERSION_OPTION );
	}

	private static function table_exists( $table ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	private static function get_column_names( $table ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
		if ( empty( $results ) ) {
			return array();
		}
		return array_column( $results, 'Field' );
	}

	private static function index_exists( $table, $index_name ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A );
		if ( empty( $results ) ) {
			return false;
		}
		foreach ( $results as $row ) {
			if ( $row['Key_name'] === $index_name ) {
				return true;
			}
		}
		return false;
	}
}
