<?php
/**
 * Authentication layer.
 *
 * Resolves an AuthContext from the request using, in order:
 *   1. OAuth 2.1 access token  — Authorization: Bearer smcp_at_xxx   (issued by our OAuth server)
 *   2. StoreMCP static API key — Authorization: Bearer smcp_xxx_yyy  OR  X-StoreMCP-Key
 *   3. WP Application Passwords (handled natively before we run)
 *   4. Third-party OAuth via `store_mcp_oauth_authenticate` filter
 *
 * @package StoreMCP
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Auth {

	const KEY_PREFIX = 'smcp_';

	public function authenticate( \WP_REST_Request $request ) {
		$bearer = $this->extract_bearer( $request );

		// 1. OAuth access token (issued by our own OAuth server).
		if ( '' !== $bearer && str_starts_with( $bearer, OAuth::ACCESS_PREFIX ) ) {
			$resolved = Plugin::instance()->oauth->resolve_access_token( $bearer );
			if ( ! $resolved ) {
				return new \WP_Error(
					'store_mcp_invalid_token',
					__( 'Invalid or expired access token', 'store-mcp' ),
					[ 'status' => 401 ]
				);
			}
			$user = get_userdata( $resolved['user_id'] );
			if ( ! $user ) {
				return new \WP_Error(
					'store_mcp_orphan_token',
					__( 'Token owner no longer exists', 'store-mcp' ),
					[ 'status' => 401 ]
				);
			}
			wp_set_current_user( $user->ID );
			$context = new AuthContext( [
				'user_id' => $user->ID,
				'key_id'  => 'oauth:' . $resolved['client_id'],
				'method'  => 'oauth',
				'roles'   => (array) $user->roles,
				'scopes'  => $resolved['scopes'],
			] );
			return apply_filters( 'store_mcp_auth_context', $context, $request );
		}

		// 2. StoreMCP static API key.
		$key = $this->extract_static_key( $request, $bearer );
		if ( '' !== $key ) {
			$context = $this->resolve_api_key( $key );
			if ( is_wp_error( $context ) ) {
				return $context;
			}
			return apply_filters( 'store_mcp_auth_context', $context, $request );
		}

		// 3. WP Application Passwords (resolved natively by WP before we run).
		if ( get_option( 'store_mcp_allow_app_passwords', true ) ) {
			$user_id = get_current_user_id();
			if ( $user_id > 0 ) {
				$user    = get_userdata( $user_id );
				$context = new AuthContext( [
					'user_id' => $user_id,
					'key_id'  => 'app_password',
					'method'  => 'application_password',
					'roles'   => $user ? (array) $user->roles : [],
				] );
				return apply_filters( 'store_mcp_auth_context', $context, $request );
			}
		}

		// 4. Third-party OAuth providers via filter.
		$oauth = apply_filters( 'store_mcp_oauth_authenticate', null, $request );
		if ( $oauth instanceof AuthContext ) {
			return $oauth;
		}

		return new \WP_Error(
			'store_mcp_unauthorized',
			__( 'Authentication required. Connect via OAuth or provide an API key.', 'store-mcp' ),
			[ 'status' => 401 ]
		);
	}

	private function extract_bearer( \WP_REST_Request $request ): string {
		$auth = (string) $request->get_header( 'Authorization' );
		if ( '' !== $auth && stripos( $auth, 'Bearer ' ) === 0 ) {
			return trim( substr( $auth, 7 ) );
		}
		return '';
	}

	private function extract_static_key( \WP_REST_Request $request, string $bearer ): string {
		$header = (string) $request->get_header( 'X-StoreMCP-Key' );
		if ( '' !== $header ) {
			return trim( $header );
		}
		if ( '' !== $bearer && str_starts_with( $bearer, self::KEY_PREFIX ) && ! str_starts_with( $bearer, OAuth::ACCESS_PREFIX ) ) {
			return $bearer;
		}
		return '';
	}

	private function resolve_api_key( string $raw_key ) {
		global $wpdb;

		if ( ! str_starts_with( $raw_key, self::KEY_PREFIX ) ) {
			return new \WP_Error( 'store_mcp_invalid_key', __( 'Invalid API key format', 'store-mcp' ), [ 'status' => 401 ] );
		}

		$parts = explode( '_', $raw_key, 3 );
		if ( count( $parts ) < 3 ) {
			return new \WP_Error( 'store_mcp_invalid_key', __( 'Invalid API key format', 'store-mcp' ), [ 'status' => 401 ] );
		}
		$key_id = $parts[1];

		$table = $wpdb->prefix . 'storemcp_api_keys';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE key_id = %s", $key_id ) );

		if ( ! $row ) {
			return new \WP_Error( 'store_mcp_invalid_key', __( 'Invalid API key', 'store-mcp' ), [ 'status' => 401 ] );
		}
		if ( $row->revoked_at ) {
			return new \WP_Error( 'store_mcp_revoked_key', __( 'API key has been revoked', 'store-mcp' ), [ 'status' => 401 ] );
		}
		if ( ! wp_check_password( $raw_key, $row->key_hash ) ) {
			return new \WP_Error( 'store_mcp_invalid_key', __( 'Invalid API key', 'store-mcp' ), [ 'status' => 401 ] );
		}

		$user = get_userdata( (int) $row->user_id );
		if ( ! $user ) {
			return new \WP_Error( 'store_mcp_orphan_key', __( 'API key owner no longer exists', 'store-mcp' ), [ 'status' => 401 ] );
		}

		wp_set_current_user( $user->ID );

		$wpdb->update(
			$table,
			[ 'last_used_at' => current_time( 'mysql', true ) ],
			[ 'id' => (int) $row->id ],
			[ '%s' ],
			[ '%d' ]
		);

		$scopes = $row->scopes ? json_decode( $row->scopes, true ) : [];

		return new AuthContext( [
			'user_id' => (int) $row->user_id,
			'key_id'  => $row->key_id,
			'method'  => 'api_key',
			'roles'   => (array) $user->roles,
			'scopes'  => is_array( $scopes ) ? $scopes : [],
			'label'   => (string) $row->label,
		] );
	}

	public function generate_api_key( int $user_id, string $label = '', array $scopes = [] ): array {
		global $wpdb;

		$key_id     = wp_generate_password( 16, false, false );
		$secret     = wp_generate_password( 40, false, false );
		$plain_key  = self::KEY_PREFIX . $key_id . '_' . $secret;
		$hash       = wp_hash_password( $plain_key );

		$wpdb->insert(
			$wpdb->prefix . 'storemcp_api_keys',
			[
				'key_id'     => $key_id,
				'key_hash'   => $hash,
				'label'      => sanitize_text_field( $label ),
				'user_id'    => $user_id,
				'scopes'     => wp_json_encode( $scopes ),
				'created_at' => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%d', '%s', '%s' ]
		);

		return [
			'id'         => (int) $wpdb->insert_id,
			'key_id'     => $key_id,
			'plain_key'  => $plain_key,
			'created_at' => current_time( 'mysql', true ),
		];
	}

	public function revoke_api_key( int $id ): bool {
		global $wpdb;
		$result = $wpdb->update(
			$wpdb->prefix . 'storemcp_api_keys',
			[ 'revoked_at' => current_time( 'mysql', true ) ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
		return false !== $result;
	}

	public function delete_api_key( int $id ): bool {
		global $wpdb;
		$result = $wpdb->delete(
			$wpdb->prefix . 'storemcp_api_keys',
			[ 'id' => $id ],
			[ '%d' ]
		);
		return false !== $result;
	}

	public function list_api_keys(): array {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT id, key_id, label, user_id, created_at, last_used_at, revoked_at FROM {$wpdb->prefix}storemcp_api_keys ORDER BY id DESC" );
		return $rows ?: [];
	}
}

final class AuthContext {

	public int $user_id = 0;
	public string $key_id = '';
	public string $method = '';
	public array $roles = [];
	public array $scopes = [];
	public string $label = '';

	public function __construct( array $data = [] ) {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}
	}

	public function user_can( string $capability, ...$args ): bool {
		if ( $this->user_id <= 0 ) {
			return false;
		}
		return user_can( $this->user_id, $capability, ...$args );
	}

	public function bucket(): string {
		return $this->key_id ?: ( 'user_' . $this->user_id );
	}
}
