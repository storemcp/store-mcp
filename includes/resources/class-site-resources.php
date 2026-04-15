<?php
/**
 * Site resources — passive site context.
 *
 * @package StoreMCP\Resources
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Site_Resources {

	public static function register( Resources_Registry $registry ): void {
		$registry->register( [
			'uri'         => 'store://site/info',
			'name'        => 'Site info',
			'description' => 'General WordPress + WooCommerce site information.',
			'tier'        => 'free',
		], [ self::class, 'site_info' ] );

		$registry->register( [
			'uri'         => 'store://plugins/active',
			'name'        => 'Active plugins',
			'description' => 'List of active plugins with their versions.',
			'tier'        => 'free',
		], [ self::class, 'active_plugins' ] );
	}

	public static function site_info( AuthContext $context ): array {
		return Site_Tools::get_site_info( [], $context );
	}

	public static function active_plugins( AuthContext $context ): array {
		Permissions::require_cap( $context, 'read' );

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all    = get_plugins();
		$active = (array) get_option( 'active_plugins', [] );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
		}

		$out = [];
		foreach ( array_unique( $active ) as $file ) {
			if ( ! isset( $all[ $file ] ) ) continue;
			$plugin = $all[ $file ];
			$out[]  = [
				'file'    => $file,
				'name'    => $plugin['Name'] ?? $file,
				'version' => $plugin['Version'] ?? '',
				'author'  => wp_strip_all_tags( (string) ( $plugin['Author'] ?? '' ) ),
			];
		}
		return [ 'items' => $out, 'count' => count( $out ) ];
	}
}

add_action( 'store_mcp_register_resources', [ Site_Resources::class, 'register' ] );
