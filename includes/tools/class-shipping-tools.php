<?php
/**
 * Shipping tools — zones and methods (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Shipping_Tools {
	use Tool_Base;

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_shipping_zones',
			'description' => 'List all shipping zones (including "Rest of the world").',
			'tier'        => 'pro',
			'module'      => 'class-shipping-tools',
			'inputSchema' => [ 'type' => 'object', 'properties' => (object) [], 'additionalProperties' => false ],
		], [ self::class, 'list_zones' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_shipping_zone',
			'description' => 'Get a shipping zone with its locations and methods.',
			'tier'        => 'pro',
			'module'      => 'class-shipping-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_zone' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_shipping_zone',
			'description' => 'Create a shipping zone.',
			'tier'        => 'pro',
			'module'      => 'class-shipping-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'name' ],
				'properties' => [
					'name'      => [ 'type' => 'string' ],
					'order'     => [ 'type' => 'integer', 'default' => 0 ],
					'locations' => [
						'type'  => 'array',
						'items' => [
							'type' => 'object',
							'properties' => [
								'code' => [ 'type' => 'string' ],
								'type' => [ 'type' => 'string', 'enum' => [ 'country', 'state', 'postcode', 'continent' ] ],
							],
						],
					],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_zone' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_shipping_zone',
			'description' => 'Update a shipping zone.',
			'tier'        => 'pro',
			'module'      => 'class-shipping-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'        => [ 'type' => 'integer' ],
					'name'      => [ 'type' => 'string' ],
					'order'     => [ 'type' => 'integer' ],
					'locations' => [
						'type'  => 'array',
						'items' => [
							'type' => 'object',
							'properties' => [
								'code' => [ 'type' => 'string' ],
								'type' => [ 'type' => 'string', 'enum' => [ 'country', 'state', 'postcode', 'continent' ] ],
							],
						],
					],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_zone' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_shipping_zone',
			'description' => 'Delete a shipping zone.',
			'tier'        => 'pro',
			'module'      => 'class-shipping-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_zone' ] );

		$registry->register( [
			'name'        => 'store_mcp_list_zone_methods',
			'description' => 'List shipping methods of a zone.',
			'tier'        => 'pro',
			'module'      => 'class-shipping-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'zone_id' ],
				'properties' => [ 'zone_id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_zone_methods' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_zone_method',
			'description' => 'Add a shipping method to a zone.',
			'tier'        => 'pro',
			'module'      => 'class-shipping-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'zone_id', 'method_id' ],
				'properties' => [
					'zone_id'   => [ 'type' => 'integer' ],
					'method_id' => [ 'type' => 'string', 'description' => 'e.g. flat_rate, free_shipping, local_pickup.' ],
					'settings'  => [ 'type' => 'object', 'additionalProperties' => true ],
					'enabled'   => [ 'type' => 'boolean', 'default' => true ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_zone_method' ] );
	}

	public static function list_zones( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$zones = \WC_Shipping_Zones::get_zones();
		$items = [];
		foreach ( $zones as $zone ) {
			$items[] = [
				'id'            => (int) $zone['id'],
				'name'          => $zone['zone_name'],
				'order'         => (int) $zone['zone_order'],
				'locations'     => $zone['formatted_zone_location'] ?? '',
				'methods_count' => count( $zone['shipping_methods'] ?? [] ),
			];
		}
		$rest = new \WC_Shipping_Zone( 0 );
		$items[] = [
			'id'            => 0,
			'name'          => $rest->get_zone_name(),
			'order'         => 0,
			'locations'     => $rest->get_formatted_location(),
			'methods_count' => count( $rest->get_shipping_methods() ),
		];

		return [ 'items' => $items, 'count' => count( $items ) ];
	}

	public static function get_zone( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$id   = (int) self::require_arg( $args, 'id' );
		$zone = \WC_Shipping_Zones::get_zone( $id );
		if ( ! $zone && 0 !== $id ) {
			throw new Tool_Exception( esc_html__( 'Zone not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		if ( 0 === $id && ! $zone ) {
			$zone = new \WC_Shipping_Zone( 0 );
		}
		return self::format_zone( $zone );
	}

	public static function create_zone( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( sanitize_text_field( (string) self::require_arg( $args, 'name' ) ) );
		$zone->set_zone_order( (int) self::arg_int( $args, 'order', 0 ) );

		if ( isset( $args['locations'] ) && is_array( $args['locations'] ) ) {
			$zone->set_locations( self::sanitize_locations( $args['locations'] ) );
		}
		$zone->save();
		return self::format_zone( \WC_Shipping_Zones::get_zone( $zone->get_id() ) );
	}

	public static function update_zone( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$id   = (int) self::require_arg( $args, 'id' );
		$zone = \WC_Shipping_Zones::get_zone( $id );
		if ( ! $zone ) {
			throw new Tool_Exception( esc_html__( 'Zone not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		if ( array_key_exists( 'name', $args ) )  $zone->set_zone_name( sanitize_text_field( (string) $args['name'] ) );
		if ( array_key_exists( 'order', $args ) ) $zone->set_zone_order( (int) $args['order'] );
		if ( isset( $args['locations'] ) && is_array( $args['locations'] ) ) {
			$zone->set_locations( self::sanitize_locations( $args['locations'] ) );
		}
		$zone->save();
		return self::format_zone( \WC_Shipping_Zones::get_zone( $id ) );
	}

	public static function delete_zone( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$id = (int) self::require_arg( $args, 'id' );
		if ( 0 === $id ) {
			throw new Tool_Exception( esc_html__( 'The default zone cannot be deleted', 'store-mcp' ), Server::ERR_FORBIDDEN );
		}
		$zone = \WC_Shipping_Zones::get_zone( $id );
		if ( ! $zone ) {
			throw new Tool_Exception( esc_html__( 'Zone not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		$zone->delete();
		return [ 'id' => $id, 'deleted' => true ];
	}

	public static function list_zone_methods( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$zone_id = (int) self::require_arg( $args, 'zone_id' );
		$zone    = \WC_Shipping_Zones::get_zone( $zone_id );
		if ( ! $zone && 0 !== $zone_id ) {
			throw new Tool_Exception( esc_html__( 'Zone not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		if ( 0 === $zone_id && ! $zone ) {
			$zone = new \WC_Shipping_Zone( 0 );
		}
		$methods = array_map( [ self::class, 'format_method' ], array_values( $zone->get_shipping_methods() ) );
		return [ 'zone_id' => $zone_id, 'items' => $methods, 'count' => count( $methods ) ];
	}

	public static function create_zone_method( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$zone_id   = (int) self::require_arg( $args, 'zone_id' );
		$method_id = sanitize_key( (string) self::require_arg( $args, 'method_id' ) );
		$zone      = \WC_Shipping_Zones::get_zone( $zone_id );
		if ( ! $zone && 0 !== $zone_id ) {
			throw new Tool_Exception( esc_html__( 'Zone not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		if ( 0 === $zone_id && ! $zone ) {
			$zone = new \WC_Shipping_Zone( 0 );
		}

		$instance_id = $zone->add_shipping_method( $method_id );
		if ( ! $instance_id ) {
			throw new Tool_Exception( esc_html__( 'Could not create method', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}

		$settings = self::arg_array( $args, 'settings', [] );
		if ( $settings ) {
			$option_key = 'woocommerce_' . $method_id . '_' . $instance_id . '_settings';
			$current    = (array) get_option( $option_key, [] );
			update_option( $option_key, array_merge( $current, $settings ) );
		}
		if ( false === self::arg_bool( $args, 'enabled', true ) ) {
			global $wpdb;
			$wpdb->update( $wpdb->prefix . 'woocommerce_shipping_zone_methods', [ 'is_enabled' => 0 ], [ 'instance_id' => $instance_id ], [ '%d' ], [ '%d' ] );
		}

		$methods = $zone->get_shipping_methods();
		$method  = $methods[ $instance_id ] ?? null;
		return $method ? self::format_method( $method ) : [ 'instance_id' => $instance_id, 'method_id' => $method_id ];
	}

	private static function sanitize_locations( array $locations ): array {
		$out = [];
		foreach ( $locations as $loc ) {
			if ( empty( $loc['code'] ) || empty( $loc['type'] ) ) continue;
			$out[] = [
				'code' => sanitize_text_field( (string) $loc['code'] ),
				'type' => sanitize_key( (string) $loc['type'] ),
			];
		}
		return $out;
	}

	private static function format_zone( \WC_Shipping_Zone $zone ): array {
		$methods   = array_values( $zone->get_shipping_methods() );
		$locations = [];
		foreach ( $zone->get_zone_locations() as $loc ) {
			$locations[] = [ 'code' => $loc->code, 'type' => $loc->type ];
		}
		return [
			'id'                => $zone->get_id(),
			'name'              => $zone->get_zone_name(),
			'order'             => $zone->get_zone_order(),
			'locations'         => $locations,
			'formatted_location' => $zone->get_formatted_location(),
			'methods'           => array_map( [ self::class, 'format_method' ], $methods ),
		];
	}

	private static function format_method( \WC_Shipping_Method $method ): array {
		return [
			'instance_id' => (int) $method->instance_id,
			'method_id'   => $method->id,
			'title'       => $method->get_title(),
			'enabled'     => $method->is_enabled(),
			'order'       => (int) $method->method_order,
			'settings'    => $method->instance_settings ?? [],
		];
	}
}

add_action( 'store_mcp_register_tools', [ Shipping_Tools::class, 'register' ] );
