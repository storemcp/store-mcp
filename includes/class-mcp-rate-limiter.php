<?php
/**
 * Rate limiter — fixed window per minute, keyed by API key (or user id).
 *
 * Backed by a small DB table because object cache may not be persistent on
 * shared hosting. Atomic INSERT ... ON DUPLICATE KEY UPDATE for thread safety.
 *
 * @package StoreMCP
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Rate_Limiter {

	const WINDOW_SECONDS = 60;

	public function check( AuthContext $context ) {
		global $wpdb;

		$limit = $this->limit_for( $context );
		if ( $limit <= 0 ) {
			return true;
		}

		$bucket  = substr( 'k:' . $context->bucket(), 0, 191 );
		$window  = (int) ( time() / self::WINDOW_SECONDS ) * self::WINDOW_SECONDS;
		$table   = $wpdb->prefix . 'storemcp_rate_limits';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; $table is the wpdb prefix + literal name; values are bound through prepare().
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (bucket, window_start, counter) VALUES (%s, %d, 1)
			 ON DUPLICATE KEY UPDATE counter = counter + 1",
			$bucket,
			$window
		) );

		$current = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT counter FROM {$table} WHERE bucket = %s AND window_start = %d",
			$bucket,
			$window
		) );
		// phpcs:enable

		if ( $current > $limit ) {
			$retry_after = self::WINDOW_SECONDS - ( time() - $window );
			return new \WP_Error(
				'store_mcp_rate_limited',
				sprintf( /* translators: %d: limit */ __( 'Rate limit exceeded (%d requests/minute)', 'store-mcp' ), $limit ),
				[
					'status'      => 429,
					'retry_after' => max( 1, $retry_after ),
				]
			);
		}

		if ( wp_rand( 1, 100 ) === 1 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table prefixed by wpdb; $window bound via prepare().
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$table} WHERE window_start < %d",
				$window - ( self::WINDOW_SECONDS * 5 )
			) );
		}

		return true;
	}

	private function limit_for( AuthContext $context ): int {
		$license = Plugin::instance()->license;

		$tier = $license->tier();

		if ( License::TIER_AGENCY === $tier ) {
			return (int) get_option( 'store_mcp_rate_limit_pro', 120 ) * 5;
		}
		if ( License::TIER_PRO === $tier ) {
			return (int) get_option( 'store_mcp_rate_limit_pro', 120 );
		}
		return (int) get_option( 'store_mcp_rate_limit_free', 30 );
	}
}
