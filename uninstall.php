<?php
/**
 * Uninstall — removes options, custom tables, transients and scheduled events.
 *
 * @package StoreMCP
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = [
	$wpdb->prefix . 'storemcp_api_keys',
	$wpdb->prefix . 'storemcp_activity',
	$wpdb->prefix . 'storemcp_rate_limits',
	$wpdb->prefix . 'storemcp_oauth_clients',
	$wpdb->prefix . 'storemcp_oauth_codes',
	$wpdb->prefix . 'storemcp_oauth_tokens',
];
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

$option_keys = [
	'store_mcp_db_version',
	'store_mcp_enabled',
	'store_mcp_rate_limit_free',
	'store_mcp_rate_limit_pro',
	'store_mcp_log_retention_days',
	'store_mcp_cors_allowed',
	'store_mcp_allow_app_passwords',
	'store_mcp_cache_ttl_seconds',
	'store_mcp_modules',
	'store_mcp_disabled_modules',
	'store_mcp_license_key',
	'store_mcp_license_state',
	'store_mcp_white_label',
	'store_mcp_oauth_min_capability',
];
foreach ( $option_keys as $key ) {
	delete_option( $key );
	delete_site_option( $key );
}

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_store_mcp_%' OR option_name LIKE '_transient_timeout_store_mcp_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_store_mcp_%' OR option_name LIKE '_site_transient_timeout_store_mcp_%'" );

foreach ( [ 'store_mcp_prune_logs', 'store_mcp_recheck_license' ] as $hook ) {
	$timestamp = wp_next_scheduled( $hook );
	while ( $timestamp ) {
		wp_unschedule_event( $timestamp, $hook );
		$timestamp = wp_next_scheduled( $hook );
	}
}
