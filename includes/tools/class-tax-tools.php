<?php
/**
 * Tax tools — rates and classes (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Tax_Tools {
	use Tool_Base;

	public static function register( Tools_Registry $registry ): void {
		$rate_props = [
			'country'  => [ 'type' => 'string', 'description' => 'ISO 3166-1 alpha-2 or empty for all.' ],
			'state'    => [ 'type' => 'string' ],
			'postcode' => [ 'type' => 'string' ],
			'city'     => [ 'type' => 'string' ],
			'rate'     => [ 'type' => 'number' ],
			'name'     => [ 'type' => 'string' ],
			'priority' => [ 'type' => 'integer', 'default' => 1 ],
			'compound' => [ 'type' => 'boolean', 'default' => false ],
			'shipping' => [ 'type' => 'boolean', 'default' => true ],
			'order'    => [ 'type' => 'integer', 'default' => 0 ],
			'class'    => [ 'type' => 'string', 'description' => 'Tax class slug or empty for standard.' ],
		];

		$registry->register( [
			'name'        => 'store_mcp_list_tax_rates',
			'description' => 'List tax rates, optionally filtered by class.',
			'tier'        => 'pro',
			'module'      => 'class-tax-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'class'    => [ 'type' => 'string' ],
					'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 100 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_tax_rates' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_tax_rate',
			'description' => 'Create a tax rate.',
			'tier'        => 'pro',
			'module'      => 'class-tax-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'rate', 'name' ],
				'properties' => $rate_props,
				'additionalProperties' => false,
			],
		], [ self::class, 'create_tax_rate' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_tax_rate',
			'description' => 'Update a tax rate.',
			'tier'        => 'pro',
			'module'      => 'class-tax-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => array_merge( [ 'id' => [ 'type' => 'integer' ] ], $rate_props ),
				'additionalProperties' => false,
			],
		], [ self::class, 'update_tax_rate' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_tax_rate',
			'description' => 'Delete a tax rate.',
			'tier'        => 'pro',
			'module'      => 'class-tax-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_tax_rate' ] );

		$registry->register( [
			'name'        => 'store_mcp_list_tax_classes',
			'description' => 'List tax classes (standard + custom).',
			'tier'        => 'pro',
			'module'      => 'class-tax-tools',
			'inputSchema' => [ 'type' => 'object', 'properties' => (object) [], 'additionalProperties' => false ],
		], [ self::class, 'list_tax_classes' ] );
	}

	public static function list_tax_rates( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		global $wpdb;
		$pagination = self::pagination( $args, 100, 200 );
		$class      = self::arg_string( $args, 'class', '' );
		$table      = $wpdb->prefix . 'woocommerce_tax_rates';
		$offset     = ( $pagination['page'] - 1 ) * $pagination['per_page'];

		if ( '' !== $class ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE tax_rate_class = %s", $class ) );
			$rows  = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} WHERE tax_rate_class = %s ORDER BY tax_rate_order ASC LIMIT %d OFFSET %d",
				$class,
				$pagination['per_page'],
				$offset
			) );
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			$rows  = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY tax_rate_order ASC LIMIT %d OFFSET %d",
				$pagination['per_page'],
				$offset
			) );
		}

		$items = array_map( [ self::class, 'format_rate' ], $rows ?: [] );
		return self::paginated( $items, $total, $pagination['page'], $pagination['per_page'] );
	}

	public static function create_tax_rate( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$data = self::prepare_rate( $args );
		$id   = \WC_Tax::_insert_tax_rate( $data );

		if ( ! $id ) {
			throw new Tool_Exception( esc_html__( 'Could not create tax rate', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}
		self::apply_locations( (int) $id, $args );
		return self::get_rate_by_id( (int) $id );
	}

	public static function update_tax_rate( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$id   = (int) self::require_arg( $args, 'id' );
		$data = self::prepare_rate( $args, true );
		\WC_Tax::_update_tax_rate( $id, $data );
		self::apply_locations( $id, $args );
		return self::get_rate_by_id( $id );
	}

	public static function delete_tax_rate( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$id = (int) self::require_arg( $args, 'id' );
		\WC_Tax::_delete_tax_rate( $id );
		return [ 'id' => $id, 'deleted' => true ];
	}

	public static function list_tax_classes( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$classes = \WC_Tax::get_tax_classes();
		$items   = [ [ 'name' => __( 'Standard', 'store-mcp' ), 'slug' => 'standard' ] ];
		foreach ( $classes as $name ) {
			$items[] = [ 'name' => $name, 'slug' => sanitize_title( $name ) ];
		}
		return [ 'items' => $items, 'count' => count( $items ) ];
	}

	private static function prepare_rate( array $args, bool $partial = false ): array {
		$data = [];
		if ( ! $partial || array_key_exists( 'country', $args ) )  $data['tax_rate_country']  = strtoupper( (string) self::arg_string( $args, 'country', '' ) );
		if ( ! $partial || array_key_exists( 'state', $args ) )    $data['tax_rate_state']    = strtoupper( (string) self::arg_string( $args, 'state', '' ) );
		if ( ! $partial || array_key_exists( 'rate', $args ) )     $data['tax_rate']          = number_format( (float) self::arg_float( $args, 'rate', 0 ), 4, '.', '' );
		if ( ! $partial || array_key_exists( 'name', $args ) )     $data['tax_rate_name']     = sanitize_text_field( (string) self::arg_string( $args, 'name', '' ) );
		if ( ! $partial || array_key_exists( 'priority', $args ) ) $data['tax_rate_priority'] = (int) self::arg_int( $args, 'priority', 1 );
		if ( ! $partial || array_key_exists( 'compound', $args ) ) $data['tax_rate_compound'] = (int) self::arg_bool( $args, 'compound', false );
		if ( ! $partial || array_key_exists( 'shipping', $args ) ) $data['tax_rate_shipping'] = (int) self::arg_bool( $args, 'shipping', true );
		if ( ! $partial || array_key_exists( 'order', $args ) )    $data['tax_rate_order']    = (int) self::arg_int( $args, 'order', 0 );
		if ( ! $partial || array_key_exists( 'class', $args ) )    $data['tax_rate_class']    = self::arg_string( $args, 'class', '' );
		return $data;
	}

	private static function apply_locations( int $id, array $args ): void {
		if ( array_key_exists( 'postcode', $args ) ) {
			$postcodes = array_filter( array_map( 'trim', explode( ';', (string) $args['postcode'] ) ) );
			\WC_Tax::_update_tax_rate_postcodes( $id, $postcodes ?: '' );
		}
		if ( array_key_exists( 'city', $args ) ) {
			$cities = array_filter( array_map( 'trim', explode( ';', (string) $args['city'] ) ) );
			\WC_Tax::_update_tax_rate_cities( $id, $cities ?: '' );
		}
	}

	private static function get_rate_by_id( int $id ): array {
		$rate = \WC_Tax::_get_tax_rate( $id, OBJECT );
		if ( ! $rate ) {
			throw new Tool_Exception( esc_html__( 'Tax rate not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		return self::format_rate( $rate );
	}

	private static function format_rate( $rate ): array {
		return [
			'id'       => (int) $rate->tax_rate_id,
			'country'  => (string) $rate->tax_rate_country,
			'state'    => (string) $rate->tax_rate_state,
			'postcode' => (string) ( $rate->postcode ?? ( $rate->tax_rate_postcode ?? '' ) ),
			'city'     => (string) ( $rate->city ?? ( $rate->tax_rate_city ?? '' ) ),
			'rate'     => (float) $rate->tax_rate,
			'name'     => (string) $rate->tax_rate_name,
			'priority' => (int) $rate->tax_rate_priority,
			'compound' => (bool) $rate->tax_rate_compound,
			'shipping' => (bool) $rate->tax_rate_shipping,
			'order'    => (int) $rate->tax_rate_order,
			'class'    => (string) $rate->tax_rate_class,
		];
	}
}

add_action( 'store_mcp_register_tools', [ Tax_Tools::class, 'register' ] );
