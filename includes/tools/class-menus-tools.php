<?php
/**
 * Menus tools — navigation menus and menu items (FREE).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Menus_Tools {
	use Tool_Base;

	const ITEM_TYPES = [ 'custom', 'page', 'post', 'category', 'product', 'product_cat', 'taxonomy' ];

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_menus',
			'description' => 'List all navigation menus with their assigned theme locations.',
			'tier'        => 'free',
			'module'      => 'class-menus-tools',
			'inputSchema' => [
				'type'                 => 'object',
				'properties'           => (object) [],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_menus' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_menu',
			'description' => 'Get a menu and its items with hierarchy.',
			'tier'        => 'free',
			'module'      => 'class-menus-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [ 'id' => [ 'type' => 'integer', 'description' => 'Menu term id.' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_menu' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_menu_item',
			'description' => 'Add an item to a navigation menu.',
			'tier'        => 'free',
			'module'      => 'class-menus-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'menu_id', 'title' ],
				'properties' => [
					'menu_id'   => [ 'type' => 'integer' ],
					'title'     => [ 'type' => 'string' ],
					'url'       => [ 'type' => 'string', 'description' => 'Required when type=custom.' ],
					'type'      => [ 'type' => 'string', 'enum' => self::ITEM_TYPES, 'default' => 'custom' ],
					'object_id' => [ 'type' => 'integer', 'description' => 'Linked object id (page, post, term, product…).' ],
					'taxonomy'  => [ 'type' => 'string', 'description' => 'Taxonomy slug when type=taxonomy.' ],
					'parent'    => [ 'type' => 'integer', 'description' => 'Parent menu item id.' ],
					'position'  => [ 'type' => 'integer' ],
					'target'    => [ 'type' => 'string', 'enum' => [ '', '_blank' ] ],
					'classes'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_menu_item' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_menu_item',
			'description' => 'Update an existing menu item.',
			'tier'        => 'free',
			'module'      => 'class-menus-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'menu_id', 'item_id' ],
				'properties' => [
					'menu_id'  => [ 'type' => 'integer' ],
					'item_id'  => [ 'type' => 'integer' ],
					'title'    => [ 'type' => 'string' ],
					'url'      => [ 'type' => 'string' ],
					'parent'   => [ 'type' => 'integer' ],
					'position' => [ 'type' => 'integer' ],
					'target'   => [ 'type' => 'string', 'enum' => [ '', '_blank' ] ],
					'classes'  => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_menu_item' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_menu_item',
			'description' => 'Remove an item from a menu.',
			'tier'        => 'free',
			'module'      => 'class-menus-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'menu_id', 'item_id' ],
				'properties' => [
					'menu_id' => [ 'type' => 'integer' ],
					'item_id' => [ 'type' => 'integer' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_menu_item' ] );
	}

	public static function list_menus( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_theme_options' );

		$menus     = wp_get_nav_menus();
		$locations = get_nav_menu_locations();

		$items = [];
		foreach ( $menus as $menu ) {
			$loc = array_keys( array_filter( $locations, static fn( $id ) => (int) $id === (int) $menu->term_id ) );
			$items[] = [
				'id'        => (int) $menu->term_id,
				'name'      => $menu->name,
				'slug'      => $menu->slug,
				'count'     => (int) $menu->count,
				'locations' => array_values( $loc ),
			];
		}

		return [ 'items' => $items ];
	}

	public static function get_menu( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_theme_options' );

		$id   = (int) self::require_arg( $args, 'id' );
		$menu = wp_get_nav_menu_object( $id );
		if ( ! $menu ) {
			throw new Tool_Exception( esc_html__( 'Menu not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$items = wp_get_nav_menu_items( $id, [ 'update_post_term_cache' => false ] ) ?: [];

		return [
			'id'    => (int) $menu->term_id,
			'name'  => $menu->name,
			'slug'  => $menu->slug,
			'items' => array_map( [ self::class, 'format_menu_item' ], $items ),
		];
	}

	public static function create_menu_item( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_theme_options' );

		$menu_id = (int) self::require_arg( $args, 'menu_id' );
		$type    = (string) self::arg_enum( $args, 'type', self::ITEM_TYPES, 'custom' );

		$data = self::build_item_data( $type, $args );
		$data['menu-item-status'] = 'publish';

		$id = wp_update_nav_menu_item( $menu_id, 0, $data );
		if ( is_wp_error( $id ) ) {
			throw new Tool_Exception( esc_html( $id->get_error_message() ), Server::ERR_TOOL_FAILED );
		}
		return self::format_menu_item( get_post( $id ) );
	}

	public static function update_menu_item( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_theme_options' );

		$menu_id = (int) self::require_arg( $args, 'menu_id' );
		$item_id = (int) self::require_arg( $args, 'item_id' );

		$existing = wp_setup_nav_menu_item( get_post( $item_id ) );
		if ( ! $existing ) {
			throw new Tool_Exception( esc_html__( 'Menu item not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$type = (string) ( $existing->type ?: 'custom' );
		$data = self::build_item_data( $type, $args, $existing );
		$data['menu-item-status'] = 'publish';

		$id = wp_update_nav_menu_item( $menu_id, $item_id, $data );
		if ( is_wp_error( $id ) ) {
			throw new Tool_Exception( esc_html( $id->get_error_message() ), Server::ERR_TOOL_FAILED );
		}
		return self::format_menu_item( get_post( $id ) );
	}

	public static function delete_menu_item( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_theme_options' );

		$item_id = (int) self::require_arg( $args, 'item_id' );
		$ok      = wp_delete_post( $item_id, true );
		if ( ! $ok ) {
			throw new Tool_Exception( esc_html__( 'Could not delete menu item', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}
		return [ 'item_id' => $item_id, 'deleted' => true ];
	}

	private static function build_item_data( string $type, array $args, $existing = null ): array {
		$data = [
			'menu-item-title'     => self::arg_string( $args, 'title', $existing->title ?? '' ),
			'menu-item-url'       => self::arg_string( $args, 'url', $existing->url ?? '' ),
			'menu-item-parent-id' => (int) self::arg_int( $args, 'parent', $existing->menu_item_parent ?? 0 ),
			'menu-item-position'  => (int) self::arg_int( $args, 'position', $existing->menu_order ?? 0 ),
			'menu-item-target'    => self::arg_string( $args, 'target', $existing->target ?? '' ),
			'menu-item-type'      => 'custom' === $type ? 'custom' : ( in_array( $type, [ 'page', 'post' ], true ) ? 'post_type' : 'taxonomy' ),
			'menu-item-object'    => $type,
		];
		if ( in_array( $type, [ 'category', 'tag', 'product_cat' ], true ) ) {
			$data['menu-item-type'] = 'taxonomy';
		}
		if ( 'taxonomy' === $type && ! empty( $args['taxonomy'] ) ) {
			$data['menu-item-object'] = sanitize_key( (string) $args['taxonomy'] );
		}
		if ( isset( $args['object_id'] ) ) {
			$data['menu-item-object-id'] = (int) $args['object_id'];
		}
		if ( ! empty( $args['classes'] ) && is_array( $args['classes'] ) ) {
			$data['menu-item-classes'] = implode( ' ', array_map( 'sanitize_html_class', $args['classes'] ) );
		}
		return $data;
	}

	private static function format_menu_item( $item ): array {
		$item = wp_setup_nav_menu_item( $item );
		return [
			'id'        => (int) $item->ID,
			'title'     => (string) $item->title,
			'url'       => (string) $item->url,
			'type'      => (string) $item->type,
			'object'    => (string) $item->object,
			'object_id' => (int) $item->object_id,
			'parent'    => (int) $item->menu_item_parent,
			'position'  => (int) $item->menu_order,
			'target'    => (string) $item->target,
			'classes'   => array_values( array_filter( (array) $item->classes ) ),
		];
	}
}

add_action( 'store_mcp_register_tools', [ Menus_Tools::class, 'register' ] );
