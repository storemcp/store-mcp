<?php
/**
 * Widgets tools — sidebars and widgets (FREE).
 *
 * Note: WordPress 5.8+ also exposes block-based widgets via the REST API.
 * These tools use the classic widget API which works across both contexts.
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Widgets_Tools {
	use Tool_Base;

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_sidebars',
			'description' => 'List registered sidebars (widget areas) on the active theme.',
			'tier'        => 'free',
			'module'      => 'class-widgets-tools',
			'inputSchema' => [ 'type' => 'object', 'properties' => (object) [], 'additionalProperties' => false ],
		], [ self::class, 'list_sidebars' ] );

		$registry->register( [
			'name'        => 'store_mcp_list_widgets',
			'description' => 'List widgets in a sidebar.',
			'tier'        => 'free',
			'module'      => 'class-widgets-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'sidebar_id' ],
				'properties' => [ 'sidebar_id' => [ 'type' => 'string' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_widgets' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_widget',
			'description' => 'Add a widget instance to a sidebar.',
			'tier'        => 'free',
			'module'      => 'class-widgets-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'sidebar_id', 'widget_type' ],
				'properties' => [
					'sidebar_id'  => [ 'type' => 'string' ],
					'widget_type' => [ 'type' => 'string', 'description' => 'Widget id_base (e.g. text, recent-posts, block).' ],
					'settings'    => [ 'type' => 'object', 'additionalProperties' => true ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_widget' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_widget',
			'description' => 'Update settings of an existing widget instance.',
			'tier'        => 'free',
			'module'      => 'class-widgets-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'widget_id', 'settings' ],
				'properties' => [
					'widget_id' => [ 'type' => 'string', 'description' => 'Full widget id, e.g. text-3.' ],
					'settings'  => [ 'type' => 'object', 'additionalProperties' => true ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_widget' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_widget',
			'description' => 'Remove a widget instance from its sidebar.',
			'tier'        => 'free',
			'module'      => 'class-widgets-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'widget_id' ],
				'properties' => [ 'widget_id' => [ 'type' => 'string' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_widget' ] );
	}

	public static function list_sidebars( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_theme_options' );

		global $wp_registered_sidebars;
		$widgets = wp_get_sidebars_widgets();

		$out = [];
		foreach ( (array) $wp_registered_sidebars as $id => $sidebar ) {
			$out[] = [
				'id'           => $id,
				'name'         => $sidebar['name'] ?? $id,
				'description'  => $sidebar['description'] ?? '',
				'widget_count' => isset( $widgets[ $id ] ) ? count( $widgets[ $id ] ) : 0,
			];
		}
		return [ 'items' => $out ];
	}

	public static function list_widgets( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_theme_options' );

		$sidebar_id = (string) self::require_arg( $args, 'sidebar_id' );
		$widgets    = wp_get_sidebars_widgets();
		$ids        = $widgets[ $sidebar_id ] ?? [];

		$items = [];
		foreach ( $ids as $widget_id ) {
			$items[] = self::describe_widget( (string) $widget_id );
		}
		return [ 'sidebar_id' => $sidebar_id, 'items' => $items ];
	}

	public static function create_widget( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_theme_options' );

		$sidebar_id  = (string) self::require_arg( $args, 'sidebar_id' );
		$widget_type = sanitize_key( (string) self::require_arg( $args, 'widget_type' ) );
		$settings    = self::arg_array( $args, 'settings', [] );

		$option_key = 'widget_' . $widget_type;
		$instances  = get_option( $option_key, [] );
		if ( ! is_array( $instances ) ) {
			$instances = [];
		}

		$next  = self::next_widget_number( $instances );
		$instances[ $next ] = $settings;
		if ( ! isset( $instances['_multiwidget'] ) ) {
			$instances['_multiwidget'] = 1;
		}
		update_option( $option_key, $instances );

		$widget_id = $widget_type . '-' . $next;
		$sidebars  = wp_get_sidebars_widgets();
		$sidebars[ $sidebar_id ]   = $sidebars[ $sidebar_id ] ?? [];
		$sidebars[ $sidebar_id ][] = $widget_id;
		wp_set_sidebars_widgets( $sidebars );

		return self::describe_widget( $widget_id );
	}

	public static function update_widget( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_theme_options' );

		$widget_id = (string) self::require_arg( $args, 'widget_id' );
		$settings  = self::arg_array( $args, 'settings', [] );

		[ $type, $number ] = self::split_widget_id( $widget_id );
		if ( null === $type ) {
			throw new Tool_Exception( __( 'Invalid widget_id', 'store-mcp' ), Server::RPC_INVALID_PARAMS );
		}

		$option_key = 'widget_' . $type;
		$instances  = get_option( $option_key, [] );
		if ( ! isset( $instances[ $number ] ) ) {
			throw new Tool_Exception( __( 'Widget instance not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		$instances[ $number ] = array_merge( (array) $instances[ $number ], $settings );
		update_option( $option_key, $instances );

		return self::describe_widget( $widget_id );
	}

	public static function delete_widget( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_theme_options' );

		$widget_id = (string) self::require_arg( $args, 'widget_id' );
		[ $type, $number ] = self::split_widget_id( $widget_id );

		$sidebars = wp_get_sidebars_widgets();
		foreach ( $sidebars as $sidebar => $widget_ids ) {
			if ( ! is_array( $widget_ids ) ) continue;
			$sidebars[ $sidebar ] = array_values( array_filter( $widget_ids, static fn( $id ) => $id !== $widget_id ) );
		}
		wp_set_sidebars_widgets( $sidebars );

		if ( $type && $number ) {
			$option_key = 'widget_' . $type;
			$instances  = get_option( $option_key, [] );
			unset( $instances[ $number ] );
			update_option( $option_key, $instances );
		}

		return [ 'widget_id' => $widget_id, 'deleted' => true ];
	}

	private static function describe_widget( string $widget_id ): array {
		[ $type, $number ] = self::split_widget_id( $widget_id );
		$instances = get_option( 'widget_' . $type, [] );
		return [
			'widget_id' => $widget_id,
			'type'      => $type,
			'number'    => $number,
			'settings'  => $instances[ $number ] ?? [],
		];
	}

	private static function split_widget_id( string $widget_id ): array {
		if ( ! preg_match( '/^(.+)-(\d+)$/', $widget_id, $m ) ) {
			return [ null, null ];
		}
		return [ sanitize_key( $m[1] ), (int) $m[2] ];
	}

	private static function next_widget_number( array $instances ): int {
		$max = 0;
		foreach ( array_keys( $instances ) as $key ) {
			if ( is_int( $key ) && $key > $max ) {
				$max = $key;
			}
		}
		return $max + 1;
	}
}

add_action( 'store_mcp_register_tools', [ Widgets_Tools::class, 'register' ] );
