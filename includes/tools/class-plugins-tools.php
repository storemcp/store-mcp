<?php
/**
 * Plugins tools — list, activate, deactivate (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Plugins_Tools {
	use Tool_Base;

	const STATUSES = [ 'all', 'active', 'inactive', 'mustuse', 'dropins' ];

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_plugins',
			'description' => 'List installed plugins with their status and metadata.',
			'tier'        => 'pro',
			'module'      => 'class-plugins-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'status' => [ 'type' => 'string', 'enum' => self::STATUSES, 'default' => 'all' ],
					'search' => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_plugins' ] );

		$registry->register( [
			'name'        => 'store_mcp_activate_plugin',
			'description' => 'Activate a plugin by its relative path, e.g. "woocommerce/woocommerce.php".',
			'tier'        => 'pro',
			'module'      => 'class-plugins-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'plugin' ],
				'properties' => [
					'plugin'       => [ 'type' => 'string' ],
					'network_wide' => [ 'type' => 'boolean', 'default' => false, 'description' => 'Multisite only.' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'activate_plugin' ] );

		$registry->register( [
			'name'        => 'store_mcp_deactivate_plugin',
			'description' => 'Deactivate a plugin.',
			'tier'        => 'pro',
			'module'      => 'class-plugins-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'plugin' ],
				'properties' => [
					'plugin'       => [ 'type' => 'string' ],
					'network_wide' => [ 'type' => 'boolean', 'default' => false ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'deactivate_plugin' ] );
	}

	public static function list_plugins( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'activate_plugins' );
		self::ensure_plugin_api();

		$status = (string) self::arg_enum( $args, 'status', self::STATUSES, 'all' );
		$search = strtolower( self::arg_string( $args, 'search', '' ) );

		$plugins    = get_plugins();
		$active     = (array) get_option( 'active_plugins', [] );
		$network    = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', [] ) : [];
		$mustuse    = 'mustuse' === $status ? get_mu_plugins() : [];
		$dropins    = 'dropins' === $status ? get_dropins() : [];

		$items = [];

		if ( 'mustuse' === $status ) {
			foreach ( $mustuse as $file => $data ) {
				$items[] = self::format_plugin( $file, $data, 'must-use' );
			}
		} elseif ( 'dropins' === $status ) {
			foreach ( $dropins as $file => $data ) {
				$items[] = self::format_plugin( $file, $data, 'drop-in' );
			}
		} else {
			foreach ( $plugins as $file => $data ) {
				$is_active = in_array( $file, $active, true ) || isset( $network[ $file ] );
				$current   = $is_active ? 'active' : 'inactive';
				if ( 'all' !== $status && $current !== $status ) {
					continue;
				}
				if ( '' !== $search && false === strpos( strtolower( (string) ( $data['Name'] ?? '' ) ), $search ) && false === strpos( strtolower( $file ), $search ) ) {
					continue;
				}
				$item = self::format_plugin( $file, $data, $current );
				$item['network_active'] = isset( $network[ $file ] );
				$items[] = $item;
			}
		}

		return [ 'items' => $items, 'count' => count( $items ) ];
	}

	public static function activate_plugin( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'activate_plugins' );
		self::ensure_plugin_api();

		$plugin = self::validate_plugin_path( (string) self::require_arg( $args, 'plugin' ) );
		$network_wide = (bool) self::arg_bool( $args, 'network_wide', false );

		if ( is_plugin_active( $plugin ) ) {
			return [ 'plugin' => $plugin, 'active' => true, 'already_active' => true ];
		}

		$result = activate_plugin( $plugin, '', $network_wide && is_multisite(), true );
		if ( is_wp_error( $result ) ) {
			throw new Tool_Exception( $result->get_error_message(), Server::ERR_TOOL_FAILED );
		}

		return [ 'plugin' => $plugin, 'active' => true ];
	}

	public static function deactivate_plugin( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'activate_plugins' );
		self::ensure_plugin_api();

		$plugin = self::validate_plugin_path( (string) self::require_arg( $args, 'plugin' ) );

		if ( $plugin === STORE_MCP_BASENAME ) {
			throw new Tool_Exception( __( 'Cannot deactivate StoreMCP via its own API.', 'store-mcp' ), Server::ERR_FORBIDDEN );
		}

		if ( ! is_plugin_active( $plugin ) ) {
			return [ 'plugin' => $plugin, 'active' => false, 'already_inactive' => true ];
		}

		$network_wide = (bool) self::arg_bool( $args, 'network_wide', false );
		deactivate_plugins( $plugin, true, $network_wide && is_multisite() );

		return [ 'plugin' => $plugin, 'active' => false ];
	}

	private static function ensure_plugin_api(): void {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	private static function validate_plugin_path( string $plugin ): string {
		$plugin = plugin_basename( trim( $plugin ) );
		if ( '' === $plugin || str_contains( $plugin, '..' ) ) {
			throw new Tool_Exception( __( 'Invalid plugin path', 'store-mcp' ), Server::RPC_INVALID_PARAMS );
		}
		$plugins = get_plugins();
		if ( ! isset( $plugins[ $plugin ] ) ) {
			throw new Tool_Exception( __( 'Plugin not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		return $plugin;
	}

	private static function format_plugin( string $file, array $data, string $status ): array {
		return [
			'file'        => $file,
			'name'        => (string) ( $data['Name'] ?? '' ),
			'version'     => (string) ( $data['Version'] ?? '' ),
			'author'      => wp_strip_all_tags( (string) ( $data['Author'] ?? '' ) ),
			'author_uri'  => (string) ( $data['AuthorURI'] ?? '' ),
			'plugin_uri'  => (string) ( $data['PluginURI'] ?? '' ),
			'description' => wp_strip_all_tags( (string) ( $data['Description'] ?? '' ) ),
			'text_domain' => (string) ( $data['TextDomain'] ?? '' ),
			'requires_wp' => (string) ( $data['RequiresWP'] ?? '' ),
			'requires_php'=> (string) ( $data['RequiresPHP'] ?? '' ),
			'status'      => $status,
		];
	}
}

add_action( 'store_mcp_register_tools', [ Plugins_Tools::class, 'register' ] );
