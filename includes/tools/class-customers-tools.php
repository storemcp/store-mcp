<?php
/**
 * Customers tools — WooCommerce customer CRUD (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Customers_Tools {
	use Tool_Base;

	public static function register( Tools_Registry $registry ): void {
		$address = [
			'type'       => 'object',
			'properties' => [
				'first_name' => [ 'type' => 'string' ],
				'last_name'  => [ 'type' => 'string' ],
				'company'    => [ 'type' => 'string' ],
				'address_1'  => [ 'type' => 'string' ],
				'address_2'  => [ 'type' => 'string' ],
				'city'       => [ 'type' => 'string' ],
				'state'      => [ 'type' => 'string' ],
				'postcode'   => [ 'type' => 'string' ],
				'country'    => [ 'type' => 'string' ],
				'email'      => [ 'type' => 'string' ],
				'phone'      => [ 'type' => 'string' ],
			],
			'additionalProperties' => false,
		];

		$registry->register( [
			'name'        => 'store_mcp_list_customers',
			'description' => 'List WooCommerce customers (users with the customer role and anyone with orders).',
			'tier'        => 'pro',
			'module'      => 'class-customers-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'search'      => [ 'type' => 'string' ],
					'email'       => [ 'type' => 'string' ],
					'role'        => [ 'type' => 'string', 'default' => 'customer' ],
					'date_after'  => [ 'type' => 'string' ],
					'date_before' => [ 'type' => 'string' ],
					'orderby'     => [ 'type' => 'string', 'enum' => [ 'id', 'registered', 'display_name', 'email' ], 'default' => 'id' ],
					'order'       => [ 'type' => 'string', 'enum' => [ 'asc', 'desc' ], 'default' => 'desc' ],
					'page'        => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page'    => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_customers' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_customer',
			'description' => 'Get a customer with billing/shipping, orders_count and total_spent.',
			'tier'        => 'pro',
			'module'      => 'class-customers-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_customer' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_customer',
			'description' => 'Create a WooCommerce customer.',
			'tier'        => 'pro',
			'module'      => 'class-customers-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'email' ],
				'properties' => [
					'email'      => [ 'type' => 'string', 'format' => 'email' ],
					'username'   => [ 'type' => 'string' ],
					'password'   => [ 'type' => 'string' ],
					'first_name' => [ 'type' => 'string' ],
					'last_name'  => [ 'type' => 'string' ],
					'billing'    => $address,
					'shipping'   => $address,
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_customer' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_customer',
			'description' => 'Update an existing customer.',
			'tier'        => 'pro',
			'module'      => 'class-customers-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'         => [ 'type' => 'integer' ],
					'email'      => [ 'type' => 'string', 'format' => 'email' ],
					'password'   => [ 'type' => 'string' ],
					'first_name' => [ 'type' => 'string' ],
					'last_name'  => [ 'type' => 'string' ],
					'billing'    => $address,
					'shipping'   => $address,
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_customer' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_customer',
			'description' => 'Delete a customer. Optionally reassign their content.',
			'tier'        => 'pro',
			'module'      => 'class-customers-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'       => [ 'type' => 'integer' ],
					'force'    => [ 'type' => 'boolean', 'default' => true ],
					'reassign' => [ 'type' => 'integer' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_customer' ] );
	}

	public static function list_customers( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'list_users' );

		$pagination = self::pagination( $args );
		$orderby    = (string) self::arg_enum( $args, 'orderby', [ 'id', 'registered', 'display_name', 'email' ], 'id' );
		$order      = strtoupper( (string) self::arg_enum( $args, 'order', [ 'asc', 'desc' ], 'desc' ) );

		$query_args = [
			'role'        => self::arg_string( $args, 'role', 'customer' ) ?: 'customer',
			'number'      => $pagination['per_page'],
			'paged'       => $pagination['page'],
			'orderby'     => $orderby,
			'order'       => $order,
			'count_total' => true,
		];
		$s = self::arg_string( $args, 'search', '' );
		if ( $s ) {
			$query_args['search']         = '*' . esc_attr( $s ) . '*';
			$query_args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
		}
		$email = self::arg_string( $args, 'email', '' );
		if ( $email ) {
			$query_args['search']         = '*' . esc_attr( $email ) . '*';
			$query_args['search_columns'] = [ 'user_email' ];
		}
		$after  = self::arg_string( $args, 'date_after', '' );
		$before = self::arg_string( $args, 'date_before', '' );
		if ( $after || $before ) {
			$dq = [];
			if ( $after )  $dq['after']  = $after;
			if ( $before ) $dq['before'] = $before;
			$query_args['date_query'] = [ $dq ];
		}

		$q     = new \WP_User_Query( $query_args );
		$items = array_map( static fn( \WP_User $u ) => self::format_user( $u, true ), $q->get_results() );

		return self::paginated( $items, (int) $q->get_total(), $pagination['page'], $pagination['per_page'] );
	}

	public static function get_customer( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'list_users' );

		$id       = (int) self::require_arg( $args, 'id' );
		$user     = get_userdata( $id );
		if ( ! $user ) {
			throw new Tool_Exception( esc_html__( 'Customer not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		$customer = new \WC_Customer( $id );

		return array_merge( self::format_user( $user, true ), [
			'billing'  => $customer->get_billing(),
			'shipping' => $customer->get_shipping(),
		] );
	}

	public static function create_customer( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'create_users' );

		$email    = sanitize_email( (string) self::require_arg( $args, 'email' ) );
		$username = self::arg_string( $args, 'username', '' );
		if ( '' === $username ) {
			$username = wc_create_new_customer_username( $email );
		}
		$password = self::arg_string( $args, 'password', '' );
		if ( '' === $password ) {
			$password = wp_generate_password( 20, true, true );
		}

		$user_id = wc_create_new_customer( $email, $username, $password );
		if ( is_wp_error( $user_id ) ) {
			throw new Tool_Exception( esc_html( $user_id->get_error_message() ), Server::ERR_TOOL_FAILED );
		}

		$customer = new \WC_Customer( $user_id );
		self::apply_customer_data( $customer, $args );
		$customer->save();

		return self::get_customer( [ 'id' => $user_id ], $context );
	}

	public static function update_customer( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();

		$id = (int) self::require_arg( $args, 'id' );
		Permissions::require_cap( $context, 'edit_user', $id );

		$customer = new \WC_Customer( $id );
		if ( ! $customer->get_id() ) {
			throw new Tool_Exception( esc_html__( 'Customer not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		self::apply_customer_data( $customer, $args );
		$customer->save();

		if ( ! empty( $args['password'] ) ) {
			wp_set_password( (string) $args['password'], $id );
		}

		return self::get_customer( [ 'id' => $id ], $context );
	}

	public static function delete_customer( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();

		$id = (int) self::require_arg( $args, 'id' );
		Permissions::require_cap( $context, 'delete_users' );

		if ( $id === $context->user_id ) {
			throw new Tool_Exception( esc_html__( 'Cannot delete yourself', 'store-mcp' ), Server::ERR_FORBIDDEN );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		$reassign = self::arg_int( $args, 'reassign' );
		$ok       = is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id, $reassign ?: null );

		if ( ! $ok ) {
			throw new Tool_Exception( esc_html__( 'Could not delete customer', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}
		return [ 'id' => $id, 'deleted' => true ];
	}

	private static function apply_customer_data( \WC_Customer $customer, array $args ): void {
		if ( array_key_exists( 'email', $args ) )      $customer->set_email( sanitize_email( (string) $args['email'] ) );
		if ( array_key_exists( 'first_name', $args ) ) $customer->set_first_name( sanitize_text_field( (string) $args['first_name'] ) );
		if ( array_key_exists( 'last_name', $args ) )  $customer->set_last_name( sanitize_text_field( (string) $args['last_name'] ) );

		if ( isset( $args['billing'] ) && is_array( $args['billing'] ) ) {
			foreach ( $args['billing'] as $k => $v ) {
				$method = 'set_billing_' . sanitize_key( (string) $k );
				if ( method_exists( $customer, $method ) ) {
					$customer->$method( sanitize_text_field( (string) $v ) );
				}
			}
		}
		if ( isset( $args['shipping'] ) && is_array( $args['shipping'] ) ) {
			foreach ( $args['shipping'] as $k => $v ) {
				$method = 'set_shipping_' . sanitize_key( (string) $k );
				if ( method_exists( $customer, $method ) ) {
					$customer->$method( sanitize_text_field( (string) $v ) );
				}
			}
		}
	}
}

add_action( 'store_mcp_register_tools', [ Customers_Tools::class, 'register' ] );
