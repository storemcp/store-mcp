<?php
/**
 * Settings tools — WooCommerce settings and payment gateways (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Settings_Tools {
	use Tool_Base;

	const GROUPS = [ 'general', 'products', 'tax', 'shipping', 'checkout', 'account', 'email', 'advanced' ];

	/** Options safe to expose — excludes API keys, private webhook secrets, etc. */
	const ALLOWED_PREFIXES = [
		'woocommerce_',
	];

	const BLOCKED_SUFFIXES = [
		'_secret',
		'_api_key',
		'_private_key',
	];

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_get_settings',
			'description' => 'Get WooCommerce settings for a group. Secrets are redacted.',
			'tier'        => 'pro',
			'module'      => 'class-settings-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'group' ],
				'properties' => [
					'group' => [ 'type' => 'string', 'enum' => self::GROUPS ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_settings' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_setting',
			'description' => 'Update a single WooCommerce setting option.',
			'tier'        => 'pro',
			'module'      => 'class-settings-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'group', 'id', 'value' ],
				'properties' => [
					'group' => [ 'type' => 'string', 'enum' => self::GROUPS ],
					'id'    => [ 'type' => 'string', 'description' => 'Option name, e.g. woocommerce_currency.' ],
					'value' => [ 'description' => 'New value (string, number, or boolean).' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_setting' ] );

		$registry->register( [
			'name'        => 'store_mcp_list_payment_gateways',
			'description' => 'List all registered payment gateways with their enabled state.',
			'tier'        => 'pro',
			'module'      => 'class-settings-tools',
			'inputSchema' => [ 'type' => 'object', 'properties' => (object) [], 'additionalProperties' => false ],
		], [ self::class, 'list_gateways' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_payment_gateway',
			'description' => 'Enable/disable and configure a payment gateway.',
			'tier'        => 'pro',
			'module'      => 'class-settings-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'          => [ 'type' => 'string' ],
					'enabled'     => [ 'type' => 'boolean' ],
					'title'       => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
					'settings'    => [ 'type' => 'object', 'additionalProperties' => true ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_gateway' ] );
	}

	public static function get_settings( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$group = (string) self::require_arg( $args, 'group' );

		$settings = apply_filters( 'woocommerce_settings_' . self::page_for_group( $group ), [] );
		if ( empty( $settings ) ) {
			$pages = \WC_Admin_Settings::get_settings_pages();
			foreach ( $pages as $page ) {
				if ( $page->get_id() === self::page_for_group( $group ) && method_exists( $page, 'get_settings' ) ) {
					$settings = $page->get_settings();
					break;
				}
			}
		}

		$items = [];
		foreach ( (array) $settings as $setting ) {
			if ( empty( $setting['id'] ) || in_array( $setting['type'] ?? '', [ 'title', 'sectionend', 'info' ], true ) ) {
				continue;
			}
			$option = $setting['id'];
			$value  = self::is_sensitive( $option ) ? '[redacted]' : get_option( $option, $setting['default'] ?? '' );
			$items[] = [
				'id'          => $option,
				'title'       => (string) ( $setting['title'] ?? '' ),
				'description' => (string) ( $setting['desc'] ?? $setting['description'] ?? '' ),
				'type'        => (string) ( $setting['type'] ?? 'text' ),
				'options'     => $setting['options'] ?? null,
				'default'     => $setting['default'] ?? null,
				'value'       => $value,
			];
		}
		return [ 'group' => $group, 'items' => $items, 'count' => count( $items ) ];
	}

	public static function update_setting( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$id    = sanitize_key( (string) self::require_arg( $args, 'id' ) );
		$value = self::arg( $args, 'value' );

		if ( ! self::is_allowed_option( $id ) || self::is_sensitive( $id ) ) {
			throw new Tool_Exception( esc_html__( 'Option not writable via MCP', 'store-mcp' ), Server::ERR_FORBIDDEN );
		}

		update_option( $id, $value );
		return [ 'id' => $id, 'value' => self::is_sensitive( $id ) ? '[redacted]' : get_option( $id ) ];
	}

	public static function list_gateways( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$gateways = \WC()->payment_gateways()->payment_gateways();
		$items    = [];
		foreach ( $gateways as $gw ) {
			$items[] = [
				'id'          => $gw->id,
				'title'       => $gw->get_title(),
				'method_title'=> $gw->get_method_title(),
				'description' => $gw->get_description(),
				'enabled'     => 'yes' === $gw->enabled,
				'order'       => (int) ( $gw->settings['order'] ?? 0 ),
			];
		}
		return [ 'items' => $items, 'count' => count( $items ) ];
	}

	public static function update_gateway( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$id       = sanitize_key( (string) self::require_arg( $args, 'id' ) );
		$gateways = \WC()->payment_gateways()->payment_gateways();
		if ( ! isset( $gateways[ $id ] ) ) {
			throw new Tool_Exception( esc_html__( 'Gateway not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		$gateway      = $gateways[ $id ];
		$option_key   = 'woocommerce_' . $id . '_settings';
		$current      = (array) get_option( $option_key, [] );

		if ( array_key_exists( 'enabled', $args ) ) {
			$current['enabled'] = $args['enabled'] ? 'yes' : 'no';
		}
		if ( array_key_exists( 'title', $args ) ) {
			$current['title'] = sanitize_text_field( (string) $args['title'] );
		}
		if ( array_key_exists( 'description', $args ) ) {
			$current['description'] = sanitize_textarea_field( (string) $args['description'] );
		}
		if ( isset( $args['settings'] ) && is_array( $args['settings'] ) ) {
			foreach ( $args['settings'] as $k => $v ) {
				$current[ sanitize_key( (string) $k ) ] = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : $v;
			}
		}

		update_option( $option_key, $current );

		return [
			'id'      => $id,
			'enabled' => ( $current['enabled'] ?? 'no' ) === 'yes',
			'title'   => (string) ( $current['title'] ?? $gateway->get_method_title() ),
		];
	}

	private static function page_for_group( string $group ): string {
		return 'advanced' === $group ? 'advanced' : $group;
	}

	private static function is_allowed_option( string $option ): bool {
		foreach ( self::ALLOWED_PREFIXES as $prefix ) {
			if ( str_starts_with( $option, $prefix ) ) return true;
		}
		return false;
	}

	private static function is_sensitive( string $option ): bool {
		foreach ( self::BLOCKED_SUFFIXES as $suffix ) {
			if ( str_ends_with( $option, $suffix ) ) return true;
		}
		return false;
	}
}

add_action( 'store_mcp_register_tools', [ Settings_Tools::class, 'register' ] );
