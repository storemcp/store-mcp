<?php
/**
 * Orders tools — full WooCommerce order management (PRO).
 *
 * All queries go through wc_get_orders() which is HPOS-compatible.
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Orders_Tools {
	use Tool_Base;

	const STATUSES = [ 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed', 'trash', 'any' ];

	public static function register( Tools_Registry $registry ): void {
		$common_props = [
			'customer_id'      => [ 'type' => 'integer' ],
			'status'           => [ 'type' => 'string', 'enum' => self::STATUSES ],
			'billing'          => self::address_schema(),
			'shipping'         => self::address_schema(),
			'line_items'       => [
				'type'  => 'array',
				'items' => [
					'type' => 'object',
					'properties' => [
						'id'           => [ 'type' => 'integer', 'description' => 'Existing line-item id (for updates).' ],
						'product_id'   => [ 'type' => 'integer' ],
						'variation_id' => [ 'type' => 'integer' ],
						'quantity'     => [ 'type' => 'integer', 'minimum' => 0 ],
						'subtotal'     => [ 'type' => 'number' ],
						'total'        => [ 'type' => 'number' ],
					],
				],
			],
			'shipping_lines'   => [
				'type'  => 'array',
				'items' => [
					'type' => 'object',
					'properties' => [
						'method_id'    => [ 'type' => 'string' ],
						'method_title' => [ 'type' => 'string' ],
						'total'        => [ 'type' => 'number' ],
					],
				],
			],
			'fee_lines'        => [
				'type'  => 'array',
				'items' => [
					'type' => 'object',
					'properties' => [
						'name'  => [ 'type' => 'string' ],
						'total' => [ 'type' => 'number' ],
					],
				],
			],
			'coupon_lines'     => [
				'type'  => 'array',
				'items' => [
					'type' => 'object',
					'properties' => [
						'code' => [ 'type' => 'string' ],
					],
				],
			],
			'payment_method'       => [ 'type' => 'string' ],
			'payment_method_title' => [ 'type' => 'string' ],
			'customer_note'        => [ 'type' => 'string' ],
			'meta_data'            => [
				'type'  => 'array',
				'items' => [
					'type' => 'object',
					'properties' => [
						'key'   => [ 'type' => 'string' ],
						'value' => [],
					],
				],
			],
		];

		$registry->register( [
			'name'        => 'store_mcp_list_orders',
			'description' => 'List WooCommerce orders with rich filters. HPOS-compatible.',
			'tier'        => 'pro',
			'module'      => 'class-orders-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'status'       => [ 'type' => 'string', 'enum' => self::STATUSES ],
					'date_after'   => [ 'type' => 'string', 'description' => 'ISO 8601.' ],
					'date_before'  => [ 'type' => 'string' ],
					'customer_id'  => [ 'type' => 'integer' ],
					'product_id'   => [ 'type' => 'integer', 'description' => 'Only orders containing this product.' ],
					'search'       => [ 'type' => 'string', 'description' => 'Matches order id/number/billing email/name.' ],
					'min_total'    => [ 'type' => 'number' ],
					'max_total'    => [ 'type' => 'number' ],
					'orderby'      => [ 'type' => 'string', 'enum' => [ 'date', 'id', 'total', 'modified' ], 'default' => 'date' ],
					'order'        => [ 'type' => 'string', 'enum' => [ 'asc', 'desc' ], 'default' => 'desc' ],
					'page'         => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page'     => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_orders' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_order',
			'description' => 'Get a full order including items, addresses, refunds and meta.',
			'tier'        => 'pro',
			'module'      => 'class-orders-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_order' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_order',
			'description' => 'Create a new order.',
			'tier'        => 'pro',
			'module'      => 'class-orders-tools',
			'inputSchema' => [ 'type' => 'object', 'properties' => $common_props, 'additionalProperties' => false ],
		], [ self::class, 'create_order' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_order',
			'description' => 'Update an existing order.',
			'tier'        => 'pro',
			'module'      => 'class-orders-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => array_merge( [ 'id' => [ 'type' => 'integer' ] ], $common_props ),
				'additionalProperties' => false,
			],
		], [ self::class, 'update_order' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_order',
			'description' => 'Delete an order. force=true permanently removes; otherwise trashes.',
			'tier'        => 'pro',
			'module'      => 'class-orders-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'    => [ 'type' => 'integer' ],
					'force' => [ 'type' => 'boolean', 'default' => false ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_order' ] );

		$registry->register( [
			'name'        => 'store_mcp_list_order_notes',
			'description' => 'List internal and customer notes on an order.',
			'tier'        => 'pro',
			'module'      => 'class-orders-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'order_id' ],
				'properties' => [
					'order_id' => [ 'type' => 'integer' ],
					'type'     => [ 'type' => 'string', 'enum' => [ 'any', 'customer', 'internal' ], 'default' => 'any' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_order_notes' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_order_note',
			'description' => 'Add a note to an order. customer_note=true makes it visible to the customer and emails them.',
			'tier'        => 'pro',
			'module'      => 'class-orders-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'order_id', 'note' ],
				'properties' => [
					'order_id'      => [ 'type' => 'integer' ],
					'note'          => [ 'type' => 'string' ],
					'customer_note' => [ 'type' => 'boolean', 'default' => false ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_order_note' ] );
	}

	private static function address_schema(): array {
		$props = [
			'first_name' => [ 'type' => 'string' ],
			'last_name'  => [ 'type' => 'string' ],
			'company'    => [ 'type' => 'string' ],
			'address_1'  => [ 'type' => 'string' ],
			'address_2'  => [ 'type' => 'string' ],
			'city'       => [ 'type' => 'string' ],
			'state'      => [ 'type' => 'string' ],
			'postcode'   => [ 'type' => 'string' ],
			'country'    => [ 'type' => 'string', 'description' => 'ISO 3166-1 alpha-2.' ],
			'email'      => [ 'type' => 'string', 'format' => 'email' ],
			'phone'      => [ 'type' => 'string' ],
		];
		return [ 'type' => 'object', 'properties' => $props, 'additionalProperties' => false ];
	}

	public static function list_orders( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'edit_shop_orders' );

		$pagination = self::pagination( $args );

		$query_args = [
			'limit'    => $pagination['per_page'],
			'page'     => $pagination['page'],
			'paginate' => true,
			'orderby'  => self::arg_enum( $args, 'orderby', [ 'date', 'id', 'total', 'modified' ], 'date' ),
			'order'    => strtoupper( (string) self::arg_enum( $args, 'order', [ 'asc', 'desc' ], 'desc' ) ),
			'type'     => 'shop_order',
			'return'   => 'objects',
		];

		$status = self::arg_enum( $args, 'status', self::STATUSES );
		if ( $status && 'any' !== $status ) {
			$query_args['status'] = 'wc-' . $status;
		}

		if ( $cid = self::arg_int( $args, 'customer_id' ) ) {
			$query_args['customer_id'] = $cid;
		}
		if ( $pid = self::arg_int( $args, 'product_id' ) ) {
			$query_args['product'] = $pid;
		}
		if ( $s = self::arg_string( $args, 'search', '' ) ) {
			$query_args['s'] = $s;
		}

		$after  = self::arg_string( $args, 'date_after', '' );
		$before = self::arg_string( $args, 'date_before', '' );
		if ( $after && $before ) {
			$query_args['date_created'] = $after . '...' . $before;
		} elseif ( $after ) {
			$query_args['date_created'] = '>=' . $after;
		} elseif ( $before ) {
			$query_args['date_created'] = '<=' . $before;
		}

		$min = self::arg_float( $args, 'min_total' );
		$max = self::arg_float( $args, 'max_total' );
		if ( null !== $min || null !== $max ) {
			$meta = [];
			if ( null !== $min ) $meta[] = [ 'key' => '_order_total', 'value' => $min, 'compare' => '>=', 'type' => 'NUMERIC' ];
			if ( null !== $max ) $meta[] = [ 'key' => '_order_total', 'value' => $max, 'compare' => '<=', 'type' => 'NUMERIC' ];
			$query_args['meta_query'] = $meta;
		}

		$result = wc_get_orders( $query_args );
		$items  = array_map( [ self::class, 'format_order_summary' ], $result->orders );

		return self::paginated( $items, (int) $result->total, $pagination['page'], $pagination['per_page'] );
	}

	public static function get_order( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();

		$id    = (int) self::require_arg( $args, 'id' );
		$order = wc_get_order( $id );
		if ( ! $order ) {
			throw new Tool_Exception( esc_html__( 'Order not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		Permissions::require_cap( $context, 'edit_shop_order', $id );

		return self::format_order_full( $order );
	}

	public static function create_order( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'edit_shop_orders' );

		$order = wc_create_order( [
			'status'      => self::resolve_status( self::arg_enum( $args, 'status', self::STATUSES ) ),
			'customer_id' => (int) self::arg_int( $args, 'customer_id', 0 ),
		] );
		if ( is_wp_error( $order ) ) {
			throw new Tool_Exception( esc_html( $order->get_error_message() ), Server::ERR_TOOL_FAILED );
		}

		self::apply_order_data( $order, $args );
		$order->calculate_totals();
		$order->save();

		return self::format_order_full( wc_get_order( $order->get_id() ) );
	}

	public static function update_order( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();

		$id    = (int) self::require_arg( $args, 'id' );
		$order = wc_get_order( $id );
		if ( ! $order ) {
			throw new Tool_Exception( esc_html__( 'Order not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		Permissions::require_cap( $context, 'edit_shop_order', $id );

		self::apply_order_data( $order, $args );
		if ( array_key_exists( 'status', $args ) ) {
			$order->update_status( self::resolve_status( self::arg_enum( $args, 'status', self::STATUSES ) ) );
		}
		$order->save();

		return self::format_order_full( wc_get_order( $id ) );
	}

	public static function delete_order( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();

		$id    = (int) self::require_arg( $args, 'id' );
		$order = wc_get_order( $id );
		if ( ! $order ) {
			throw new Tool_Exception( esc_html__( 'Order not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		Permissions::require_cap( $context, 'delete_shop_order', $id );

		$force = (bool) self::arg_bool( $args, 'force', false );
		$ok    = $order->delete( $force );
		if ( ! $ok ) {
			throw new Tool_Exception( esc_html__( 'Could not delete order', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}
		return [ 'id' => $id, 'deleted' => true, 'force' => $force ];
	}

	public static function list_order_notes( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();

		$order_id = (int) self::require_arg( $args, 'order_id' );
		Permissions::require_cap( $context, 'edit_shop_order', $order_id );

		$type = (string) self::arg_enum( $args, 'type', [ 'any', 'customer', 'internal' ], 'any' );

		$notes = wc_get_order_notes( [
			'order_id' => $order_id,
			'type'     => 'any' === $type ? '' : $type,
			'orderby'  => 'date_created',
			'order'    => 'DESC',
			'limit'    => 100,
		] );

		return [ 'items' => array_map( [ self::class, 'format_note' ], $notes ), 'count' => count( $notes ) ];
	}

	public static function create_order_note( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();

		$order_id = (int) self::require_arg( $args, 'order_id' );
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new Tool_Exception( esc_html__( 'Order not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		Permissions::require_cap( $context, 'edit_shop_order', $order_id );

		$note     = (string) self::require_arg( $args, 'note' );
		$is_cust  = (bool) self::arg_bool( $args, 'customer_note', false );

		$note_id = $order->add_order_note( $note, $is_cust, true );
		if ( ! $note_id ) {
			throw new Tool_Exception( esc_html__( 'Could not add note', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}
		return [ 'id' => (int) $note_id, 'order_id' => $order_id, 'customer_note' => $is_cust, 'note' => $note ];
	}

	private static function resolve_status( ?string $status ): string {
		if ( null === $status || 'any' === $status ) {
			return 'pending';
		}
		return $status;
	}

	private static function apply_order_data( \WC_Order $order, array $args ): void {
		if ( array_key_exists( 'customer_id', $args ) ) {
			$order->set_customer_id( (int) $args['customer_id'] );
		}
		if ( array_key_exists( 'customer_note', $args ) ) {
			$order->set_customer_note( sanitize_textarea_field( (string) $args['customer_note'] ) );
		}
		if ( array_key_exists( 'payment_method', $args ) ) {
			$order->set_payment_method( sanitize_text_field( (string) $args['payment_method'] ) );
		}
		if ( array_key_exists( 'payment_method_title', $args ) ) {
			$order->set_payment_method_title( sanitize_text_field( (string) $args['payment_method_title'] ) );
		}

		foreach ( [ 'billing', 'shipping' ] as $ctx ) {
			if ( isset( $args[ $ctx ] ) && is_array( $args[ $ctx ] ) ) {
				$address = [];
				foreach ( $args[ $ctx ] as $k => $v ) {
					$address[ sanitize_key( (string) $k ) ] = sanitize_text_field( (string) $v );
				}
				$order->set_address( $address, $ctx );
			}
		}

		if ( isset( $args['line_items'] ) && is_array( $args['line_items'] ) ) {
			self::apply_line_items( $order, $args['line_items'] );
		}
		if ( isset( $args['shipping_lines'] ) && is_array( $args['shipping_lines'] ) ) {
			self::apply_shipping_lines( $order, $args['shipping_lines'] );
		}
		if ( isset( $args['fee_lines'] ) && is_array( $args['fee_lines'] ) ) {
			self::apply_fee_lines( $order, $args['fee_lines'] );
		}
		if ( isset( $args['coupon_lines'] ) && is_array( $args['coupon_lines'] ) ) {
			self::apply_coupon_lines( $order, $args['coupon_lines'] );
		}
		if ( isset( $args['meta_data'] ) && is_array( $args['meta_data'] ) ) {
			foreach ( $args['meta_data'] as $meta ) {
				if ( ! empty( $meta['key'] ) ) {
					$order->update_meta_data( sanitize_key( (string) $meta['key'] ), $meta['value'] ?? '' );
				}
			}
		}
	}

	private static function apply_line_items( \WC_Order $order, array $items ): void {
		foreach ( $items as $item ) {
			if ( ! empty( $item['id'] ) ) {
				$line = $order->get_item( (int) $item['id'] );
				if ( ! $line ) continue;
				if ( isset( $item['quantity'] ) ) {
					$qty = (int) $item['quantity'];
					if ( $qty <= 0 ) {
						$order->remove_item( (int) $item['id'] );
						continue;
					}
					$line->set_quantity( $qty );
				}
				if ( isset( $item['subtotal'] ) ) $line->set_subtotal( (float) $item['subtotal'] );
				if ( isset( $item['total'] ) )    $line->set_total( (float) $item['total'] );
				$line->save();
				continue;
			}
			$product_id   = (int) ( $item['product_id'] ?? 0 );
			$variation_id = (int) ( $item['variation_id'] ?? 0 );
			$target_id    = $variation_id ?: $product_id;
			if ( ! $target_id ) continue;
			$product = wc_get_product( $target_id );
			if ( ! $product ) continue;
			$qty = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			$order->add_product( $product, $qty );
		}
	}

	private static function apply_shipping_lines( \WC_Order $order, array $lines ): void {
		foreach ( $lines as $line ) {
			$item = new \WC_Order_Item_Shipping();
			$item->set_method_id( sanitize_text_field( (string) ( $line['method_id'] ?? '' ) ) );
			$item->set_method_title( sanitize_text_field( (string) ( $line['method_title'] ?? '' ) ) );
			$item->set_total( (string) ( $line['total'] ?? 0 ) );
			$order->add_item( $item );
		}
	}

	private static function apply_fee_lines( \WC_Order $order, array $lines ): void {
		foreach ( $lines as $line ) {
			$item = new \WC_Order_Item_Fee();
			$item->set_name( sanitize_text_field( (string) ( $line['name'] ?? __( 'Fee', 'store-mcp' ) ) ) );
			$item->set_total( (string) ( $line['total'] ?? 0 ) );
			$order->add_item( $item );
		}
	}

	private static function apply_coupon_lines( \WC_Order $order, array $lines ): void {
		foreach ( $lines as $line ) {
			$code = sanitize_text_field( (string) ( $line['code'] ?? '' ) );
			if ( '' === $code ) continue;
			$order->apply_coupon( $code );
		}
	}

	public static function format_order_summary( \WC_Order $order ): array {
		return [
			'id'             => $order->get_id(),
			'number'         => $order->get_order_number(),
			'status'         => $order->get_status(),
			'date_created'   => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'c' ) : null,
			'date_modified'  => $order->get_date_modified() ? $order->get_date_modified()->date_i18n( 'c' ) : null,
			'total'          => (float) $order->get_total(),
			'currency'       => $order->get_currency(),
			'customer_id'    => $order->get_customer_id(),
			'billing'        => [
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
				'city'       => $order->get_billing_city(),
				'country'    => $order->get_billing_country(),
			],
			'line_items'     => array_values( array_map( static fn( \WC_Order_Item_Product $i ) => [
				'product_id' => $i->get_product_id(),
				'variation_id' => $i->get_variation_id(),
				'name'       => $i->get_name(),
				'quantity'   => $i->get_quantity(),
				'total'      => (float) $i->get_total(),
			], $order->get_items() ) ),
			'payment_method'       => $order->get_payment_method(),
			'payment_method_title' => $order->get_payment_method_title(),
		];
	}

	public static function format_order_full( \WC_Order $order ): array {
		$base = self::format_order_summary( $order );
		$base['shipping'] = [
			'first_name' => $order->get_shipping_first_name(),
			'last_name'  => $order->get_shipping_last_name(),
			'address_1'  => $order->get_shipping_address_1(),
			'address_2'  => $order->get_shipping_address_2(),
			'city'       => $order->get_shipping_city(),
			'state'      => $order->get_shipping_state(),
			'postcode'   => $order->get_shipping_postcode(),
			'country'    => $order->get_shipping_country(),
		];
		$base['billing'] = array_merge( $base['billing'], [
			'company'   => $order->get_billing_company(),
			'address_1' => $order->get_billing_address_1(),
			'address_2' => $order->get_billing_address_2(),
			'state'     => $order->get_billing_state(),
			'postcode'  => $order->get_billing_postcode(),
		] );
		$base['totals'] = [
			'subtotal'       => (float) $order->get_subtotal(),
			'total_tax'      => (float) $order->get_total_tax(),
			'shipping_total' => (float) $order->get_shipping_total(),
			'shipping_tax'   => (float) $order->get_shipping_tax(),
			'discount_total' => (float) $order->get_discount_total(),
			'total'          => (float) $order->get_total(),
		];
		$base['tax_lines']       = array_values( array_map( static fn( $t ) => [
			'rate_code' => $t->get_rate_code(),
			'label'     => $t->get_label(),
			'total'     => (float) $t->get_tax_total(),
		], $order->get_items( 'tax' ) ) );
		$base['shipping_lines']  = array_values( array_map( static fn( $s ) => [
			'method_title' => $s->get_method_title(),
			'method_id'    => $s->get_method_id(),
			'total'        => (float) $s->get_total(),
		], $order->get_items( 'shipping' ) ) );
		$base['fee_lines']       = array_values( array_map( static fn( $f ) => [
			'name'  => $f->get_name(),
			'total' => (float) $f->get_total(),
		], $order->get_items( 'fee' ) ) );
		$base['coupon_lines']    = array_values( array_map( static fn( $c ) => [
			'code'     => $c->get_code(),
			'discount' => (float) $c->get_discount(),
		], $order->get_items( 'coupon' ) ) );
		$base['refunds']         = array_values( array_map( static fn( $r ) => [
			'id'     => $r->get_id(),
			'amount' => (float) $r->get_amount(),
			'reason' => $r->get_reason(),
		], $order->get_refunds() ) );
		$base['customer_note']   = $order->get_customer_note();
		$base['date_paid']       = $order->get_date_paid() ? $order->get_date_paid()->date_i18n( 'c' ) : null;
		$base['transaction_id']  = $order->get_transaction_id();
		$base['meta_data']       = array_values( array_map( static fn( $m ) => [ 'key' => $m->key, 'value' => $m->value ], $order->get_meta_data() ) );

		return $base;
	}

	private static function format_note( $note ): array {
		return [
			'id'            => (int) $note->id,
			'date_created'  => $note->date_created ? $note->date_created->date_i18n( 'c' ) : null,
			'note'          => $note->content,
			'customer_note' => (bool) $note->customer_note,
			'added_by'      => $note->added_by,
		];
	}
}

add_action( 'store_mcp_register_tools', [ Orders_Tools::class, 'register' ] );
