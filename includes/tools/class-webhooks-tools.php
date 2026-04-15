<?php
/**
 * Webhooks tools — WooCommerce webhooks CRUD (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Webhooks_Tools {
	use Tool_Base;

	const TOPICS = [
		'coupon.created', 'coupon.updated', 'coupon.deleted', 'coupon.restored',
		'customer.created', 'customer.updated', 'customer.deleted',
		'order.created', 'order.updated', 'order.deleted', 'order.restored',
		'product.created', 'product.updated', 'product.deleted', 'product.restored',
	];
	const STATUSES = [ 'active', 'paused', 'disabled' ];

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_webhooks',
			'description' => 'List WooCommerce webhooks.',
			'tier'        => 'pro',
			'module'      => 'class-webhooks-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'status'   => [ 'type' => 'string', 'enum' => self::STATUSES ],
					'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_webhooks' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_webhook',
			'description' => 'Get a webhook by id.',
			'tier'        => 'pro',
			'module'      => 'class-webhooks-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_webhook' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_webhook',
			'description' => 'Create a webhook. Common topics: order.created, product.updated, customer.created.',
			'tier'        => 'pro',
			'module'      => 'class-webhooks-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'name', 'topic', 'delivery_url' ],
				'properties' => [
					'name'         => [ 'type' => 'string' ],
					'topic'        => [ 'type' => 'string' ],
					'delivery_url' => [ 'type' => 'string', 'format' => 'uri' ],
					'secret'       => [ 'type' => 'string' ],
					'status'       => [ 'type' => 'string', 'enum' => self::STATUSES, 'default' => 'active' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_webhook' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_webhook',
			'description' => 'Update a webhook.',
			'tier'        => 'pro',
			'module'      => 'class-webhooks-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'           => [ 'type' => 'integer' ],
					'name'         => [ 'type' => 'string' ],
					'topic'        => [ 'type' => 'string' ],
					'delivery_url' => [ 'type' => 'string', 'format' => 'uri' ],
					'secret'       => [ 'type' => 'string' ],
					'status'       => [ 'type' => 'string', 'enum' => self::STATUSES ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_webhook' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_webhook',
			'description' => 'Delete a webhook.',
			'tier'        => 'pro',
			'module'      => 'class-webhooks-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'    => [ 'type' => 'integer' ],
					'force' => [ 'type' => 'boolean', 'default' => true ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_webhook' ] );
	}

	public static function list_webhooks( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$data_store = \WC_Data_Store::load( 'webhook' );
		$pagination = self::pagination( $args );
		$status     = self::arg_enum( $args, 'status', self::STATUSES );

		$ids = $data_store->get_webhooks_ids( $status ?: '' );
		$total = count( $ids );
		$slice = array_slice( $ids, ( $pagination['page'] - 1 ) * $pagination['per_page'], $pagination['per_page'] );

		$items = [];
		foreach ( $slice as $id ) {
			$items[] = self::format_webhook( new \WC_Webhook( (int) $id ) );
		}
		return self::paginated( $items, $total, $pagination['page'], $pagination['per_page'] );
	}

	public static function get_webhook( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$id      = (int) self::require_arg( $args, 'id' );
		$webhook = new \WC_Webhook( $id );
		if ( ! $webhook->get_id() ) {
			throw new Tool_Exception( esc_html__( 'Webhook not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		return self::format_webhook( $webhook );
	}

	public static function create_webhook( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$topic = sanitize_text_field( (string) self::require_arg( $args, 'topic' ) );
		if ( ! wc_is_webhook_valid_topic( $topic ) ) {
			throw new Tool_Exception( esc_html__( 'Invalid topic', 'store-mcp' ), Server::RPC_INVALID_PARAMS );
		}

		$webhook = new \WC_Webhook();
		$webhook->set_name( sanitize_text_field( (string) self::require_arg( $args, 'name' ) ) );
		$webhook->set_user_id( $context->user_id );
		$webhook->set_topic( $topic );
		$webhook->set_delivery_url( esc_url_raw( (string) self::require_arg( $args, 'delivery_url' ) ) );
		$webhook->set_secret( self::arg_string( $args, 'secret', wp_generate_password( 32, false ) ) );
		$webhook->set_status( (string) self::arg_enum( $args, 'status', self::STATUSES, 'active' ) );
		$webhook->set_api_version( 'wp_api_v3' );
		$webhook->save();

		return self::format_webhook( new \WC_Webhook( $webhook->get_id() ) );
	}

	public static function update_webhook( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$id      = (int) self::require_arg( $args, 'id' );
		$webhook = new \WC_Webhook( $id );
		if ( ! $webhook->get_id() ) {
			throw new Tool_Exception( esc_html__( 'Webhook not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		if ( array_key_exists( 'name', $args ) )         $webhook->set_name( sanitize_text_field( (string) $args['name'] ) );
		if ( array_key_exists( 'delivery_url', $args ) ) $webhook->set_delivery_url( esc_url_raw( (string) $args['delivery_url'] ) );
		if ( array_key_exists( 'secret', $args ) )       $webhook->set_secret( (string) $args['secret'] );
		if ( array_key_exists( 'topic', $args ) ) {
			$topic = sanitize_text_field( (string) $args['topic'] );
			if ( ! wc_is_webhook_valid_topic( $topic ) ) {
				throw new Tool_Exception( esc_html__( 'Invalid topic', 'store-mcp' ), Server::RPC_INVALID_PARAMS );
			}
			$webhook->set_topic( $topic );
		}
		if ( array_key_exists( 'status', $args ) ) {
			$webhook->set_status( (string) self::arg_enum( $args, 'status', self::STATUSES ) );
		}

		$webhook->save();
		return self::format_webhook( new \WC_Webhook( $id ) );
	}

	public static function delete_webhook( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_woocommerce' );

		$id      = (int) self::require_arg( $args, 'id' );
		$webhook = new \WC_Webhook( $id );
		if ( ! $webhook->get_id() ) {
			throw new Tool_Exception( esc_html__( 'Webhook not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		$force = (bool) self::arg_bool( $args, 'force', true );
		$webhook->delete( $force );
		return [ 'id' => $id, 'deleted' => true ];
	}

	private static function format_webhook( \WC_Webhook $w ): array {
		$secret = $w->get_secret();
		return [
			'id'              => $w->get_id(),
			'name'            => $w->get_name(),
			'status'          => $w->get_status(),
			'topic'           => $w->get_topic(),
			'resource'        => $w->get_resource(),
			'event'           => $w->get_event(),
			'delivery_url'    => $w->get_delivery_url(),
			'secret_hint'     => $secret ? substr( $secret, 0, 4 ) . str_repeat( '*', max( 0, strlen( $secret ) - 4 ) ) : '',
			'api_version'     => $w->get_api_version(),
			'date_created'    => $w->get_date_created() ? $w->get_date_created()->date_i18n( 'c' ) : null,
			'date_modified'   => $w->get_date_modified() ? $w->get_date_modified()->date_i18n( 'c' ) : null,
			'failure_count'   => (int) $w->get_failure_count(),
			'user_id'         => (int) $w->get_user_id(),
		];
	}
}

add_action( 'store_mcp_register_tools', [ Webhooks_Tools::class, 'register' ] );
