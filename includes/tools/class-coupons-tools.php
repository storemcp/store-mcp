<?php
/**
 * Coupons tools — WooCommerce coupons CRUD (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Coupons_Tools {
	use Tool_Base;

	const DISCOUNT_TYPES = [ 'percent', 'fixed_cart', 'fixed_product' ];

	public static function register( Tools_Registry $registry ): void {
		$mutable = [
			'code'                         => [ 'type' => 'string' ],
			'discount_type'                => [ 'type' => 'string', 'enum' => self::DISCOUNT_TYPES ],
			'amount'                       => [ 'type' => 'number' ],
			'description'                  => [ 'type' => 'string' ],
			'date_expires'                 => [ 'type' => 'string', 'description' => 'ISO 8601.' ],
			'individual_use'               => [ 'type' => 'boolean' ],
			'product_ids'                  => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
			'excluded_product_ids'         => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
			'usage_limit'                  => [ 'type' => 'integer' ],
			'usage_limit_per_user'         => [ 'type' => 'integer' ],
			'limit_usage_to_x_items'       => [ 'type' => 'integer' ],
			'free_shipping'                => [ 'type' => 'boolean' ],
			'product_categories'           => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
			'excluded_product_categories'  => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
			'exclude_sale_items'           => [ 'type' => 'boolean' ],
			'minimum_amount'               => [ 'type' => 'number' ],
			'maximum_amount'               => [ 'type' => 'number' ],
			'email_restrictions'           => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
		];

		$registry->register( [
			'name'        => 'store_mcp_list_coupons',
			'description' => 'List WooCommerce coupons.',
			'tier'        => 'pro',
			'module'      => 'class-coupons-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'search'   => [ 'type' => 'string' ],
					'code'     => [ 'type' => 'string' ],
					'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_coupons' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_coupon',
			'description' => 'Get a coupon by id.',
			'tier'        => 'pro',
			'module'      => 'class-coupons-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_coupon' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_coupon',
			'description' => 'Create a new coupon.',
			'tier'        => 'pro',
			'module'      => 'class-coupons-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'code', 'discount_type', 'amount' ],
				'properties' => $mutable,
				'additionalProperties' => false,
			],
		], [ self::class, 'create_coupon' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_coupon',
			'description' => 'Update an existing coupon.',
			'tier'        => 'pro',
			'module'      => 'class-coupons-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => array_merge( [ 'id' => [ 'type' => 'integer' ] ], $mutable ),
				'additionalProperties' => false,
			],
		], [ self::class, 'update_coupon' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_coupon',
			'description' => 'Delete a coupon. force=true skips trash.',
			'tier'        => 'pro',
			'module'      => 'class-coupons-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'    => [ 'type' => 'integer' ],
					'force' => [ 'type' => 'boolean', 'default' => true ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_coupon' ] );
	}

	public static function list_coupons( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$pagination = self::pagination( $args );

		$query_args = [
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => $pagination['per_page'],
			'paged'          => $pagination['page'],
			'orderby'        => 'date',
			'order'          => 'DESC',
		];
		if ( $s = self::arg_string( $args, 'search', '' ) ) {
			$query_args['s'] = $s;
		}
		if ( $code = self::arg_string( $args, 'code', '' ) ) {
			$query_args['name'] = sanitize_title( $code );
		}

		$query = new \WP_Query( $query_args );
		$items = array_map( static fn( \WP_Post $p ) => self::format_coupon( new \WC_Coupon( $p->ID ) ), $query->posts );

		return self::paginated( $items, (int) $query->found_posts, $pagination['page'], $pagination['per_page'] );
	}

	public static function get_coupon( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$id     = (int) self::require_arg( $args, 'id' );
		$coupon = new \WC_Coupon( $id );
		if ( ! $coupon->get_id() ) {
			throw new Tool_Exception( esc_html__( 'Coupon not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		return self::format_coupon( $coupon );
	}

	public static function create_coupon( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$code = sanitize_text_field( (string) self::require_arg( $args, 'code' ) );
		if ( wc_get_coupon_id_by_code( $code ) ) {
			throw new Tool_Exception( esc_html__( 'Coupon code already exists', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		self::apply_coupon_data( $coupon, $args );
		$coupon->save();

		return self::format_coupon( new \WC_Coupon( $coupon->get_id() ) );
	}

	public static function update_coupon( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$id     = (int) self::require_arg( $args, 'id' );
		$coupon = new \WC_Coupon( $id );
		if ( ! $coupon->get_id() ) {
			throw new Tool_Exception( esc_html__( 'Coupon not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		if ( array_key_exists( 'code', $args ) ) {
			$coupon->set_code( sanitize_text_field( (string) $args['code'] ) );
		}
		self::apply_coupon_data( $coupon, $args );
		$coupon->save();

		return self::format_coupon( new \WC_Coupon( $id ) );
	}

	public static function delete_coupon( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$id    = (int) self::require_arg( $args, 'id' );
		$force = (bool) self::arg_bool( $args, 'force', true );
		$ok    = wp_delete_post( $id, $force );
		if ( ! $ok ) {
			throw new Tool_Exception( esc_html__( 'Could not delete coupon', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}
		return [ 'id' => $id, 'deleted' => true, 'force' => $force ];
	}

	private static function apply_coupon_data( \WC_Coupon $coupon, array $args ): void {
		$map = [
			'discount_type'               => 'set_discount_type',
			'amount'                      => 'set_amount',
			'description'                 => 'set_description',
			'individual_use'              => 'set_individual_use',
			'product_ids'                 => 'set_product_ids',
			'excluded_product_ids'        => 'set_excluded_product_ids',
			'usage_limit'                 => 'set_usage_limit',
			'usage_limit_per_user'        => 'set_usage_limit_per_user',
			'limit_usage_to_x_items'      => 'set_limit_usage_to_x_items',
			'free_shipping'               => 'set_free_shipping',
			'product_categories'          => 'set_product_categories',
			'excluded_product_categories' => 'set_excluded_product_categories',
			'exclude_sale_items'          => 'set_exclude_sale_items',
			'minimum_amount'              => 'set_minimum_amount',
			'maximum_amount'              => 'set_maximum_amount',
			'email_restrictions'          => 'set_email_restrictions',
		];
		foreach ( $map as $key => $method ) {
			if ( array_key_exists( $key, $args ) && method_exists( $coupon, $method ) ) {
				$coupon->$method( $args[ $key ] );
			}
		}
		if ( array_key_exists( 'date_expires', $args ) ) {
			$ts = $args['date_expires'] ? strtotime( (string) $args['date_expires'] ) : null;
			$coupon->set_date_expires( $ts ?: null );
		}
	}

	private static function format_coupon( \WC_Coupon $coupon ): array {
		return [
			'id'                          => $coupon->get_id(),
			'code'                        => $coupon->get_code(),
			'discount_type'               => $coupon->get_discount_type(),
			'amount'                      => (float) $coupon->get_amount(),
			'description'                 => $coupon->get_description(),
			'date_created'                => $coupon->get_date_created() ? $coupon->get_date_created()->date_i18n( 'c' ) : null,
			'date_modified'               => $coupon->get_date_modified() ? $coupon->get_date_modified()->date_i18n( 'c' ) : null,
			'date_expires'                => $coupon->get_date_expires() ? $coupon->get_date_expires()->date_i18n( 'c' ) : null,
			'usage_count'                 => (int) $coupon->get_usage_count(),
			'individual_use'              => (bool) $coupon->get_individual_use(),
			'product_ids'                 => $coupon->get_product_ids(),
			'excluded_product_ids'        => $coupon->get_excluded_product_ids(),
			'usage_limit'                 => $coupon->get_usage_limit(),
			'usage_limit_per_user'        => $coupon->get_usage_limit_per_user(),
			'limit_usage_to_x_items'      => $coupon->get_limit_usage_to_x_items(),
			'free_shipping'               => (bool) $coupon->get_free_shipping(),
			'product_categories'          => $coupon->get_product_categories(),
			'excluded_product_categories' => $coupon->get_excluded_product_categories(),
			'exclude_sale_items'          => (bool) $coupon->get_exclude_sale_items(),
			'minimum_amount'              => (float) $coupon->get_minimum_amount(),
			'maximum_amount'              => (float) $coupon->get_maximum_amount(),
			'email_restrictions'          => $coupon->get_email_restrictions(),
			'used_by'                     => $coupon->get_used_by(),
		];
	}
}

add_action( 'store_mcp_register_tools', [ Coupons_Tools::class, 'register' ] );
