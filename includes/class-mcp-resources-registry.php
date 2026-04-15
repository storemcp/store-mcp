<?php
/**
 * Resources registry — read-only context exposed to MCP clients.
 *
 * @package StoreMCP
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Resources_Registry {

	private License $license;

	/** @var array<string, array> uri => meta */
	private array $resources = [];

	/** @var array<string, callable> uri => reader */
	private array $readers = [];

	public function __construct( License $license ) {
		$this->license = $license;
	}

	public function bootstrap(): void {
		$base = STORE_MCP_PATH . 'includes/resources/';

		foreach ( [ 'class-site-resources.php', 'class-content-resources.php', 'class-store-resources.php' ] as $file ) {
			$path = $base . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}

		do_action( 'store_mcp_register_resources', $this );
	}

	public function register( array $definition, callable $reader ): void {
		if ( empty( $definition['uri'] ) ) {
			return;
		}
		$uri = (string) $definition['uri'];

		$definition += [
			'name'        => $uri,
			'description' => '',
			'mimeType'    => 'application/json',
			'tier'        => Tools_Registry::TIER_FREE,
		];

		$this->resources[ $uri ] = [
			'uri'         => $uri,
			'name'        => (string) $definition['name'],
			'description' => (string) $definition['description'],
			'mimeType'    => (string) $definition['mimeType'],
			'_meta'       => [ 'tier' => $definition['tier'] ],
		];
		$this->readers[ $uri ] = $reader;
	}

	public function list_for( AuthContext $context ): array {
		$is_pro = $this->license->is_pro();
		$out    = [];
		foreach ( $this->resources as $uri => $resource ) {
			if ( Tools_Registry::TIER_PRO === $resource['_meta']['tier'] && ! $is_pro ) {
				continue;
			}
			$public = $resource;
			unset( $public['_meta'] );
			$out[ $uri ] = $public;
		}
		return $out;
	}

	public function read( string $uri, AuthContext $context ): array {
		if ( ! isset( $this->readers[ $uri ] ) ) {
			throw new Tool_Exception( esc_html( sprintf( /* translators: %s: resource uri */ __( 'Unknown resource: %s', 'store-mcp' ), $uri ) ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$resource = $this->resources[ $uri ];
		if ( Tools_Registry::TIER_PRO === $resource['_meta']['tier'] && ! $this->license->is_pro() ) {
			throw new Tool_Exception( esc_html__( 'This resource requires StoreMCP Pro', 'store-mcp' ), Server::ERR_LICENSE_REQUIRED );
		}

		$data = call_user_func( $this->readers[ $uri ], $context );

		$mime = $resource['mimeType'];
		$text = ( 'application/json' === $mime ) ? wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : (string) $data;

		return [
			'contents' => [
				[
					'uri'      => $uri,
					'mimeType' => $mime,
					'text'     => $text,
				],
			],
		];
	}
}
