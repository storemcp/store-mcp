<?php
/**
 * Users tools — list/get (FREE), create/update/delete (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Users_Tools {
	use Tool_Base;

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_users',
			'description' => 'List site users with filters and pagination.',
			'tier'        => 'free',
			'module'      => 'class-users-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'search'   => [ 'type' => 'string' ],
					'role'     => [ 'type' => 'string' ],
					'orderby'  => [ 'type' => 'string', 'enum' => [ 'id', 'login', 'email', 'registered', 'display_name' ], 'default' => 'id' ],
					'order'    => [ 'type' => 'string', 'enum' => [ 'asc', 'desc' ], 'default' => 'asc' ],
					'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_users' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_user',
			'description' => 'Get a user by id.',
			'tier'        => 'free',
			'module'      => 'class-users-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_user' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_user',
			'description' => 'Create a new user (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-users-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'username', 'email' ],
				'properties' => [
					'username'   => [ 'type' => 'string' ],
					'email'      => [ 'type' => 'string', 'format' => 'email' ],
					'password'   => [ 'type' => 'string', 'description' => 'If omitted, a secure random password is generated.' ],
					'first_name' => [ 'type' => 'string' ],
					'last_name'  => [ 'type' => 'string' ],
					'role'       => [ 'type' => 'string', 'default' => 'subscriber' ],
					'send_email' => [ 'type' => 'boolean', 'default' => false, 'description' => 'Send the new-user notification email.' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_user' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_user',
			'description' => 'Update an existing user (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-users-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'         => [ 'type' => 'integer' ],
					'email'      => [ 'type' => 'string', 'format' => 'email' ],
					'password'   => [ 'type' => 'string' ],
					'first_name' => [ 'type' => 'string' ],
					'last_name'  => [ 'type' => 'string' ],
					'display_name' => [ 'type' => 'string' ],
					'role'       => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_user' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_user',
			'description' => 'Delete a user (PRO). Optionally reassign their content.',
			'tier'        => 'pro',
			'module'      => 'class-users-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'       => [ 'type' => 'integer' ],
					'reassign' => [ 'type' => 'integer', 'description' => 'User id to reassign posts to.' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_user' ] );
	}

	public static function list_users( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'list_users' );

		$pagination = self::pagination( $args );
		$orderby    = (string) self::arg_enum( $args, 'orderby', [ 'id', 'login', 'email', 'registered', 'display_name' ], 'id' );
		$order      = strtoupper( (string) self::arg_enum( $args, 'order', [ 'asc', 'desc' ], 'asc' ) );

		$q = new \WP_User_Query( [
			'search'  => self::arg_string( $args, 'search', '' ) ? '*' . self::arg_string( $args, 'search' ) . '*' : '',
			'role'    => self::arg_string( $args, 'role', '' ) ?: '',
			'orderby' => $orderby,
			'order'   => $order,
			'number'  => $pagination['per_page'],
			'paged'   => $pagination['page'],
			'count_total' => true,
		] );

		$items = array_map( static fn( \WP_User $u ) => self::format_user( $u ), $q->get_results() );
		return self::paginated( $items, (int) $q->get_total(), $pagination['page'], $pagination['per_page'] );
	}

	public static function get_user( array $args, AuthContext $context ): array {
		$id = (int) self::require_arg( $args, 'id' );

		if ( $context->user_id !== $id ) {
			Permissions::require_cap( $context, 'list_users' );
		}

		$user = get_userdata( $id );
		if ( ! $user ) {
			throw new Tool_Exception( esc_html__( 'User not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		return self::format_user( $user, true );
	}

	public static function create_user( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'create_users' );

		$username = sanitize_user( (string) self::require_arg( $args, 'username' ), true );
		$email    = sanitize_email( (string) self::require_arg( $args, 'email' ) );

		if ( '' === $username || ! is_email( $email ) ) {
			throw new Tool_Exception( esc_html__( 'Invalid username or email', 'store-mcp' ), Server::RPC_INVALID_PARAMS );
		}

		$password = self::arg_string( $args, 'password', '' );
		if ( '' === $password ) {
			$password = wp_generate_password( 20, true, true );
		}

		$id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $id ) ) {
			throw new Tool_Exception( esc_html( $id->get_error_message() ), Server::ERR_TOOL_FAILED );
		}

		$extra = [ 'ID' => $id ];
		if ( ! empty( $args['first_name'] ) ) $extra['first_name']   = sanitize_text_field( (string) $args['first_name'] );
		if ( ! empty( $args['last_name'] ) )  $extra['last_name']    = sanitize_text_field( (string) $args['last_name'] );
		if ( ! empty( $args['role'] ) )       $extra['role']         = sanitize_key( (string) $args['role'] );
		wp_update_user( $extra );

		if ( self::arg_bool( $args, 'send_email', false ) ) {
			wp_new_user_notification( $id, null, 'both' );
		}

		return self::format_user( get_userdata( $id ), true );
	}

	public static function update_user( array $args, AuthContext $context ): array {
		$id = (int) self::require_arg( $args, 'id' );
		Permissions::require_cap( $context, 'edit_user', $id );

		$data = [ 'ID' => $id ];
		if ( array_key_exists( 'email', $args ) )        $data['user_email']   = sanitize_email( (string) $args['email'] );
		if ( array_key_exists( 'password', $args ) )     $data['user_pass']    = (string) $args['password'];
		if ( array_key_exists( 'first_name', $args ) )   $data['first_name']   = sanitize_text_field( (string) $args['first_name'] );
		if ( array_key_exists( 'last_name', $args ) )    $data['last_name']    = sanitize_text_field( (string) $args['last_name'] );
		if ( array_key_exists( 'display_name', $args ) ) $data['display_name'] = sanitize_text_field( (string) $args['display_name'] );
		if ( array_key_exists( 'role', $args ) )         $data['role']         = sanitize_key( (string) $args['role'] );

		$result = wp_update_user( $data );
		if ( is_wp_error( $result ) ) {
			throw new Tool_Exception( esc_html( $result->get_error_message() ), Server::ERR_TOOL_FAILED );
		}

		return self::format_user( get_userdata( $id ), true );
	}

	public static function delete_user( array $args, AuthContext $context ): array {
		$id = (int) self::require_arg( $args, 'id' );
		Permissions::require_cap( $context, 'delete_users' );

		if ( $id === $context->user_id ) {
			throw new Tool_Exception( esc_html__( 'Cannot delete yourself', 'store-mcp' ), Server::ERR_FORBIDDEN );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$reassign = self::arg_int( $args, 'reassign' );
		$ok       = is_multisite()
			? wpmu_delete_user( $id )
			: wp_delete_user( $id, $reassign ?: null );

		if ( ! $ok ) {
			throw new Tool_Exception( esc_html__( 'Could not delete user', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}
		return [ 'id' => $id, 'deleted' => true, 'reassign' => $reassign ];
	}
}

add_action( 'store_mcp_register_tools', [ Users_Tools::class, 'register' ] );
