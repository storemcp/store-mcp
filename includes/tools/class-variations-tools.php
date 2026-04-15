<?php
/**
 * Product variations tools (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Variations_Tools {
	use Tool_Base;

	public static function register( Tools_Registry $registry ): void {
		$mutable = [
			'regular_price'    => [ 'type' => [ 'number', 'string' ] ],
			'sale_price'       => [ 'type' => [ 'number', 'string', 'null' ] ],
			'sku'              => [ 'type' => 'string' ],
			'stock_quantity'   => [ 'type' => [ 'integer', 'null' ] ],
			'stock_status'     => [ 'type' => 'string', 'enum' => Products_Tools::STOCK ],
			'manage_stock'     => [ 'type' => 'boolean' ],
			'description'      => [ 'type' => 'string' ],
			'status'           => [ 'type' => 'string', 'enum' => [ 'publish', 'private' ] ],
			'weight'           => [ 'type' => [ 'number', 'null' ] ],
			'dimensions'       => [
				'type'       => 'object',
				'properties' => [
					'length' => [ 'type' => [ 'number', 'null' ] ],
					'width'  => [ 'type' => [ 'number', 'null' ] ],
					'height' => [ 'type' => [ 'number', 'null' ] ],
				],
			],
			'image'            => [
				'type' => 'object',
				'properties' => [
					'id'  => [ 'type' => 'integer' ],
					'src' => [ 'type' => 'string' ],
					'alt' => [ 'type' => 'string' ],
				],
			],
			'attributes'       => [
				'type'  => 'array',
				'description' => 'Each item: {name: string, option: string}.',
				'items' => [
					'type' => 'object',
					'properties' => [
						'name'   => [ 'type' => 'string' ],
						'option' => [ 'type' => 'string' ],
					],
				],
			],
		];

		$registry->register( [
			'name'        => 'store_mcp_list_variations',
			'description' => 'List variations of a variable product.',
			'tier'        => 'pro',
			'module'      => 'class-variations-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'product_id' ],
				'properties' => [
					'product_id' => [ 'type' => 'integer' ],
					'page'       => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page'   => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_variations' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_variation',
			'description' => 'Get a specific variation.',
			'tier'        => 'pro',
			'module'      => 'class-variations-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'product_id', 'variation_id' ],
				'properties' => [
					'product_id'   => [ 'type' => 'integer' ],
					'variation_id' => [ 'type' => 'integer' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_variation' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_variation',
			'description' => 'Create a variation on a variable product.',
			'tier'        => 'pro',
			'module'      => 'class-variations-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'product_id' ],
				'properties' => array_merge( [ 'product_id' => [ 'type' => 'integer' ] ], $mutable ),
				'additionalProperties' => false,
			],
		], [ self::class, 'create_variation' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_variation',
			'description' => 'Update a variation.',
			'tier'        => 'pro',
			'module'      => 'class-variations-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'product_id', 'variation_id' ],
				'properties' => array_merge( [
					'product_id'   => [ 'type' => 'integer' ],
					'variation_id' => [ 'type' => 'integer' ],
				], $mutable ),
				'additionalProperties' => false,
			],
		], [ self::class, 'update_variation' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_variation',
			'description' => 'Delete a variation.',
			'tier'        => 'pro',
			'module'      => 'class-variations-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'product_id', 'variation_id' ],
				'properties' => [
					'product_id'   => [ 'type' => 'integer' ],
					'variation_id' => [ 'type' => 'integer' ],
					'force'        => [ 'type' => 'boolean', 'default' => true ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_variation' ] );
	}

	public static function list_variations( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'edit_products' );

		$product_id = (int) self::require_arg( $args, 'product_id' );
		$product    = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			throw new Tool_Exception( __( 'Variable product not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$pagination = self::pagination( $args, 50 );
		$children   = $product->get_children();
		$total      = count( $children );
		$slice      = array_slice( $children, ( $pagination['page'] - 1 ) * $pagination['per_page'], $pagination['per_page'] );

		$items = [];
		foreach ( $slice as $vid ) {
			$variation = wc_get_product( $vid );
			if ( $variation instanceof \WC_Product_Variation ) {
				$items[] = self::format_variation( $variation );
			}
		}
		return self::paginated( $items, $total, $pagination['page'], $pagination['per_page'] );
	}

	public static function get_variation( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'edit_products' );

		$vid       = (int) self::require_arg( $args, 'variation_id' );
		$variation = wc_get_product( $vid );
		if ( ! $variation instanceof \WC_Product_Variation ) {
			throw new Tool_Exception( __( 'Variation not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		return self::format_variation( $variation );
	}

	public static function create_variation( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'edit_products' );

		$product_id = (int) self::require_arg( $args, 'product_id' );
		$product    = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			throw new Tool_Exception( __( 'Variable product not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $product_id );
		self::apply_variation_data( $variation, $args );
		$variation->save();

		return self::format_variation( wc_get_product( $variation->get_id() ) );
	}

	public static function update_variation( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'edit_products' );

		$vid       = (int) self::require_arg( $args, 'variation_id' );
		$variation = wc_get_product( $vid );
		if ( ! $variation instanceof \WC_Product_Variation ) {
			throw new Tool_Exception( __( 'Variation not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		self::apply_variation_data( $variation, $args );
		$variation->save();

		return self::format_variation( wc_get_product( $vid ) );
	}

	public static function delete_variation( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'edit_products' );

		$vid       = (int) self::require_arg( $args, 'variation_id' );
		$variation = wc_get_product( $vid );
		if ( ! $variation instanceof \WC_Product_Variation ) {
			throw new Tool_Exception( __( 'Variation not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		$force = (bool) self::arg_bool( $args, 'force', true );
		$ok    = $variation->delete( $force );
		if ( ! $ok ) {
			throw new Tool_Exception( __( 'Could not delete variation', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}
		return [ 'id' => $vid, 'deleted' => true ];
	}

	private static function apply_variation_data( \WC_Product_Variation $variation, array $args ): void {
		$map = [
			'regular_price'  => 'set_regular_price',
			'sale_price'     => 'set_sale_price',
			'sku'            => 'set_sku',
			'stock_quantity' => 'set_stock_quantity',
			'stock_status'   => 'set_stock_status',
			'manage_stock'   => 'set_manage_stock',
			'description'    => 'set_description',
			'status'         => 'set_status',
			'weight'         => 'set_weight',
		];
		foreach ( $map as $k => $method ) {
			if ( array_key_exists( $k, $args ) ) {
				$variation->$method( $args[ $k ] );
			}
		}
		if ( isset( $args['dimensions'] ) && is_array( $args['dimensions'] ) ) {
			if ( array_key_exists( 'length', $args['dimensions'] ) ) $variation->set_length( $args['dimensions']['length'] );
			if ( array_key_exists( 'width', $args['dimensions'] ) )  $variation->set_width( $args['dimensions']['width'] );
			if ( array_key_exists( 'height', $args['dimensions'] ) ) $variation->set_height( $args['dimensions']['height'] );
		}
		if ( isset( $args['attributes'] ) && is_array( $args['attributes'] ) ) {
			$attrs = [];
			foreach ( $args['attributes'] as $a ) {
				if ( empty( $a['name'] ) ) continue;
				$key           = 'attribute_' . sanitize_title( (string) $a['name'] );
				$attrs[ $key ] = sanitize_title( (string) ( $a['option'] ?? '' ) );
			}
			$variation->set_attributes( $attrs );
		}
		if ( isset( $args['image'] ) && is_array( $args['image'] ) ) {
			$attachment_id = 0;
			if ( ! empty( $args['image']['id'] ) ) {
				$attachment_id = (int) $args['image']['id'];
			} elseif ( ! empty( $args['image']['src'] ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				$tmp = download_url( (string) $args['image']['src'], 60 );
				if ( ! is_wp_error( $tmp ) ) {
					$file_array = [ 'name' => wp_basename( wp_parse_url( (string) $args['image']['src'], PHP_URL_PATH ) ?: 'image' ), 'tmp_name' => $tmp ];
					$id         = media_handle_sideload( $file_array, $variation->get_parent_id() );
					if ( ! is_wp_error( $id ) ) {
						$attachment_id = (int) $id;
					} else {
						@unlink( $tmp );
					}
				}
			}
			if ( $attachment_id ) {
				$variation->set_image_id( $attachment_id );
				if ( ! empty( $args['image']['alt'] ) ) {
					update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $args['image']['alt'] ) );
				}
			}
		}
	}

	private static function format_variation( \WC_Product_Variation $v ): array {
		$image_id = $v->get_image_id();
		return [
			'id'             => $v->get_id(),
			'parent_id'      => $v->get_parent_id(),
			'sku'            => $v->get_sku(),
			'price'          => (float) $v->get_price(),
			'regular_price'  => (float) $v->get_regular_price(),
			'sale_price'     => '' !== $v->get_sale_price() ? (float) $v->get_sale_price() : null,
			'on_sale'        => $v->is_on_sale(),
			'status'         => $v->get_status(),
			'description'    => $v->get_description(),
			'manage_stock'   => $v->get_manage_stock(),
			'stock_quantity' => $v->get_stock_quantity(),
			'stock_status'   => $v->get_stock_status(),
			'weight'         => '' !== $v->get_weight() ? (float) $v->get_weight() : null,
			'dimensions'     => [
				'length' => '' !== $v->get_length() ? (float) $v->get_length() : null,
				'width'  => '' !== $v->get_width()  ? (float) $v->get_width()  : null,
				'height' => '' !== $v->get_height() ? (float) $v->get_height() : null,
			],
			'attributes'     => array_map( static fn( $k, $v2 ) => [ 'name' => preg_replace( '/^attribute_/', '', $k ), 'option' => $v2 ], array_keys( $v->get_attributes() ), array_values( $v->get_attributes() ) ),
			'image'          => $image_id ? [
				'id'  => (int) $image_id,
				'src' => wp_get_attachment_url( $image_id ),
				'alt' => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
			] : null,
			'date_created'   => $v->get_date_created() ? $v->get_date_created()->date_i18n( 'c' ) : null,
		];
	}
}

add_action( 'store_mcp_register_tools', [ Variations_Tools::class, 'register' ] );
