<?php
/**
 * Database installer — creates custom tables and seeds defaults.
 *
 * @package StoreMCP
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Installer {

	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		$keys_table = "CREATE TABLE {$prefix}storemcp_api_keys (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			key_id VARCHAR(32) NOT NULL,
			key_hash VARCHAR(255) NOT NULL,
			label VARCHAR(191) NOT NULL DEFAULT '',
			user_id BIGINT UNSIGNED NOT NULL,
			scopes LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			last_used_at DATETIME NULL,
			revoked_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY key_id (key_id),
			KEY user_id (user_id)
		) {$charset_collate};";

		$log_table = "CREATE TABLE {$prefix}storemcp_activity (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			ip VARCHAR(45) NOT NULL DEFAULT '',
			user_id BIGINT UNSIGNED NULL,
			key_id VARCHAR(32) NOT NULL DEFAULT '',
			method VARCHAR(64) NOT NULL DEFAULT '',
			tool_name VARCHAR(128) NOT NULL DEFAULT '',
			params LONGTEXT NULL,
			status VARCHAR(16) NOT NULL DEFAULT '',
			http_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
			error_message TEXT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY tool_name (tool_name),
			KEY status (status)
		) {$charset_collate};";

		$rate_table = "CREATE TABLE {$prefix}storemcp_rate_limits (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			bucket VARCHAR(191) NOT NULL,
			window_start INT UNSIGNED NOT NULL,
			counter INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY bucket_window (bucket, window_start)
		) {$charset_collate};";

		$clients_table = "CREATE TABLE {$prefix}storemcp_oauth_clients (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			client_id VARCHAR(64) NOT NULL,
			client_secret_hash VARCHAR(128) NULL,
			client_name VARCHAR(191) NOT NULL DEFAULT '',
			client_uri VARCHAR(255) NOT NULL DEFAULT '',
			logo_uri VARCHAR(255) NOT NULL DEFAULT '',
			redirect_uris LONGTEXT NULL,
			grant_types TEXT NULL,
			response_types TEXT NULL,
			token_endpoint_auth_method VARCHAR(64) NOT NULL DEFAULT 'none',
			scope VARCHAR(255) NOT NULL DEFAULT 'mcp',
			metadata LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			last_used_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY client_id (client_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$codes_table = "CREATE TABLE {$prefix}storemcp_oauth_codes (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code_hash VARCHAR(128) NOT NULL,
			client_id VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			redirect_uri VARCHAR(500) NOT NULL DEFAULT '',
			code_challenge VARCHAR(255) NOT NULL DEFAULT '',
			code_challenge_method VARCHAR(16) NOT NULL DEFAULT '',
			scope VARCHAR(255) NOT NULL DEFAULT '',
			resource VARCHAR(500) NOT NULL DEFAULT '',
			expires_at DATETIME NOT NULL,
			used_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code_hash (code_hash),
			KEY expires_at (expires_at),
			KEY client_id (client_id)
		) {$charset_collate};";

		$tokens_table = "CREATE TABLE {$prefix}storemcp_oauth_tokens (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token_hash VARCHAR(128) NOT NULL,
			refresh_hash VARCHAR(128) NULL,
			client_id VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			scopes LONGTEXT NULL,
			expires_at DATETIME NOT NULL,
			refresh_expires_at DATETIME NULL,
			revoked_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			last_used_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			UNIQUE KEY refresh_hash (refresh_hash),
			KEY client_id (client_id),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		dbDelta( $keys_table );
		dbDelta( $log_table );
		dbDelta( $rate_table );
		dbDelta( $clients_table );
		dbDelta( $codes_table );
		dbDelta( $tokens_table );

		self::seed_defaults();
		update_option( 'store_mcp_db_version', STORE_MCP_DB_VERSION, false );
	}

	public static function maybe_upgrade(): void {
		$current = (string) get_option( 'store_mcp_db_version', '' );
		if ( $current === STORE_MCP_DB_VERSION ) {
			return;
		}
		self::install();
	}

	private static function seed_defaults(): void {
		$defaults = [
			'enabled'              => true,
			'rate_limit_free'      => 30,
			'rate_limit_pro'       => 120,
			'log_retention_days'   => 30,
			'cors_allowed'         => [ 'https://claude.ai', 'https://chat.openai.com', 'https://chatgpt.com' ],
			'allow_app_passwords'  => true,
			'cache_ttl_seconds'    => 300,
			'modules'              => [],
			'oauth_min_capability' => 'manage_options',
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( 'store_mcp_' . $key, false ) ) {
				add_option( 'store_mcp_' . $key, $value, '', false );
			}
		}
	}
}
