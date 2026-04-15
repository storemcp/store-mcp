<?php
/**
 * MCP Server — JSON-RPC 2.0 dispatcher with Streamable HTTP transport.
 *
 * Implements MCP protocol revision 2025-03-26 over Streamable HTTP, plus a
 * legacy SSE endpoint for older clients. Single endpoint accepts POST with a
 * JSON-RPC message (single or batch) and replies with either application/json
 * or text/event-stream depending on Accept header and message shape.
 *
 * @package StoreMCP
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Server {

	const RPC_PARSE_ERROR      = -32700;
	const RPC_INVALID_REQUEST  = -32600;
	const RPC_METHOD_NOT_FOUND = -32601;
	const RPC_INVALID_PARAMS   = -32602;
	const RPC_INTERNAL_ERROR   = -32603;

	const ERR_UNAUTHORIZED      = -32001;
	const ERR_FORBIDDEN         = -32002;
	const ERR_RATE_LIMITED      = -32003;
	const ERR_LICENSE_REQUIRED  = -32004;
	const ERR_DISABLED          = -32005;
	const ERR_TOOL_FAILED       = -32010;
	const ERR_RESOURCE_NOT_FOUND = -32011;

	private Auth $auth;
	private Tools_Registry $tools;
	private Resources_Registry $resources;
	private Rate_Limiter $rate_limiter;
	private Activity_Log $log;

	public function __construct(
		Auth $auth,
		Tools_Registry $tools,
		Resources_Registry $resources,
		Rate_Limiter $rate_limiter,
		Activity_Log $log
	) {
		$this->auth         = $auth;
		$this->tools        = $tools;
		$this->resources    = $resources;
		$this->rate_limiter = $rate_limiter;
		$this->log          = $log;
	}

	public function register_routes(): void {
		$ns = STORE_MCP_REST_NAMESPACE;

		register_rest_route( $ns, '/mcp', [
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_streamable' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle_streamable_get' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ $this, 'handle_streamable_delete' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
		] );

		register_rest_route( $ns, '/message', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_streamable' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		register_rest_route( $ns, '/sse', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_sse' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		register_rest_route( $ns, '/info', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_info' ],
			'permission_callback' => '__return_true',
		] );

		add_filter( 'rest_pre_serve_request', [ $this, 'maybe_serve_cors' ], 10, 4 );
	}

	public function permission_check( \WP_REST_Request $request ) {
		if ( ! get_option( 'store_mcp_enabled', true ) ) {
			return new \WP_Error( 'store_mcp_disabled', __( 'StoreMCP is disabled', 'store-mcp' ), [ 'status' => 503 ] );
		}

		$context = $this->auth->authenticate( $request );
		if ( is_wp_error( $context ) ) {
			// RFC 9728: tell clients where to find our OAuth discovery.
			$metadata = OAuth::protected_resource_metadata_url();
			header( sprintf( 'WWW-Authenticate: Bearer realm="StoreMCP", resource_metadata="%s"', $metadata ) );
			return $context;
		}

		$request->set_param( '__store_mcp_context', $context );
		return true;
	}

	public function handle_info( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( [
			'name'             => 'StoreMCP',
			'version'          => STORE_MCP_VERSION,
			'protocol_version' => STORE_MCP_PROTOCOL_VERSION,
			'transports'       => [ 'streamable_http', 'sse' ],
			'endpoints'        => [
				'streamable' => rest_url( STORE_MCP_REST_NAMESPACE . '/mcp' ),
				'sse'        => rest_url( STORE_MCP_REST_NAMESPACE . '/sse' ),
				'message'    => rest_url( STORE_MCP_REST_NAMESPACE . '/message' ),
			],
			'tier'             => Plugin::instance()->license->tier(),
			'wc_active'        => class_exists( 'WooCommerce' ),
		], 200 );
	}

	public function handle_streamable( \WP_REST_Request $request ) {
		$context = $request->get_param( '__store_mcp_context' );

		$rate = $this->rate_limiter->check( $context );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$body = $request->get_body();
		$payload = json_decode( $body, true );

		if ( null === $payload && '' !== trim( (string) $body ) ) {
			return $this->rpc_error_response( null, self::RPC_PARSE_ERROR, __( 'Parse error: invalid JSON', 'store-mcp' ), 400 );
		}

		$is_batch = is_array( $payload ) && [] !== $payload && array_keys( $payload ) === range( 0, count( $payload ) - 1 );
		$messages = $is_batch ? $payload : [ $payload ];

		$responses = [];
		foreach ( $messages as $message ) {
			$response = $this->dispatch( $message, $context );
			if ( null !== $response ) {
				$responses[] = $response;
			}
		}

		if ( empty( $responses ) ) {
			return new \WP_REST_Response( null, 202 );
		}

		$payload_out = $is_batch ? $responses : $responses[0];
		return new \WP_REST_Response( $payload_out, 200 );
	}

	public function handle_streamable_get( \WP_REST_Request $request ) {
		return new \WP_REST_Response( null, 405, [ 'Allow' => 'POST, DELETE' ] );
	}

	public function handle_streamable_delete( \WP_REST_Request $request ) {
		return new \WP_REST_Response( null, 204 );
	}

	public function handle_sse( \WP_REST_Request $request ) {
		$context = $request->get_param( '__store_mcp_context' );

		nocache_headers();
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache, no-transform' );
		header( 'X-Accel-Buffering: no' );
		header( 'Connection: keep-alive' );

		@set_time_limit( 0 );
		ignore_user_abort( true );

		$endpoint = rest_url( STORE_MCP_REST_NAMESPACE . '/message' );
		echo "event: endpoint\n";
		echo 'data: ' . wp_json_encode( [ 'uri' => $endpoint ] ) . "\n\n";
		@ob_flush();
		flush();

		$start = time();
		while ( ! connection_aborted() && ( time() - $start ) < 25 ) {
			echo ": ping\n\n";
			@ob_flush();
			flush();
			sleep( 5 );
		}
		exit;
	}

	private function dispatch( $message, AuthContext $context ): ?array {
		if ( ! is_array( $message ) || ! isset( $message['jsonrpc'] ) || '2.0' !== $message['jsonrpc'] ) {
			return $this->rpc_error( null, self::RPC_INVALID_REQUEST, __( 'Invalid JSON-RPC request', 'store-mcp' ) );
		}

		$id     = $message['id'] ?? null;
		$method = isset( $message['method'] ) ? (string) $message['method'] : '';
		$params = $message['params'] ?? [];

		$is_notification = ! array_key_exists( 'id', $message );

		$started = microtime( true );
		$status  = 'success';
		$error   = '';

		try {
			switch ( $method ) {
				case 'initialize':
					$result = $this->method_initialize( $params );
					break;
				case 'notifications/initialized':
				case 'notifications/cancelled':
				case 'notifications/roots/list_changed':
					return null;
				case 'ping':
					$result = (object) [];
					break;
				case 'tools/list':
					$result = $this->method_tools_list( $params, $context );
					break;
				case 'tools/call':
					$result = $this->method_tools_call( $params, $context );
					break;
				case 'resources/list':
					$result = $this->method_resources_list( $params, $context );
					break;
				case 'resources/read':
					$result = $this->method_resources_read( $params, $context );
					break;
				case 'resources/templates/list':
					$result = [ 'resourceTemplates' => [] ];
					break;
				case 'prompts/list':
					$result = [ 'prompts' => [] ];
					break;
				case 'logging/setLevel':
					$result = (object) [];
					break;
				default:
					$status = 'error';
					$error  = 'method_not_found';
					if ( $is_notification ) {
						return null;
					}
					return $this->rpc_error( $id, self::RPC_METHOD_NOT_FOUND, sprintf( /* translators: %s: method name */ __( 'Method not found: %s', 'store-mcp' ), $method ) );
			}
		} catch ( \StoreMCP\Tool_Exception $e ) {
			$status = 'error';
			$error  = $e->getMessage();
			$this->log_call( $context, $method, $params, 'error', 200, microtime( true ) - $started, $e->getMessage() );
			if ( $is_notification ) {
				return null;
			}
			return $this->rpc_error( $id, $e->getCode() ?: self::ERR_TOOL_FAILED, $e->getMessage(), $e->data() );
		} catch ( \Throwable $e ) {
			$status = 'error';
			$error  = $e->getMessage();
			$this->log_call( $context, $method, $params, 'error', 500, microtime( true ) - $started, $e->getMessage() );
			if ( $is_notification ) {
				return null;
			}
			return $this->rpc_error( $id, self::RPC_INTERNAL_ERROR, __( 'Internal error', 'store-mcp' ) );
		}

		$this->log_call( $context, $method, $params, $status, 200, microtime( true ) - $started, $error );

		if ( $is_notification ) {
			return null;
		}

		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		];
	}

	private function method_initialize( array $params ): array {
		return [
			'protocolVersion' => STORE_MCP_PROTOCOL_VERSION,
			'serverInfo'      => [
				'name'    => apply_filters( 'store_mcp_server_name', 'StoreMCP' ),
				'version' => STORE_MCP_VERSION,
			],
			'capabilities'    => [
				'tools'     => [ 'listChanged' => false ],
				'resources' => [ 'listChanged' => false, 'subscribe' => false ],
				'logging'   => (object) [],
			],
			'instructions'    => __( 'StoreMCP exposes WordPress and WooCommerce as MCP tools. Use tools/list to discover available operations.', 'store-mcp' ),
		];
	}

	private function method_tools_list( array $params, AuthContext $context ): array {
		$cursor = isset( $params['cursor'] ) ? (string) $params['cursor'] : '';
		$tools  = $this->tools->list_for( $context );
		return [ 'tools' => array_values( $tools ) ];
	}

	private function method_tools_call( array $params, AuthContext $context ): array {
		$name      = isset( $params['name'] ) ? (string) $params['name'] : '';
		$arguments = $params['arguments'] ?? [];

		if ( '' === $name ) {
			throw new Tool_Exception( esc_html__( 'Missing tool name', 'store-mcp' ), self::RPC_INVALID_PARAMS );
		}
		if ( ! is_array( $arguments ) ) {
			throw new Tool_Exception( esc_html__( 'Arguments must be an object', 'store-mcp' ), self::RPC_INVALID_PARAMS );
		}

		return $this->tools->call( $name, $arguments, $context );
	}

	private function method_resources_list( array $params, AuthContext $context ): array {
		return [ 'resources' => array_values( $this->resources->list_for( $context ) ) ];
	}

	private function method_resources_read( array $params, AuthContext $context ): array {
		$uri = isset( $params['uri'] ) ? (string) $params['uri'] : '';
		if ( '' === $uri ) {
			throw new Tool_Exception( esc_html__( 'Missing resource uri', 'store-mcp' ), self::RPC_INVALID_PARAMS );
		}
		return $this->resources->read( $uri, $context );
	}

	private function rpc_error( $id, int $code, string $message, $data = null ): array {
		$error = [ 'code' => $code, 'message' => $message ];
		if ( null !== $data ) {
			$error['data'] = $data;
		}
		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => $error,
		];
	}

	private function rpc_error_response( $id, int $code, string $message, int $http_status ): \WP_REST_Response {
		return new \WP_REST_Response( $this->rpc_error( $id, $code, $message ), $http_status );
	}

	private function log_call( AuthContext $context, string $method, $params, string $status, int $http_code, float $duration_s, string $error ): void {
		$tool = '';
		if ( 'tools/call' === $method && is_array( $params ) && isset( $params['name'] ) ) {
			$tool = (string) $params['name'];
		} elseif ( 'resources/read' === $method && is_array( $params ) && isset( $params['uri'] ) ) {
			$tool = (string) $params['uri'];
		}

		$this->log->record( [
			'user_id'       => $context->user_id,
			'key_id'        => $context->key_id,
			'ip'            => $this->client_ip(),
			'method'        => $method,
			'tool_name'     => $tool,
			'params'        => $params,
			'status'        => $status,
			'http_code'     => $http_code,
			'duration_ms'   => (int) round( $duration_s * 1000 ),
			'error_message' => $error,
		] );
	}

	private function client_ip(): string {
		$candidates = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ];
		foreach ( $candidates as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				$value = trim( explode( ',', $value )[0] );
				if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
					return $value;
				}
			}
		}
		return '';
	}

	public function maybe_serve_cors( $served, $result, $request, $server ) {
		if ( strpos( (string) $request->get_route(), '/' . STORE_MCP_REST_NAMESPACE ) !== 0 ) {
			return $served;
		}

		$origin  = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		$allowed = (array) get_option( 'store_mcp_cors_allowed', [] );

		if ( $origin && ( in_array( '*', $allowed, true ) || in_array( $origin, $allowed, true ) ) ) {
			header( 'Access-Control-Allow-Origin: ' . $origin );
			header( 'Vary: Origin' );
			header( 'Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-StoreMCP-Key, Mcp-Session-Id, MCP-Protocol-Version' );
			header( 'Access-Control-Max-Age: 600' );
		}

		return $served;
	}
}

class Tool_Exception extends \RuntimeException {
	private $extra;

	public function __construct( string $message, int $code = 0, $extra = null ) {
		parent::__construct( $message, $code );
		$this->extra = $extra;
	}

	public function data() {
		return $this->extra;
	}
}
