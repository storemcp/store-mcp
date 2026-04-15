<?php
/**
 * OAuth 2.1 authorization server for MCP clients.
 *
 * Implements MCP Authorization spec (protocol 2025-03-26):
 *   - RFC 8414  OAuth 2.0 Authorization Server Metadata
 *   - RFC 9728  OAuth 2.0 Protected Resource Metadata
 *   - RFC 7591  Dynamic Client Registration
 *   - RFC 7636  PKCE (S256 required)
 *   - RFC 6749  Authorization Code + Refresh Token grants
 *
 * Discovery endpoints (served via `init` since WP REST cannot handle `.well-known`):
 *   GET  /.well-known/oauth-protected-resource
 *   GET  /.well-known/oauth-authorization-server
 *
 * Interactive authorize screen (HTML, served via `init`):
 *   GET  /store-mcp/authorize         — consent form (redirects to wp-login if needed)
 *   POST /store-mcp/authorize         — form submit (allow / deny)
 *
 * OAuth REST routes (JSON):
 *   POST /wp-json/store-mcp/v1/oauth/register  — Dynamic Client Registration
 *   POST /wp-json/store-mcp/v1/oauth/token     — token exchange / refresh
 *   POST /wp-json/store-mcp/v1/oauth/revoke    — RFC 7009 token revocation
 *
 * @package StoreMCP
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class OAuth {

	const CLIENT_PREFIX  = 'smcp_client_';
	const ACCESS_PREFIX  = 'smcp_at_';
	const REFRESH_PREFIX = 'smcp_rt_';

	const CODE_TTL    = 60;               // 1 min
	const ACCESS_TTL  = 3600;             // 1 h
	const REFRESH_TTL = 2592000;          // 30 d

	const AUTHORIZE_PATH = '/store-mcp/authorize';
	const DEFAULT_SCOPE  = 'mcp';

	public function bootstrap(): void {
		add_action( 'init', [ $this, 'maybe_handle_request' ], 1 );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	public function register_rest_routes(): void {
		$ns = STORE_MCP_REST_NAMESPACE;

		register_rest_route( $ns, '/oauth/register', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_register' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $ns, '/oauth/token', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_token' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $ns, '/oauth/revoke', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_revoke' ],
			'permission_callback' => '__return_true',
		] );
	}

	// ---------------------------------------------------------------------
	//  Public API consumed by Auth / admin
	// ---------------------------------------------------------------------

	public function resolve_access_token( string $raw ): ?array {
		if ( ! str_starts_with( $raw, self::ACCESS_PREFIX ) ) {
			return null;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'storemcp_oauth_tokens';
		$hash  = self::hash_token( $raw );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token_hash = %s", $hash ) );
		if ( ! $row ) {
			return null;
		}
		if ( $row->revoked_at ) {
			return null;
		}
		if ( strtotime( $row->expires_at ) < time() ) {
			return null;
		}

		$wpdb->update(
			$table,
			[ 'last_used_at' => current_time( 'mysql', true ) ],
			[ 'id' => (int) $row->id ],
			[ '%s' ],
			[ '%d' ]
		);

		return [
			'id'        => (int) $row->id,
			'user_id'   => (int) $row->user_id,
			'client_id' => (string) $row->client_id,
			'scopes'    => $row->scopes ? (array) json_decode( $row->scopes, true ) : [],
		];
	}

	public function list_clients(): array {
		global $wpdb;
		$clients = $wpdb->get_results(
			"SELECT id, client_id, client_name, redirect_uris, created_at, last_used_at
			 FROM {$wpdb->prefix}storemcp_oauth_clients
			 ORDER BY id DESC"
		);
		if ( ! $clients ) {
			return [];
		}
		$token_table = $wpdb->prefix . 'storemcp_oauth_tokens';
		foreach ( $clients as $c ) {
			$c->active_tokens = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$token_table} WHERE client_id = %s AND revoked_at IS NULL AND expires_at > %s",
				$c->client_id,
				current_time( 'mysql', true )
			) );
		}
		return $clients;
	}

	public function revoke_client( string $client_id ): bool {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}storemcp_oauth_tokens SET revoked_at = %s WHERE client_id = %s AND revoked_at IS NULL",
			$now,
			$client_id
		) );
		$deleted = $wpdb->delete(
			$wpdb->prefix . 'storemcp_oauth_clients',
			[ 'client_id' => $client_id ],
			[ '%s' ]
		);
		return false !== $deleted;
	}

	public static function issuer(): string {
		return untrailingslashit( home_url( '/' ) );
	}

	public static function resource_url(): string {
		return rest_url( STORE_MCP_REST_NAMESPACE . '/mcp' );
	}

	public static function protected_resource_metadata_url(): string {
		return self::issuer() . '/.well-known/oauth-protected-resource';
	}

	// ---------------------------------------------------------------------
	//  Request dispatcher (init hook)
	// ---------------------------------------------------------------------

	public function maybe_handle_request(): void {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) parse_url( $uri, PHP_URL_PATH );
		if ( '' === $path ) {
			return;
		}

		if ( $path === '/.well-known/oauth-protected-resource'
			|| str_starts_with( $path, '/.well-known/oauth-protected-resource/' ) ) {
			$this->serve_protected_resource_metadata();
			exit;
		}

		if ( $path === '/.well-known/oauth-authorization-server'
			|| str_starts_with( $path, '/.well-known/oauth-authorization-server/' ) ) {
			$this->serve_authorization_server_metadata();
			exit;
		}

		if ( $path === self::AUTHORIZE_PATH || $path === self::AUTHORIZE_PATH . '/' ) {
			$this->handle_authorize();
			exit;
		}
	}

	// ---------------------------------------------------------------------
	//  Discovery metadata
	// ---------------------------------------------------------------------

	private function serve_protected_resource_metadata(): void {
		$meta = [
			'resource'                 => self::resource_url(),
			'authorization_servers'    => [ self::issuer() ],
			'bearer_methods_supported' => [ 'header' ],
			'scopes_supported'         => [ self::DEFAULT_SCOPE ],
			'resource_name'            => apply_filters( 'store_mcp_server_name', 'StoreMCP' ),
			'resource_documentation'   => 'https://storemcp.io/docs',
		];
		$this->send_json( $meta );
	}

	private function serve_authorization_server_metadata(): void {
		$issuer = self::issuer();
		$meta   = [
			'issuer'                                  => $issuer,
			'authorization_endpoint'                  => $issuer . self::AUTHORIZE_PATH,
			'token_endpoint'                          => rest_url( STORE_MCP_REST_NAMESPACE . '/oauth/token' ),
			'registration_endpoint'                   => rest_url( STORE_MCP_REST_NAMESPACE . '/oauth/register' ),
			'revocation_endpoint'                     => rest_url( STORE_MCP_REST_NAMESPACE . '/oauth/revoke' ),
			'response_types_supported'                => [ 'code' ],
			'response_modes_supported'                => [ 'query' ],
			'grant_types_supported'                   => [ 'authorization_code', 'refresh_token' ],
			'code_challenge_methods_supported'        => [ 'S256' ],
			'token_endpoint_auth_methods_supported'   => [ 'none', 'client_secret_basic', 'client_secret_post' ],
			'revocation_endpoint_auth_methods_supported' => [ 'none', 'client_secret_basic', 'client_secret_post' ],
			'scopes_supported'                        => [ self::DEFAULT_SCOPE ],
			'service_documentation'                   => 'https://storemcp.io/docs',
		];
		$this->send_json( $meta );
	}

	private function send_json( array $data, int $status = 200 ): void {
		nocache_headers();
		status_header( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Authorization, Content-Type' );
		echo wp_json_encode( $data );
	}

	// ---------------------------------------------------------------------
	//  Dynamic Client Registration (RFC 7591)
	// ---------------------------------------------------------------------

	public function handle_register( \WP_REST_Request $request ) {
		$body = json_decode( $request->get_body(), true );
		if ( ! is_array( $body ) ) {
			return $this->oauth_error( 'invalid_client_metadata', 'Body must be JSON', 400 );
		}

		$redirect_uris = isset( $body['redirect_uris'] ) && is_array( $body['redirect_uris'] )
			? array_values( array_filter( array_map( 'strval', $body['redirect_uris'] ) ) )
			: [];
		if ( empty( $redirect_uris ) ) {
			return $this->oauth_error( 'invalid_redirect_uri', 'redirect_uris is required', 400 );
		}
		foreach ( $redirect_uris as $uri ) {
			if ( ! $this->is_allowed_redirect_uri( $uri ) ) {
				return $this->oauth_error( 'invalid_redirect_uri', 'redirect_uri not allowed: ' . $uri, 400 );
			}
		}

		$client_name        = isset( $body['client_name'] ) ? sanitize_text_field( (string) $body['client_name'] ) : 'MCP Client';
		$client_uri         = isset( $body['client_uri'] ) ? esc_url_raw( (string) $body['client_uri'] ) : '';
		$logo_uri           = isset( $body['logo_uri'] ) ? esc_url_raw( (string) $body['logo_uri'] ) : '';
		$token_auth_method  = isset( $body['token_endpoint_auth_method'] ) ? (string) $body['token_endpoint_auth_method'] : 'none';
		$grant_types        = isset( $body['grant_types'] ) && is_array( $body['grant_types'] )
			? array_map( 'strval', $body['grant_types'] )
			: [ 'authorization_code', 'refresh_token' ];
		$response_types     = isset( $body['response_types'] ) && is_array( $body['response_types'] )
			? array_map( 'strval', $body['response_types'] )
			: [ 'code' ];
		$scope              = isset( $body['scope'] ) ? (string) $body['scope'] : self::DEFAULT_SCOPE;

		$allowed_auth = [ 'none', 'client_secret_basic', 'client_secret_post' ];
		if ( ! in_array( $token_auth_method, $allowed_auth, true ) ) {
			$token_auth_method = 'none';
		}

		$client_id     = self::CLIENT_PREFIX . wp_generate_password( 24, false, false );
		$client_secret = null;
		$secret_hash   = null;
		if ( 'none' !== $token_auth_method ) {
			$client_secret = wp_generate_password( 48, false, false );
			$secret_hash   = self::hash_token( $client_secret );
		}

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'storemcp_oauth_clients',
			[
				'client_id'                  => $client_id,
				'client_secret_hash'         => $secret_hash,
				'client_name'                => $client_name,
				'client_uri'                 => $client_uri,
				'logo_uri'                   => $logo_uri,
				'redirect_uris'              => wp_json_encode( $redirect_uris ),
				'grant_types'                => wp_json_encode( $grant_types ),
				'response_types'             => wp_json_encode( $response_types ),
				'token_endpoint_auth_method' => $token_auth_method,
				'scope'                      => $scope,
				'metadata'                   => wp_json_encode( $body ),
				'created_at'                 => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		$response = [
			'client_id'                  => $client_id,
			'client_id_issued_at'        => time(),
			'redirect_uris'              => $redirect_uris,
			'grant_types'                => $grant_types,
			'response_types'             => $response_types,
			'token_endpoint_auth_method' => $token_auth_method,
			'client_name'                => $client_name,
			'scope'                      => $scope,
		];
		if ( null !== $client_secret ) {
			$response['client_secret']            = $client_secret;
			$response['client_secret_expires_at'] = 0;
		}

		return new \WP_REST_Response( $response, 201 );
	}

	private function is_allowed_redirect_uri( string $uri ): bool {
		$parts = wp_parse_url( $uri );
		if ( ! $parts || empty( $parts['scheme'] ) ) {
			return false;
		}
		$scheme = strtolower( $parts['scheme'] );
		$host   = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';

		if ( 'https' === $scheme ) {
			return true;
		}
		if ( 'http' === $scheme && ( 'localhost' === $host || '127.0.0.1' === $host || '[::1]' === $host ) ) {
			return true;
		}
		// Custom schemes for native apps (e.g. claude://) — allow non-http/https with at least 3 chars.
		if ( $scheme && ! in_array( $scheme, [ 'http', 'javascript', 'data', 'file' ], true ) && strlen( $scheme ) >= 3 ) {
			return true;
		}
		return false;
	}

	// ---------------------------------------------------------------------
	//  Authorize endpoint (consent UI)
	// ---------------------------------------------------------------------

	private function handle_authorize(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';

		if ( 'POST' === $method ) {
			$this->handle_authorize_submit();
			return;
		}

		$params = $this->collect_authorize_params( $_GET );

		$validation = $this->validate_authorize_params( $params );
		if ( is_wp_error( $validation ) ) {
			$this->render_error_page(
				__( 'Invalid authorization request', 'store-mcp' ),
				$validation->get_error_message()
			);
			return;
		}
		$client = $validation;

		// Require WP login. If not logged in, bounce to wp-login.php with redirect_to back here.
		if ( ! is_user_logged_in() ) {
			$return_to = self::issuer() . self::AUTHORIZE_PATH . '?' . http_build_query( $params );
			wp_safe_redirect( wp_login_url( $return_to ) );
			exit;
		}

		$required_cap = self::min_capability();
		if ( ! current_user_can( $required_cap ) ) {
			$this->render_error_page(
				__( 'Not allowed', 'store-mcp' ),
				sprintf(
					/* translators: %s: capability name */
					__( 'Your user does not have the "%s" capability required to authorize MCP clients for this site.', 'store-mcp' ),
					$required_cap
				)
			);
			return;
		}

		$this->render_consent_page( $client, $params );
	}

	private function handle_authorize_submit(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'store-mcp' ), 401 );
		}

		$params = $this->collect_authorize_params( $_POST );
		check_admin_referer( 'store_mcp_oauth_consent' );

		$validation = $this->validate_authorize_params( $params );
		if ( is_wp_error( $validation ) ) {
			$this->render_error_page(
				__( 'Invalid authorization request', 'store-mcp' ),
				$validation->get_error_message()
			);
			return;
		}
		$client = $validation;

		if ( ! current_user_can( self::min_capability() ) ) {
			wp_die( esc_html__( 'Forbidden', 'store-mcp' ), 403 );
		}

		$decision = isset( $_POST['decision'] ) ? (string) $_POST['decision'] : 'deny';

		if ( 'allow' !== $decision ) {
			$redirect = $this->append_redirect( $params['redirect_uri'], [
				'error'             => 'access_denied',
				'error_description' => 'User denied the authorization request',
				'state'             => $params['state'],
			] );
			wp_redirect( $redirect );
			exit;
		}

		$code       = wp_generate_password( 48, false, false );
		$code_hash  = self::hash_token( $code );
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + self::CODE_TTL );

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'storemcp_oauth_codes',
			[
				'code_hash'             => $code_hash,
				'client_id'             => $params['client_id'],
				'user_id'               => get_current_user_id(),
				'redirect_uri'          => $params['redirect_uri'],
				'code_challenge'        => $params['code_challenge'],
				'code_challenge_method' => $params['code_challenge_method'],
				'scope'                 => $params['scope'],
				'resource'              => $params['resource'],
				'expires_at'            => $expires_at,
				'created_at'            => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		$redirect = $this->append_redirect( $params['redirect_uri'], [
			'code'  => $code,
			'state' => $params['state'],
		] );
		wp_redirect( $redirect );
		exit;
	}

	private function collect_authorize_params( array $src ): array {
		$src = wp_unslash( $src );
		return [
			'response_type'         => isset( $src['response_type'] ) ? (string) $src['response_type'] : '',
			'client_id'             => isset( $src['client_id'] ) ? (string) $src['client_id'] : '',
			'redirect_uri'          => isset( $src['redirect_uri'] ) ? (string) $src['redirect_uri'] : '',
			'scope'                 => isset( $src['scope'] ) ? (string) $src['scope'] : self::DEFAULT_SCOPE,
			'state'                 => isset( $src['state'] ) ? (string) $src['state'] : '',
			'code_challenge'        => isset( $src['code_challenge'] ) ? (string) $src['code_challenge'] : '',
			'code_challenge_method' => isset( $src['code_challenge_method'] ) ? (string) $src['code_challenge_method'] : '',
			'resource'              => isset( $src['resource'] ) ? (string) $src['resource'] : '',
		];
	}

	private function validate_authorize_params( array $p ) {
		if ( 'code' !== $p['response_type'] ) {
			return new \WP_Error( 'unsupported_response_type', __( 'Only response_type=code is supported', 'store-mcp' ) );
		}
		if ( '' === $p['client_id'] ) {
			return new \WP_Error( 'invalid_request', __( 'Missing client_id', 'store-mcp' ) );
		}
		if ( '' === $p['redirect_uri'] ) {
			return new \WP_Error( 'invalid_request', __( 'Missing redirect_uri', 'store-mcp' ) );
		}
		if ( '' === $p['code_challenge'] || 'S256' !== $p['code_challenge_method'] ) {
			return new \WP_Error( 'invalid_request', __( 'PKCE code_challenge with S256 is required', 'store-mcp' ) );
		}

		$client = $this->get_client( $p['client_id'] );
		if ( ! $client ) {
			return new \WP_Error( 'invalid_client', __( 'Unknown client', 'store-mcp' ) );
		}

		$redirect_uris = (array) json_decode( $client->redirect_uris, true );
		if ( ! in_array( $p['redirect_uri'], $redirect_uris, true ) ) {
			return new \WP_Error( 'invalid_redirect_uri', __( 'redirect_uri does not match registered URIs', 'store-mcp' ) );
		}

		return $client;
	}

	private function get_client( string $client_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'storemcp_oauth_clients';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE client_id = %s", $client_id ) );
	}

	private function append_redirect( string $base, array $params ): string {
		$params = array_filter( $params, static fn( $v ) => '' !== $v && null !== $v );
		$sep    = ( false === strpos( $base, '?' ) ) ? '?' : '&';
		return $base . $sep . http_build_query( $params );
	}

	private function render_consent_page( $client, array $params ): void {
		$user        = wp_get_current_user();
		$client_name = $client->client_name ?: __( 'Unknown MCP client', 'store-mcp' );
		$client_uri  = $client->client_uri ?: '';
		$logo_uri    = $client->logo_uri ?: '';
		$view        = STORE_MCP_PATH . 'admin/views/oauth-consent.php';

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		if ( file_exists( $view ) ) {
			include $view;
			return;
		}

		// Ultra-minimal fallback if view is missing.
		echo '<!doctype html><meta charset=utf-8><title>' . esc_html__( 'Authorize', 'store-mcp' ) . '</title>';
		echo '<form method=post action=' . esc_url( self::issuer() . self::AUTHORIZE_PATH ) . '>';
		wp_nonce_field( 'store_mcp_oauth_consent' );
		foreach ( $params as $k => $v ) {
			echo '<input type=hidden name=' . esc_attr( $k ) . ' value=' . esc_attr( $v ) . '>';
		}
		echo '<p>' . esc_html( sprintf( __( '%s wants to access your site as %s.', 'store-mcp' ), $client_name, $user->user_login ) ) . '</p>';
		echo '<button name=decision value=allow>' . esc_html__( 'Allow', 'store-mcp' ) . '</button> ';
		echo '<button name=decision value=deny>' . esc_html__( 'Deny', 'store-mcp' ) . '</button>';
		echo '</form>';
	}

	private function render_error_page( string $title, string $message ): void {
		nocache_headers();
		status_header( 400 );
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
<meta charset="utf-8">
<title><?php echo esc_html( $title ); ?></title>
<style>body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f6f7f7;margin:0;padding:40px;color:#1d2327}.box{max-width:480px;margin:0 auto;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:32px}h1{font-size:20px;margin:0 0 8px}p{color:#50575e;line-height:1.5}</style>
</head>
<body>
<div class="box">
	<h1><?php echo esc_html( $title ); ?></h1>
	<p><?php echo esc_html( $message ); ?></p>
</div>
</body>
</html>
		<?php
	}

	// ---------------------------------------------------------------------
	//  Token endpoint
	// ---------------------------------------------------------------------

	public function handle_token( \WP_REST_Request $request ) {
		$params = $request->get_body_params();
		if ( empty( $params ) ) {
			// Some clients send JSON even though the spec says form-encoded. Be permissive.
			$json = json_decode( $request->get_body(), true );
			if ( is_array( $json ) ) {
				$params = $json;
			}
		}

		$grant_type = isset( $params['grant_type'] ) ? (string) $params['grant_type'] : '';

		// Extract client credentials (either Basic auth or body).
		$credentials = $this->extract_client_credentials( $request, $params );
		$client_id   = $credentials['client_id'];
		$secret      = $credentials['client_secret'];

		if ( '' === $client_id ) {
			return $this->oauth_error( 'invalid_client', 'Missing client_id', 401 );
		}

		$client = $this->get_client( $client_id );
		if ( ! $client ) {
			return $this->oauth_error( 'invalid_client', 'Unknown client', 401 );
		}

		if ( 'none' !== $client->token_endpoint_auth_method ) {
			if ( '' === $secret || ! hash_equals( (string) $client->client_secret_hash, self::hash_token( $secret ) ) ) {
				return $this->oauth_error( 'invalid_client', 'Bad client credentials', 401 );
			}
		}

		switch ( $grant_type ) {
			case 'authorization_code':
				return $this->grant_authorization_code( $client, $params );
			case 'refresh_token':
				return $this->grant_refresh_token( $client, $params );
			default:
				return $this->oauth_error( 'unsupported_grant_type', 'Supported grants: authorization_code, refresh_token', 400 );
		}
	}

	private function extract_client_credentials( \WP_REST_Request $request, array $params ): array {
		$auth = (string) $request->get_header( 'Authorization' );
		if ( '' !== $auth && 0 === stripos( $auth, 'Basic ' ) ) {
			$decoded = base64_decode( trim( substr( $auth, 6 ) ), true );
			if ( false !== $decoded && false !== strpos( $decoded, ':' ) ) {
				[ $id, $secret ] = explode( ':', $decoded, 2 );
				return [
					'client_id'     => urldecode( (string) $id ),
					'client_secret' => urldecode( (string) $secret ),
				];
			}
		}
		return [
			'client_id'     => isset( $params['client_id'] ) ? (string) $params['client_id'] : '',
			'client_secret' => isset( $params['client_secret'] ) ? (string) $params['client_secret'] : '',
		];
	}

	private function grant_authorization_code( $client, array $params ) {
		$code          = isset( $params['code'] ) ? (string) $params['code'] : '';
		$redirect_uri  = isset( $params['redirect_uri'] ) ? (string) $params['redirect_uri'] : '';
		$code_verifier = isset( $params['code_verifier'] ) ? (string) $params['code_verifier'] : '';

		if ( '' === $code || '' === $code_verifier ) {
			return $this->oauth_error( 'invalid_request', 'Missing code or code_verifier', 400 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'storemcp_oauth_codes';
		$hash  = self::hash_token( $code );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE code_hash = %s", $hash ) );

		if ( ! $row ) {
			return $this->oauth_error( 'invalid_grant', 'Unknown authorization code', 400 );
		}
		if ( $row->used_at ) {
			// Single-use: replay means we should revoke everything this client has.
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}storemcp_oauth_tokens SET revoked_at = %s WHERE client_id = %s AND revoked_at IS NULL",
				current_time( 'mysql', true ),
				$row->client_id
			) );
			return $this->oauth_error( 'invalid_grant', 'Authorization code already used', 400 );
		}
		if ( strtotime( $row->expires_at ) < time() ) {
			return $this->oauth_error( 'invalid_grant', 'Authorization code expired', 400 );
		}
		if ( $row->client_id !== $client->client_id ) {
			return $this->oauth_error( 'invalid_grant', 'client_id mismatch', 400 );
		}
		if ( $row->redirect_uri !== $redirect_uri ) {
			return $this->oauth_error( 'invalid_grant', 'redirect_uri mismatch', 400 );
		}

		$verifier_hash = rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' );
		if ( ! hash_equals( (string) $row->code_challenge, $verifier_hash ) ) {
			return $this->oauth_error( 'invalid_grant', 'PKCE verification failed', 400 );
		}

		$wpdb->update(
			$table,
			[ 'used_at' => current_time( 'mysql', true ) ],
			[ 'id' => (int) $row->id ],
			[ '%s' ],
			[ '%d' ]
		);

		return $this->issue_tokens( $client->client_id, (int) $row->user_id, (string) $row->scope );
	}

	private function grant_refresh_token( $client, array $params ) {
		$refresh = isset( $params['refresh_token'] ) ? (string) $params['refresh_token'] : '';
		if ( '' === $refresh || ! str_starts_with( $refresh, self::REFRESH_PREFIX ) ) {
			return $this->oauth_error( 'invalid_grant', 'Missing or malformed refresh_token', 400 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'storemcp_oauth_tokens';
		$hash  = self::hash_token( $refresh );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE refresh_hash = %s", $hash ) );

		if ( ! $row ) {
			return $this->oauth_error( 'invalid_grant', 'Unknown refresh_token', 400 );
		}
		if ( $row->revoked_at ) {
			return $this->oauth_error( 'invalid_grant', 'Refresh token revoked', 400 );
		}
		if ( $row->refresh_expires_at && strtotime( $row->refresh_expires_at ) < time() ) {
			return $this->oauth_error( 'invalid_grant', 'Refresh token expired', 400 );
		}
		if ( $row->client_id !== $client->client_id ) {
			return $this->oauth_error( 'invalid_grant', 'client mismatch', 400 );
		}

		// Rotate: revoke the old token row and issue fresh tokens.
		$wpdb->update(
			$table,
			[ 'revoked_at' => current_time( 'mysql', true ) ],
			[ 'id' => (int) $row->id ],
			[ '%s' ],
			[ '%d' ]
		);

		$scopes = $row->scopes ? implode( ' ', (array) json_decode( $row->scopes, true ) ) : self::DEFAULT_SCOPE;
		return $this->issue_tokens( $client->client_id, (int) $row->user_id, $scopes );
	}

	private function issue_tokens( string $client_id, int $user_id, string $scope ) {
		$access  = self::ACCESS_PREFIX . wp_generate_password( 48, false, false );
		$refresh = self::REFRESH_PREFIX . wp_generate_password( 48, false, false );

		$now         = time();
		$expires_at  = gmdate( 'Y-m-d H:i:s', $now + self::ACCESS_TTL );
		$rt_expires  = gmdate( 'Y-m-d H:i:s', $now + self::REFRESH_TTL );
		$scope_list  = array_values( array_filter( preg_split( '/\s+/', trim( $scope ) ) ?: [] ) );

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'storemcp_oauth_tokens',
			[
				'token_hash'         => self::hash_token( $access ),
				'refresh_hash'       => self::hash_token( $refresh ),
				'client_id'          => $client_id,
				'user_id'            => $user_id,
				'scopes'             => wp_json_encode( $scope_list ),
				'expires_at'         => $expires_at,
				'refresh_expires_at' => $rt_expires,
				'created_at'         => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
		);

		$wpdb->update(
			$wpdb->prefix . 'storemcp_oauth_clients',
			[ 'last_used_at' => current_time( 'mysql', true ) ],
			[ 'client_id' => $client_id ],
			[ '%s' ],
			[ '%s' ]
		);

		return new \WP_REST_Response( [
			'access_token'  => $access,
			'token_type'    => 'Bearer',
			'expires_in'    => self::ACCESS_TTL,
			'refresh_token' => $refresh,
			'scope'         => implode( ' ', $scope_list ),
		], 200 );
	}

	// ---------------------------------------------------------------------
	//  Token revocation (RFC 7009)
	// ---------------------------------------------------------------------

	public function handle_revoke( \WP_REST_Request $request ) {
		$params = $request->get_body_params();
		$token  = isset( $params['token'] ) ? (string) $params['token'] : '';

		if ( '' === $token ) {
			return new \WP_REST_Response( null, 200 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'storemcp_oauth_tokens';
		$hash  = self::hash_token( $token );
		$now   = current_time( 'mysql', true );

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET revoked_at = %s WHERE (token_hash = %s OR refresh_hash = %s) AND revoked_at IS NULL",
			$now,
			$hash,
			$hash
		) );

		return new \WP_REST_Response( null, 200 );
	}

	// ---------------------------------------------------------------------
	//  Error helpers
	// ---------------------------------------------------------------------

	private function oauth_error( string $code, string $description, int $status ): \WP_REST_Response {
		return new \WP_REST_Response( [
			'error'             => $code,
			'error_description' => $description,
		], $status );
	}

	public static function min_capability(): string {
		$cap = (string) get_option( 'store_mcp_oauth_min_capability', 'manage_options' );
		return $cap !== '' ? $cap : 'manage_options';
	}

	public static function hash_token( string $raw ): string {
		return hash( 'sha256', $raw );
	}

	public static function purge_expired(): void {
		global $wpdb;
		$now = current_time( 'mysql', true );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}storemcp_oauth_codes WHERE expires_at < %s",
			$now
		) );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}storemcp_oauth_tokens
			 WHERE (refresh_expires_at IS NOT NULL AND refresh_expires_at < %s)
			    OR (revoked_at IS NOT NULL AND revoked_at < %s)",
			$now,
			gmdate( 'Y-m-d H:i:s', time() - 86400 )
		) );
	}
}
