<?php
/**
 * Maintenance — cron jobs for log pruning and license re-checks.
 *
 * @package StoreMCP
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Maintenance {

	const HOOK_PRUNE   = 'store_mcp_prune_logs';
	const HOOK_LICENSE = 'store_mcp_recheck_license';

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK_PRUNE ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_PRUNE );
		}
		if ( ! wp_next_scheduled( self::HOOK_LICENSE ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::HOOK_LICENSE );
		}

		add_action( self::HOOK_PRUNE, [ __CLASS__, 'prune_logs' ] );
		add_action( self::HOOK_LICENSE, [ __CLASS__, 'recheck_license' ] );
	}

	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK_PRUNE );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK_PRUNE );
		}
		$timestamp = wp_next_scheduled( self::HOOK_LICENSE );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK_LICENSE );
		}
	}

	public static function prune_logs(): void {
		$days = (int) get_option( 'store_mcp_log_retention_days', 30 );
		Plugin::instance()->activity_log->prune( $days );
		OAuth::purge_expired();
	}

	public static function recheck_license(): void {
		Plugin::instance()->license->verify_remote();
	}
}
