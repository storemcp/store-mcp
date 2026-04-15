<?php
/**
 * Themes tools — list, get active, switch (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Themes_Tools {
	use Tool_Base;

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_get_active_theme',
			'description' => 'Get the currently active theme with full metadata.',
			'tier'        => 'pro',
			'module'      => 'class-themes-tools',
			'inputSchema' => [ 'type' => 'object', 'properties' => (object) [], 'additionalProperties' => false ],
		], [ self::class, 'get_active_theme' ] );

		$registry->register( [
			'name'        => 'store_mcp_list_themes',
			'description' => 'List installed themes.',
			'tier'        => 'pro',
			'module'      => 'class-themes-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'status' => [ 'type' => 'string', 'enum' => [ 'all', 'active', 'inactive' ], 'default' => 'all' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_themes' ] );

		$registry->register( [
			'name'        => 'store_mcp_switch_theme',
			'description' => 'Switch to another theme by stylesheet slug. Requires switch_themes capability.',
			'tier'        => 'pro',
			'module'      => 'class-themes-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'stylesheet' ],
				'properties' => [
					'stylesheet' => [ 'type' => 'string', 'description' => 'Theme folder name.' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'switch_theme' ] );
	}

	public static function get_active_theme( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'switch_themes' );
		$theme = wp_get_theme();
		return self::format_theme( $theme, true );
	}

	public static function list_themes( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'switch_themes' );

		$status      = (string) self::arg_enum( $args, 'status', [ 'all', 'active', 'inactive' ], 'all' );
		$active_slug = get_stylesheet();

		$items = [];
		foreach ( wp_get_themes() as $slug => $theme ) {
			$is_active = $slug === $active_slug;
			if ( 'active' === $status && ! $is_active ) continue;
			if ( 'inactive' === $status && $is_active )  continue;
			$items[] = array_merge( self::format_theme( $theme, false ), [ 'active' => $is_active ] );
		}
		return [ 'items' => $items, 'count' => count( $items ), 'active_stylesheet' => $active_slug ];
	}

	public static function switch_theme( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'switch_themes' );

		$slug = sanitize_key( (string) self::require_arg( $args, 'stylesheet' ) );
		$theme = wp_get_theme( $slug );
		if ( ! $theme->exists() ) {
			throw new Tool_Exception( esc_html__( 'Theme not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		if ( method_exists( $theme, 'errors' ) && $theme->errors() ) {
			throw new Tool_Exception( esc_html__( 'Theme has errors and cannot be activated', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}

		switch_theme( $slug );

		return array_merge( self::format_theme( wp_get_theme(), true ), [ 'switched' => true ] );
	}

	private static function format_theme( \WP_Theme $theme, bool $full ): array {
		$base = [
			'name'         => $theme->get( 'Name' ),
			'version'      => $theme->get( 'Version' ),
			'author'       => wp_strip_all_tags( (string) $theme->get( 'Author' ) ),
			'template'     => $theme->get_template(),
			'stylesheet'   => $theme->get_stylesheet(),
			'screenshot'   => $theme->get_screenshot(),
			'theme_uri'    => $theme->get( 'ThemeURI' ),
			'description'  => wp_strip_all_tags( (string) $theme->get( 'Description' ) ),
		];
		if ( ! $full ) {
			return $base;
		}
		return array_merge( $base, [
			'parent'       => $theme->parent() ? $theme->parent()->get_stylesheet() : null,
			'tags'         => (array) $theme->get( 'Tags' ),
			'requires_wp'  => (string) $theme->get( 'RequiresWP' ),
			'requires_php' => (string) $theme->get( 'RequiresPHP' ),
			'is_block_theme' => method_exists( $theme, 'is_block_theme' ) ? $theme->is_block_theme() : false,
		] );
	}
}

add_action( 'store_mcp_register_tools', [ Themes_Tools::class, 'register' ] );
