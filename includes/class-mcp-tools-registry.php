<?php
/**
 * Tools registry — lazy-loads modules and dispatches tool calls.
 *
 * Each module class registers its tools via Tools_Registry::register().
 * Modules are bootstrapped on `init`, but their tool implementations may be
 * loaded lazily — only the metadata is needed to satisfy tools/list.
 *
 * @package StoreMCP
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Tools_Registry {

	const TIER_FREE = 'free';
	const TIER_PRO  = 'pro';

	private License $license;

	/** @var array<string, array> name => tool definition */
	private array $tools = [];

	/** @var array<string, callable> name => handler */
	private array $handlers = [];

	/** @var array<string, string> name => module slug */
	private array $modules = [];

	public function __construct( License $license ) {
		$this->license = $license;
	}

	public function bootstrap(): void {
		$base     = STORE_MCP_PATH . 'includes/tools/';
		$disabled = (array) get_option( 'store_mcp_disabled_modules', [] );

		$wp_modules = [
			'class-pages-tools.php',
			'class-posts-tools.php',
			'class-media-tools.php',
			'class-menus-tools.php',
			'class-widgets-tools.php',
			'class-users-tools.php',
			'class-site-tools.php',
			'class-seo-tools.php',
			'class-plugins-tools.php',
			'class-themes-tools.php',
			'class-system-tools.php',
		];

		$wc_modules = [
			'class-products-tools.php',
			'class-categories-tools.php',
			'class-tags-tools.php',
			'class-orders-tools.php',
			'class-reports-tools.php',
			'class-variations-tools.php',
			'class-attributes-tools.php',
			'class-customers-tools.php',
			'class-coupons-tools.php',
			'class-shipping-tools.php',
			'class-tax-tools.php',
			'class-settings-tools.php',
			'class-reviews-tools.php',
			'class-webhooks-tools.php',
			'class-refunds-tools.php',
		];

		foreach ( $wp_modules as $file ) {
			$this->load_module( $base . $file, $disabled );
		}

		if ( class_exists( 'WooCommerce' ) ) {
			foreach ( $wc_modules as $file ) {
				$this->load_module( $base . $file, $disabled );
			}
		}

		do_action( 'store_mcp_register_tools', $this );
	}

	private function load_module( string $path, array $disabled ): void {
		if ( ! file_exists( $path ) ) {
			return;
		}
		$slug = basename( $path, '.php' );
		if ( in_array( $slug, $disabled, true ) ) {
			return;
		}
		require_once $path;
	}

	/**
	 * Register a tool.
	 *
	 * @param array{name:string, description:string, inputSchema:array, tier?:string, capability?:string|callable, module?:string} $definition
	 * @param callable                                                                                                              $handler
	 */
	public function register( array $definition, callable $handler ): void {
		if ( empty( $definition['name'] ) ) {
			return;
		}

		$name = (string) $definition['name'];

		$definition += [
			'tier'        => self::TIER_FREE,
			'capability'  => 'read',
			'module'      => '',
			'inputSchema' => [ 'type' => 'object', 'properties' => (object) [], 'additionalProperties' => false ],
		];

		$this->tools[ $name ]    = [
			'name'        => $name,
			'description' => (string) $definition['description'],
			'inputSchema' => $definition['inputSchema'],
			'_meta'       => [
				'tier'   => $definition['tier'],
				'module' => $definition['module'],
			],
		];
		$this->handlers[ $name ] = $handler;
		$this->modules[ $name ]  = (string) $definition['module'];
	}

	public function list_for( AuthContext $context ): array {
		$is_pro = $this->license->is_pro();
		$out    = [];

		foreach ( $this->tools as $name => $tool ) {
			if ( self::TIER_PRO === $tool['_meta']['tier'] && ! $is_pro ) {
				continue;
			}
			$public = $tool;
			unset( $public['_meta'] );
			$out[ $name ] = $public;
		}
		return $out;
	}

	public function call( string $name, array $arguments, AuthContext $context ): array {
		if ( ! isset( $this->tools[ $name ] ) ) {
			throw new Tool_Exception( sprintf( /* translators: %s: tool name */ __( 'Unknown tool: %s', 'store-mcp' ), $name ), Server::RPC_METHOD_NOT_FOUND );
		}

		$tool = $this->tools[ $name ];

		if ( self::TIER_PRO === $tool['_meta']['tier'] && ! $this->license->is_pro() ) {
			throw new Tool_Exception( __( 'This tool requires StoreMCP Pro', 'store-mcp' ), Server::ERR_LICENSE_REQUIRED );
		}

		$handler = $this->handlers[ $name ];
		$result  = call_user_func( $handler, $arguments, $context );

		if ( is_array( $result ) && isset( $result['content'] ) && is_array( $result['content'] ) ) {
			return $result;
		}

		return [
			'content' => [
				[
					'type' => 'text',
					'text' => is_string( $result ) ? $result : wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				],
			],
			'structuredContent' => is_array( $result ) ? $result : [ 'value' => $result ],
		];
	}

	public function all(): array {
		return $this->tools;
	}

	public function modules(): array {
		$grouped = [];
		foreach ( $this->tools as $name => $tool ) {
			$module                    = $this->modules[ $name ] ?: 'misc';
			$grouped[ $module ][ $name ] = $tool;
		}
		return $grouped;
	}
}
