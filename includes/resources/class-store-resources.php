<?php
/**
 * Store resources — WooCommerce stats, low stock, pending orders.
 *
 * @package StoreMCP\Resources
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Store_Resources {

	public static function register( Resources_Registry $registry ): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$registry->register( [
			'uri'         => 'store://store/stats',
			'name'        => 'Store stats',
			'description' => 'Summary: total products, orders today, revenue today.',
			'tier'        => 'free',
		], [ self::class, 'stats' ] );

		$registry->register( [
			'uri'         => 'store://store/low-stock',
			'name'        => 'Low stock products',
			'description' => 'Products currently at or below the low-stock threshold.',
			'tier'        => 'free',
		], [ self::class, 'low_stock' ] );

		$registry->register( [
			'uri'         => 'store://store/pending-orders',
			'name'        => 'Pending orders',
			'description' => 'Orders awaiting processing (pending, on-hold, processing).',
			'tier'        => 'free',
		], [ self::class, 'pending_orders' ] );
	}

	public static function stats( AuthContext $context ): array {
		Permissions::require_cap( $context, 'read' );

		$counts = wp_count_posts( 'product' );
		$today  = current_time( 'Y-m-d' );

		$orders_today = wc_get_orders( [
			'status'       => [ 'wc-completed', 'wc-processing', 'wc-on-hold' ],
			'type'         => 'shop_order',
			'date_created' => '>=' . $today,
			'limit'        => -1,
			'return'       => 'objects',
		] );

		$revenue_today = 0.0;
		foreach ( $orders_today as $order ) {
			/** @var \WC_Order $order */
			$revenue_today += (float) $order->get_total();
		}

		return [
			'total_products'   => (int) ( $counts->publish ?? 0 ),
			'orders_today'     => count( $orders_today ),
			'revenue_today'    => round( $revenue_today, 2 ),
			'currency'         => get_woocommerce_currency(),
			'pending_count'    => (int) wc_orders_count( 'pending' ),
			'processing_count' => (int) wc_orders_count( 'processing' ),
			'on_hold_count'    => (int) wc_orders_count( 'on-hold' ),
		];
	}

	public static function low_stock( AuthContext $context ): array {
		Permissions::require_cap( $context, 'manage_woocommerce' );
		return Reports_Tools::stock_low( [ 'limit' => 50 ], $context );
	}

	public static function pending_orders( AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_shop_orders' );

		$orders = wc_get_orders( [
			'status'  => [ 'wc-pending', 'wc-on-hold', 'wc-processing' ],
			'type'    => 'shop_order',
			'limit'   => 50,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
		] );

		$items = [];
		foreach ( $orders as $order ) {
			/** @var \WC_Order $order */
			$items[] = [
				'id'           => $order->get_id(),
				'number'       => $order->get_order_number(),
				'status'       => $order->get_status(),
				'total'        => (float) $order->get_total(),
				'currency'     => $order->get_currency(),
				'customer'     => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'email'        => $order->get_billing_email(),
				'date_created' => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'c' ) : null,
			];
		}
		return [ 'items' => $items, 'count' => count( $items ) ];
	}
}

add_action( 'store_mcp_register_resources', [ Store_Resources::class, 'register' ] );
