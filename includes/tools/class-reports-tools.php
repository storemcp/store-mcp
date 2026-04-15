<?php
/**
 * Reports tools — totals (FREE), advanced sales analytics (PRO).
 *
 * Cached in transients for the duration configured under
 * store_mcp_cache_ttl_seconds (default 300).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Reports_Tools {
	use Tool_Base;

	const ORDER_STATUSES = [ 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ];

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_report_orders_totals',
			'description' => 'Order counts grouped by status.',
			'tier'        => 'free',
			'module'      => 'class-reports-tools',
			'inputSchema' => [ 'type' => 'object', 'properties' => (object) [], 'additionalProperties' => false ],
		], [ self::class, 'orders_totals' ] );

		$registry->register( [
			'name'        => 'store_mcp_report_products_totals',
			'description' => 'Product counts grouped by type and status.',
			'tier'        => 'free',
			'module'      => 'class-reports-tools',
			'inputSchema' => [ 'type' => 'object', 'properties' => (object) [], 'additionalProperties' => false ],
		], [ self::class, 'products_totals' ] );

		$registry->register( [
			'name'        => 'store_mcp_report_sales',
			'description' => 'Aggregated sales metrics for a period (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-reports-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'period'   => [ 'type' => 'string', 'enum' => [ 'day', 'week', 'month', 'year', 'custom' ], 'default' => 'month' ],
					'date_min' => [ 'type' => 'string', 'description' => 'YYYY-MM-DD (required when period=custom).' ],
					'date_max' => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'sales' ] );

		$registry->register( [
			'name'        => 'store_mcp_report_sales_by_date',
			'description' => 'Sales time-series grouped by day/week/month — ideal for charts (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-reports-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'date_min', 'date_max' ],
				'properties' => [
					'date_min' => [ 'type' => 'string' ],
					'date_max' => [ 'type' => 'string' ],
					'interval' => [ 'type' => 'string', 'enum' => [ 'day', 'week', 'month' ], 'default' => 'day' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'sales_by_date' ] );

		$registry->register( [
			'name'        => 'store_mcp_report_top_sellers',
			'description' => 'Top selling products by quantity (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-reports-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'period'   => [ 'type' => 'string', 'enum' => [ 'day', 'week', 'month', 'year', 'custom' ], 'default' => 'month' ],
					'date_min' => [ 'type' => 'string' ],
					'date_max' => [ 'type' => 'string' ],
					'limit'    => [ 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'top_sellers' ] );

		$registry->register( [
			'name'        => 'store_mcp_report_top_earners',
			'description' => 'Top earning products by revenue (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-reports-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'period'   => [ 'type' => 'string', 'enum' => [ 'day', 'week', 'month', 'year', 'custom' ], 'default' => 'month' ],
					'date_min' => [ 'type' => 'string' ],
					'date_max' => [ 'type' => 'string' ],
					'limit'    => [ 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'top_earners' ] );

		$registry->register( [
			'name'        => 'store_mcp_report_customers_totals',
			'description' => 'Customer count totals (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-reports-tools',
			'inputSchema' => [ 'type' => 'object', 'properties' => (object) [], 'additionalProperties' => false ],
		], [ self::class, 'customers_totals' ] );

		$registry->register( [
			'name'        => 'store_mcp_report_stock_low',
			'description' => 'List products with stock at or below the low-stock threshold (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-reports-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'threshold' => [ 'type' => 'integer', 'description' => 'Override WC low-stock threshold.' ],
					'limit'     => [ 'type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 500 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'stock_low' ] );

		$registry->register( [
			'name'        => 'store_mcp_report_stock_out',
			'description' => 'List products currently out of stock (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-reports-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'limit' => [ 'type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 500 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'stock_out' ] );
	}

	public static function orders_totals( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'read' );

		$key = 'store_mcp_orders_totals';
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}

		$totals = [];
		$total  = 0;
		foreach ( self::ORDER_STATUSES as $status ) {
			$count = (int) wc_orders_count( $status );
			$totals[ $status ] = $count;
			$total += $count;
		}
		$totals['total'] = $total;

		set_transient( $key, $totals, self::cache_ttl() );
		return $totals;
	}

	public static function products_totals( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'read' );

		$key    = 'store_mcp_products_totals';
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}

		$types  = [ 'simple', 'variable', 'grouped', 'external' ];
		$out    = [ 'total' => 0, 'publish' => 0, 'draft' => 0, 'pending' => 0, 'private' => 0, 'trash' => 0 ];
		foreach ( $types as $type ) {
			$count = 0;
			$terms = get_terms( [ 'taxonomy' => 'product_type', 'hide_empty' => false, 'name' => $type ] );
			if ( ! is_wp_error( $terms ) && $terms ) {
				$count = (int) $terms[0]->count;
			}
			$out[ $type ] = $count;
		}

		$counts = (array) wp_count_posts( 'product' );
		foreach ( [ 'publish', 'draft', 'pending', 'private', 'trash' ] as $status ) {
			$out[ $status ] = isset( $counts[ $status ] ) ? (int) $counts[ $status ] : 0;
		}
		$out['total'] = $out['publish'] + $out['draft'] + $out['pending'] + $out['private'];

		set_transient( $key, $out, self::cache_ttl() );
		return $out;
	}

	public static function sales( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'view_woocommerce_reports' );

		[ $from, $to ] = self::resolve_period( $args );
		$cache_key     = 'store_mcp_sales_' . md5( $from . '|' . $to );
		$cached        = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$orders = wc_get_orders( [
			'status'       => [ 'wc-completed', 'wc-processing', 'wc-on-hold' ],
			'type'         => 'shop_order',
			'limit'        => -1,
			'date_created' => $from . '...' . $to,
			'return'       => 'objects',
		] );

		$total_sales     = 0.0;
		$total_tax       = 0.0;
		$total_shipping  = 0.0;
		$total_discount  = 0.0;
		$total_items     = 0;
		$customer_ids    = [];

		foreach ( $orders as $order ) {
			/** @var \WC_Order $order */
			$total_sales    += (float) $order->get_total();
			$total_tax      += (float) $order->get_total_tax();
			$total_shipping += (float) $order->get_shipping_total();
			$total_discount += (float) $order->get_total_discount();
			$total_items    += array_sum( array_map( static fn( $i ) => (int) $i->get_quantity(), $order->get_items() ) );
			$customer_ids[ $order->get_customer_id() ] = true;
		}

		$refunds = wc_get_orders( [
			'type'         => 'shop_order_refund',
			'limit'        => -1,
			'date_created' => $from . '...' . $to,
			'return'       => 'objects',
		] );
		$total_refunds = 0.0;
		foreach ( $refunds as $refund ) {
			$total_refunds += (float) $refund->get_amount();
		}

		$result = [
			'period'          => [ 'from' => $from, 'to' => $to ],
			'total_sales'     => round( $total_sales, 2 ),
			'net_sales'       => round( $total_sales - $total_tax - $total_shipping - $total_refunds, 2 ),
			'total_orders'    => count( $orders ),
			'total_items'     => $total_items,
			'total_tax'       => round( $total_tax, 2 ),
			'total_shipping'  => round( $total_shipping, 2 ),
			'total_refunds'   => round( $total_refunds, 2 ),
			'total_discount'  => round( $total_discount, 2 ),
			'total_customers' => count( array_filter( array_keys( $customer_ids ) ) ),
			'currency'        => get_woocommerce_currency(),
		];

		set_transient( $cache_key, $result, self::cache_ttl() );
		return $result;
	}

	public static function sales_by_date( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'view_woocommerce_reports' );

		$from     = self::sanitize_date( (string) self::require_arg( $args, 'date_min' ) );
		$to       = self::sanitize_date( (string) self::require_arg( $args, 'date_max' ) );
		$interval = (string) self::arg_enum( $args, 'interval', [ 'day', 'week', 'month' ], 'day' );

		$cache_key = 'store_mcp_sales_by_' . $interval . '_' . md5( $from . '|' . $to );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$orders = wc_get_orders( [
			'status'       => [ 'wc-completed', 'wc-processing', 'wc-on-hold' ],
			'type'         => 'shop_order',
			'limit'        => -1,
			'date_created' => $from . '...' . $to,
			'return'       => 'objects',
		] );

		$buckets = [];
		foreach ( $orders as $order ) {
			/** @var \WC_Order $order */
			$date = $order->get_date_created();
			if ( ! $date ) continue;
			$key = self::bucket_key( $date->date_i18n( 'Y-m-d' ), $interval );
			if ( ! isset( $buckets[ $key ] ) ) {
				$buckets[ $key ] = [ 'date' => $key, 'total_sales' => 0.0, 'total_orders' => 0, 'total_items' => 0 ];
			}
			$buckets[ $key ]['total_sales']  += (float) $order->get_total();
			$buckets[ $key ]['total_orders'] += 1;
			$buckets[ $key ]['total_items']  += array_sum( array_map( static fn( $i ) => (int) $i->get_quantity(), $order->get_items() ) );
		}

		ksort( $buckets );
		$out = array_values( array_map( static function ( $b ) {
			$b['total_sales'] = round( $b['total_sales'], 2 );
			return $b;
		}, $buckets ) );

		$result = [ 'interval' => $interval, 'currency' => get_woocommerce_currency(), 'items' => $out ];
		set_transient( $cache_key, $result, self::cache_ttl() );
		return $result;
	}

	public static function top_sellers( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'view_woocommerce_reports' );

		[ $from, $to ] = self::resolve_period( $args );
		$limit         = (int) self::arg_int( $args, 'limit', 10, 1, 100 );

		return self::aggregate_by_product( $from, $to, 'quantity', $limit );
	}

	public static function top_earners( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'view_woocommerce_reports' );

		[ $from, $to ] = self::resolve_period( $args );
		$limit         = (int) self::arg_int( $args, 'limit', 10, 1, 100 );

		return self::aggregate_by_product( $from, $to, 'revenue', $limit );
	}

	public static function customers_totals( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'view_woocommerce_reports' );

		$cache_key = 'store_mcp_customers_totals';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$customers_q = new \WP_User_Query( [
			'role__in' => [ 'customer' ],
			'fields'   => 'ID',
			'number'   => -1,
		] );
		$total = (int) $customers_q->get_total();

		global $wpdb;
		$orders_table = $wpdb->prefix . 'wc_orders';
		$paying       = 0;
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$paying = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT customer_id) FROM {$orders_table} WHERE customer_id > 0 AND status IN ('wc-completed','wc-processing')" );
		} else {
			$paying = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT pm.meta_value) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = %s AND p.post_status IN ('wc-completed','wc-processing') AND pm.meta_key = %s AND pm.meta_value > 0",
				'shop_order',
				'_customer_user'
			) );
		}

		$result = [
			'total_customers'   => $total,
			'paying_customers'  => $paying,
			'non_paying'        => max( 0, $total - $paying ),
		];
		set_transient( $cache_key, $result, self::cache_ttl() );
		return $result;
	}

	public static function stock_low( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$threshold = self::arg_int( $args, 'threshold' );
		if ( null === $threshold ) {
			$threshold = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
		}
		$limit = (int) self::arg_int( $args, 'limit', 100, 1, 500 );

		$products = wc_get_products( [
			'limit'         => $limit,
			'status'        => 'publish',
			'stock_status'  => 'instock',
			'manage_stock'  => true,
			'meta_query'    => [
				[ 'key' => '_stock', 'value' => $threshold, 'compare' => '<=', 'type' => 'NUMERIC' ],
			],
			'return'        => 'objects',
		] );

		$items = array_map( static fn( $p ) => [
			'product_id'     => $p->get_id(),
			'name'           => $p->get_name(),
			'sku'            => $p->get_sku(),
			'stock_quantity' => $p->get_stock_quantity(),
		], $products );

		return [ 'threshold' => $threshold, 'items' => $items ];
	}

	public static function stock_out( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$limit    = (int) self::arg_int( $args, 'limit', 100, 1, 500 );
		$products = wc_get_products( [
			'limit'        => $limit,
			'status'       => 'publish',
			'stock_status' => 'outofstock',
			'return'       => 'objects',
		] );

		$items = array_map( static fn( $p ) => [
			'product_id' => $p->get_id(),
			'name'       => $p->get_name(),
			'sku'        => $p->get_sku(),
		], $products );

		return [ 'items' => $items ];
	}

	private static function aggregate_by_product( string $from, string $to, string $mode, int $limit ): array {
		$cache_key = 'store_mcp_top_' . $mode . '_' . md5( $from . '|' . $to . '|' . $limit );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$orders = wc_get_orders( [
			'status'       => [ 'wc-completed', 'wc-processing', 'wc-on-hold' ],
			'type'         => 'shop_order',
			'limit'        => -1,
			'date_created' => $from . '...' . $to,
			'return'       => 'objects',
		] );

		$tally = [];
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				/** @var \WC_Order_Item_Product $item */
				$pid = $item->get_product_id();
				if ( ! $pid ) continue;
				if ( ! isset( $tally[ $pid ] ) ) {
					$tally[ $pid ] = [ 'product_id' => $pid, 'quantity' => 0, 'revenue' => 0.0 ];
				}
				$tally[ $pid ]['quantity'] += (int) $item->get_quantity();
				$tally[ $pid ]['revenue']  += (float) $item->get_total();
			}
		}

		usort( $tally, static fn( $a, $b ) => $b[ $mode ] <=> $a[ $mode ] );
		$top = array_slice( $tally, 0, $limit );

		foreach ( $top as &$row ) {
			$row['name']    = get_the_title( $row['product_id'] );
			$row['revenue'] = round( $row['revenue'], 2 );
		}
		unset( $row );

		$result = [ 'period' => [ 'from' => $from, 'to' => $to ], 'items' => array_values( $top ) ];
		set_transient( $cache_key, $result, self::cache_ttl() );
		return $result;
	}

	private static function resolve_period( array $args ): array {
		$period = (string) self::arg_enum( $args, 'period', [ 'day', 'week', 'month', 'year', 'custom' ], 'month' );

		$today = current_time( 'Y-m-d' );
		switch ( $period ) {
			case 'day':
				$from = $to = $today;
				break;
			case 'week':
				$from = gmdate( 'Y-m-d', strtotime( '-6 days', strtotime( $today ) ) );
				$to   = $today;
				break;
			case 'year':
				$from = gmdate( 'Y-01-01' );
				$to   = $today;
				break;
			case 'custom':
				$from = self::sanitize_date( (string) self::require_arg( $args, 'date_min' ) );
				$to   = self::sanitize_date( (string) self::require_arg( $args, 'date_max' ) );
				break;
			case 'month':
			default:
				$from = gmdate( 'Y-m-01' );
				$to   = $today;
		}
		return [ $from, $to ];
	}

	private static function sanitize_date( string $date ): string {
		$ts = strtotime( $date );
		if ( ! $ts ) {
			throw new Tool_Exception( __( 'Invalid date', 'store-mcp' ), Server::RPC_INVALID_PARAMS );
		}
		return gmdate( 'Y-m-d', $ts );
	}

	private static function bucket_key( string $ymd, string $interval ): string {
		$ts = strtotime( $ymd );
		if ( ! $ts ) return $ymd;
		return match ( $interval ) {
			'month' => gmdate( 'Y-m', $ts ),
			'week'  => gmdate( 'o-\WW', $ts ),
			default => gmdate( 'Y-m-d', $ts ),
		};
	}

	private static function cache_ttl(): int {
		return max( 0, (int) get_option( 'store_mcp_cache_ttl_seconds', 300 ) );
	}
}

add_action( 'store_mcp_register_tools', [ Reports_Tools::class, 'register' ] );
