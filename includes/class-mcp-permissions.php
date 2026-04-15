<?php
/**
 * Permissions helper.
 *
 * Centralises the capability checks used by every tool. Throws Tool_Exception
 * (mapped to JSON-RPC -32002) so the dispatcher returns a clean error.
 *
 * @package StoreMCP
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Permissions {

	public static function require_cap( AuthContext $context, string $capability, ...$args ): void {
		if ( ! $context->user_can( $capability, ...$args ) ) {
			throw new Tool_Exception(
				sprintf( /* translators: %s: capability */ __( 'Missing capability: %s', 'store-mcp' ), $capability ),
				Server::ERR_FORBIDDEN
			);
		}
	}

	public static function require_any( AuthContext $context, array $capabilities ): void {
		foreach ( $capabilities as $cap ) {
			if ( $context->user_can( $cap ) ) {
				return;
			}
		}
		throw new Tool_Exception(
			sprintf( /* translators: %s: capability list */ __( 'Missing one of capabilities: %s', 'store-mcp' ), implode( ', ', $capabilities ) ),
			Server::ERR_FORBIDDEN
		);
	}

	public static function require_woocommerce(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			throw new Tool_Exception( __( 'WooCommerce is not active', 'store-mcp' ), Server::ERR_FORBIDDEN );
		}
	}
}
