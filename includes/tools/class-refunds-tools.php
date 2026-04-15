<?php
/**
 * Refunds tools — WooCommerce order refunds (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Refunds_Tools {
	use Tool_Base;

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_refunds',
			'description' => 'List refunds for an order.',
			'tier'        => 'pro',
			'module'      => 'class-refunds-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'order_id' ],
				'properties' => [ 'order_id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_refunds' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_refund',
			'description' => 'Create a refund on an order. api_refund=true triggers gateway-level refund if supported.',
			'tier'        => 'pro',
			'module'      => 'class-refunds-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'order_id', 'amount' ],
				'properties' => [
					'order_id'   => [ 'type' => 'integer' ],
					'amount'     => [ 'type' => 'number' ],
					'reason'     => [ 'type' => 'string' ],
					'api_refund' => [ 'type' => 'boolean', 'default' => false ],
					'restock'    => [ 'type' => 'boolean', 'default' => false ],
					'line_items' => [
						'type'  => 'array',
						'items' => [
							'type' => 'object',
							'properties' => [
								'item_id'       => [ 'type' => 'integer' ],
								'qty'           => [ 'type' => 'integer' ],
								'refund_total'  => [ 'type' => 'number' ],
							],
						],
					],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_refund' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_refund',
			'description' => 'Delete a refund.',
			'tier'        => 'pro',
			'module'      => 'class-refunds-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'order_id', 'refund_id' ],
				'properties' => [
					'order_id'  => [ 'type' => 'integer' ],
					'refund_id' => [ 'type' => 'integer' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_refund' ] );
	}

	public static function list_refunds( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'edit_shop_orders' );

		$order_id = (int) self::require_arg( $args, 'order_id' );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new Tool_Exception( __( 'Order not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$items = [];
		foreach ( $order->get_refunds() as $refund ) {
			/** @var \WC_Order_Refund $refund */
			$items[] = [
				'id'           => $refund->get_id(),
				'amount'       => (float) $refund->get_amount(),
				'reason'       => $refund->get_reason(),
				'refunded_by'  => $refund->get_refunded_by(),
				'refunded_payment' => $refund->get_refunded_payment(),
				'date_created' => $refund->get_date_created() ? $refund->get_date_created()->date_i18n( 'c' ) : null,
			];
		}
		return [ 'order_id' => $order_id, 'items' => $items, 'count' => count( $items ) ];
	}

	public static function create_refund( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'edit_shop_orders' );

		$order_id = (int) self::require_arg( $args, 'order_id' );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new Tool_Exception( __( 'Order not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$amount     = (float) self::require_arg( $args, 'amount' );
		$reason     = self::arg_textarea( $args, 'reason', '' );
		$api_refund = (bool) self::arg_bool( $args, 'api_refund', false );
		$restock    = (bool) self::arg_bool( $args, 'restock', false );

		$line_items = [];
		foreach ( self::arg_array( $args, 'line_items', [] ) as $li ) {
			if ( empty( $li['item_id'] ) ) continue;
			$line_items[ (int) $li['item_id'] ] = [
				'qty'          => (int) ( $li['qty'] ?? 0 ),
				'refund_total' => (float) ( $li['refund_total'] ?? 0 ),
			];
		}

		$refund = wc_create_refund( [
			'amount'         => $amount,
			'reason'         => $reason,
			'order_id'       => $order_id,
			'line_items'     => $line_items,
			'refund_payment' => $api_refund,
			'restock_items'  => $restock,
		] );
		if ( is_wp_error( $refund ) ) {
			throw new Tool_Exception( $refund->get_error_message(), Server::ERR_TOOL_FAILED );
		}

		return [
			'id'            => $refund->get_id(),
			'order_id'      => $order_id,
			'amount'        => (float) $refund->get_amount(),
			'reason'        => $refund->get_reason(),
			'date_created'  => $refund->get_date_created() ? $refund->get_date_created()->date_i18n( 'c' ) : null,
			'api_refund'    => $api_refund,
		];
	}

	public static function delete_refund( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'edit_shop_orders' );

		$refund_id = (int) self::require_arg( $args, 'refund_id' );
		$refund    = wc_get_order( $refund_id );
		if ( ! $refund || 'shop_order_refund' !== $refund->get_type() ) {
			throw new Tool_Exception( __( 'Refund not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		$refund->delete( true );
		return [ 'id' => $refund_id, 'deleted' => true ];
	}
}

add_action( 'store_mcp_register_tools', [ Refunds_Tools::class, 'register' ] );
