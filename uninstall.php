<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wseo_redirects" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wseo_logs" );

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		'wseo_general_%',
		'wseo_sitemap_%',
		'wseo_social_%',
		'wseo_license_%',
		'wseo_gmc_%'
	)
);

delete_post_meta_by_key( '_wseo_title' );
delete_post_meta_by_key( '_wseo_description' );
delete_post_meta_by_key( '_wseo_canonical' );
delete_post_meta_by_key( '_wseo_robots' );
delete_post_meta_by_key( '_wseo_og_title' );
delete_post_meta_by_key( '_wseo_og_description' );
delete_post_meta_by_key( '_wseo_og_image' );
delete_post_meta_by_key( '_wseo_twitter_card' );
delete_post_meta_by_key( '_wseo_twitter_title' );
delete_post_meta_by_key( '_wseo_twitter_description' );
delete_post_meta_by_key( '_wseo_twitter_image' );

wp_clear_scheduled_hook( 'wseo_sitemap_rebuild' );
wp_clear_scheduled_hook( 'wseo_license_check' );
wp_clear_scheduled_hook( 'wseo_yoast_import' );
wp_clear_scheduled_hook( 'wseo_gmc_push_product' );
wp_clear_scheduled_hook( 'wseo_gmc_sync_batch' );
wp_clear_scheduled_hook( 'wseo_gmc_recurring_sync' );
