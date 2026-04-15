<?php
/**
 * Products tools — list/get (FREE), create/update/delete/bulk (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Products_Tools {
	use Tool_Base;

	const STATUSES = [ 'publish', 'draft', 'pending', 'private', 'trash', 'any' ];
	const TYPES    = [ 'simple', 'variable', 'grouped', 'external' ];
	const STOCK    = [ 'instock', 'outofstock', 'onbackorder' ];

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_products',
			'description' => 'List WooCommerce products with rich filters (category, tag, status, type, price, stock, SKU).',
			'tier'        => 'free',
			'module'      => 'class-products-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'search'       => [ 'type' => 'string' ],
					'category'     => [ 'type' => 'integer', 'description' => 'Product category id.' ],
					'tag'          => [ 'type' => 'integer' ],
					'status'       => [ 'type' => 'string', 'enum' => self::STATUSES, 'default' => 'any' ],
					'type'         => [ 'type' => 'string', 'enum' => self::TYPES ],
					'featured'     => [ 'type' => 'boolean' ],
					'on_sale'      => [ 'type' => 'boolean' ],
					'min_price'    => [ 'type' => 'number' ],
					'max_price'    => [ 'type' => 'number' ],
					'stock_status' => [ 'type' => 'string', 'enum' => self::STOCK ],
					'sku'          => [ 'type' => 'string' ],
					'orderby'      => [ 'type' => 'string', 'enum' => [ 'date', 'title', 'price', 'popularity', 'rating', 'id', 'modified', 'menu_order' ], 'default' => 'date' ],
					'order'        => [ 'type' => 'string', 'enum' => [ 'asc', 'desc' ], 'default' => 'desc' ],
					'page'         => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page'     => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_products' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_product',
			'description' => 'Get full product data including attributes, variations, and meta.',
			'tier'        => 'free',
			'module'      => 'class-products-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_product' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_product',
			'description' => 'Create a new product (PRO). Supports simple, variable, grouped and external types.',
			'tier'        => 'pro',
			'module'      => 'class-products-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'name' ],
				'properties' => self::mutable_product_schema(),
				'additionalProperties' => false,
			],
		], [ self::class, 'create_product' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_product',
			'description' => 'Update an existing product (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-products-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => array_merge( [ 'id' => [ 'type' => 'integer' ] ], self::mutable_product_schema() ),
				'additionalProperties' => false,
			],
		], [ self::class, 'update_product' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_product',
			'description' => 'Delete a product (PRO). force=true skips trash.',
			'tier'        => 'pro',
			'module'      => 'class-products-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'    => [ 'type' => 'integer' ],
					'force' => [ 'type' => 'boolean', 'default' => false ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_product' ] );

		$registry->register( [
			'name'        => 'store_mcp_bulk_update_products',
			'description' => 'Update up to 50 products in one call (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-products-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'updates' ],
				'properties' => [
					'updates' => [
						'type'  => 'array',
						'maxItems' => 50,
						'items' => [
							'type' => 'object',
							'required' => [ 'id' ],
							'properties' => array_merge( [ 'id' => [ 'type' => 'integer' ] ], self::mutable_product_schema() ),
						],
					],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'bulk_update_products' ] );
	}

	private static function mutable_product_schema(): array {
		return [
			'name'                => [ 'type' => 'string' ],
			'type'                => [ 'type' => 'string', 'enum' => self::TYPES ],
			'status'              => [ 'type' => 'string', 'enum' => self::STATUSES ],
			'description'         => [ 'type' => 'string' ],
			'short_description'   => [ 'type' => 'string' ],
			'slug'                => [ 'type' => 'string' ],
			'sku'                 => [ 'type' => 'string' ],
			'regular_price'       => [ 'type' => 'number' ],
			'sale_price'          => [ 'type' => [ 'number', 'null' ] ],
			'sale_price_dates_from' => [ 'type' => 'string', 'description' => 'ISO 8601 or Y-m-d.' ],
			'sale_price_dates_to'   => [ 'type' => 'string' ],
			'manage_stock'        => [ 'type' => 'boolean' ],
			'stock_quantity'      => [ 'type' => [ 'integer', 'null' ] ],
			'stock_status'        => [ 'type' => 'string', 'enum' => self::STOCK ],
			'backorders'          => [ 'type' => 'string', 'enum' => [ 'no', 'notify', 'yes' ] ],
			'weight'              => [ 'type' => [ 'number', 'null' ] ],
			'dimensions'          => [
				'type'       => 'object',
				'properties' => [
					'length' => [ 'type' => [ 'number', 'null' ] ],
					'width'  => [ 'type' => [ 'number', 'null' ] ],
					'height' => [ 'type' => [ 'number', 'null' ] ],
				],
			],
			'categories'          => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
			'tags'                => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
			'images'              => [
				'type'  => 'array',
				'items' => [
					'type' => 'object',
					'properties' => [
						'id'  => [ 'type' => 'integer' ],
						'src' => [ 'type' => 'string' ],
						'alt' => [ 'type' => 'string' ],
					],
				],
			],
			'attributes'          => [
				'type'  => 'array',
				'items' => [
					'type' => 'object',
					'properties' => [
						'name'      => [ 'type' => 'string' ],
						'options'   => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
						'visible'   => [ 'type' => 'boolean', 'default' => true ],
						'variation' => [ 'type' => 'boolean', 'default' => false ],
					],
				],
			],
			'featured'            => [ 'type' => 'boolean' ],
			'catalog_visibility'  => [ 'type' => 'string', 'enum' => [ 'visible', 'catalog', 'search', 'hidden' ] ],
			'tax_status'          => [ 'type' => 'string', 'enum' => [ 'taxable', 'shipping', 'none' ] ],
			'tax_class'           => [ 'type' => 'string' ],
			'upsell_ids'          => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
			'cross_sell_ids'      => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
			'meta_data'           => [
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
	}

	public static function list_products( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();

		$pagination = self::pagination( $args );

		$query_args = [
			'status'   => self::arg_enum( $args, 'status', self::STATUSES, 'any' ),
			'limit'    => $pagination['per_page'],
			'page'     => $pagination['page'],
			'paginate' => true,
			'orderby'  => self::arg_enum( $args, 'orderby', [ 'date', 'title', 'price', 'popularity', 'rating', 'id', 'modified', 'menu_order' ], 'date' ),
			'order'    => strtoupper( (string) self::arg_enum( $args, 'order', [ 'asc', 'desc' ], 'desc' ) ),
		];

		if ( $s = self::arg_string( $args, 'search', '' ) ) {
			$query_args['s'] = $s;
		}
		if ( $type = self::arg_enum( $args, 'type', self::TYPES ) ) {
			$query_args['type'] = $type;
		}
		if ( null !== self::arg_bool( $args, 'featured' ) ) {
			$query_args['featured'] = self::arg_bool( $args, 'featured' );
		}
		if ( $ss = self::arg_enum( $args, 'stock_status', self::STOCK ) ) {
			$query_args['stock_status'] = $ss;
		}
		if ( $sku = self::arg_string( $args, 'sku', '' ) ) {
			$query_args['sku'] = $sku;
		}
		if ( $cat = self::arg_int( $args, 'category' ) ) {
			$query_args['category'] = [ get_term( $cat, 'product_cat' ) ? get_term( $cat, 'product_cat' )->slug : '' ];
		}
		if ( $tag = self::arg_int( $args, 'tag' ) ) {
			$query_args['tag'] = [ get_term( $tag, 'product_tag' ) ? get_term( $tag, 'product_tag' )->slug : '' ];
		}

		$min = self::arg_float( $args, 'min_price' );
		$max = self::arg_float( $args, 'max_price' );
		$on_sale = self::arg_bool( $args, 'on_sale' );

		if ( null !== $min || null !== $max || true === $on_sale ) {
			$meta = [];
			if ( null !== $min ) $meta[] = [ 'key' => '_price', 'value' => $min, 'compare' => '>=', 'type' => 'NUMERIC' ];
			if ( null !== $max ) $meta[] = [ 'key' => '_price', 'value' => $max, 'compare' => '<=', 'type' => 'NUMERIC' ];
			if ( true === $on_sale ) {
				$query_args['include'] = wc_get_product_ids_on_sale();
				if ( empty( $query_args['include'] ) ) {
					return self::paginated( [], 0, $pagination['page'], $pagination['per_page'] );
				}
			}
			if ( $meta ) {
				$query_args['meta_query'] = $meta;
			}
		}

		$result = wc_get_products( $query_args );

		$items = array_map( static fn( $p ) => self::format_product( $p, false ), $result->products );
		return self::paginated( $items, (int) $result->total, $pagination['page'], $pagination['per_page'] );
	}

	public static function get_product( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();

		$id      = (int) self::require_arg( $args, 'id' );
		$product = wc_get_product( $id );
		if ( ! $product ) {
			throw new Tool_Exception( esc_html__( 'Product not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		return self::format_product( $product, true );
	}

	public static function create_product( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'edit_products' );

		$type = (string) self::arg_enum( $args, 'type', self::TYPES, 'simple' );
		$classname = \WC_Product_Factory::get_classname_from_product_type( $type );
		if ( ! $classname || ! class_exists( $classname ) ) {
			$classname = \WC_Product_Simple::class;
		}
		/** @var \WC_Product $product */
		$product = new $classname();

		self::apply_product_data( $product, $args );
		$product->save();

		return self::format_product( wc_get_product( $product->get_id() ), true );
	}

	public static function update_product( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();

		$id = (int) self::require_arg( $args, 'id' );
		Permissions::require_cap( $context, 'edit_product', $id );

		$product = wc_get_product( $id );
		if ( ! $product ) {
			throw new Tool_Exception( esc_html__( 'Product not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		self::apply_product_data( $product, $args );
		$product->save();

		return self::format_product( wc_get_product( $id ), true );
	}

	public static function delete_product( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();

		$id = (int) self::require_arg( $args, 'id' );
		Permissions::require_cap( $context, 'delete_product', $id );

		$product = wc_get_product( $id );
		if ( ! $product ) {
			throw new Tool_Exception( esc_html__( 'Product not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$force = (bool) self::arg_bool( $args, 'force', false );
		$ok    = $product->delete( $force );
		if ( ! $ok ) {
			throw new Tool_Exception( esc_html__( 'Could not delete product', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}
		return [ 'id' => $id, 'deleted' => true, 'force' => $force ];
	}

	public static function bulk_update_products( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'edit_products' );

		$updates = self::arg_array( $args, 'updates', [] );
		if ( count( $updates ) > 50 ) {
			throw new Tool_Exception( esc_html__( 'Bulk update limited to 50 products per call', 'store-mcp' ), Server::RPC_INVALID_PARAMS );
		}

		$results = [];
		foreach ( $updates as $update ) {
			if ( empty( $update['id'] ) ) {
				$results[] = [ 'id' => 0, 'success' => false, 'error' => 'missing id' ];
				continue;
			}
			$product = wc_get_product( (int) $update['id'] );
			if ( ! $product ) {
				$results[] = [ 'id' => (int) $update['id'], 'success' => false, 'error' => 'not found' ];
				continue;
			}
			try {
				self::apply_product_data( $product, $update );
				$product->save();
				$results[] = [ 'id' => $product->get_id(), 'success' => true ];
			} catch ( \Throwable $e ) {
				$results[] = [ 'id' => (int) $update['id'], 'success' => false, 'error' => $e->getMessage() ];
			}
		}

		return [ 'processed' => count( $results ), 'results' => $results ];
	}

	private static function apply_product_data( \WC_Product $product, array $args ): void {
		$setters = [
			'name'                => 'set_name',
			'slug'                => 'set_slug',
			'status'              => 'set_status',
			'description'         => 'set_description',
			'short_description'   => 'set_short_description',
			'sku'                 => 'set_sku',
			'regular_price'       => 'set_regular_price',
			'sale_price'          => 'set_sale_price',
			'manage_stock'        => 'set_manage_stock',
			'stock_quantity'      => 'set_stock_quantity',
			'stock_status'        => 'set_stock_status',
			'backorders'          => 'set_backorders',
			'weight'              => 'set_weight',
			'featured'             => 'set_featured',
			'catalog_visibility'  => 'set_catalog_visibility',
			'tax_status'          => 'set_tax_status',
			'tax_class'           => 'set_tax_class',
			'upsell_ids'          => 'set_upsell_ids',
			'cross_sell_ids'      => 'set_cross_sell_ids',
		];

		foreach ( $setters as $key => $method ) {
			if ( array_key_exists( $key, $args ) && method_exists( $product, $method ) ) {
				$product->$method( $args[ $key ] );
			}
		}

		if ( isset( $args['dimensions'] ) && is_array( $args['dimensions'] ) ) {
			if ( array_key_exists( 'length', $args['dimensions'] ) ) $product->set_length( $args['dimensions']['length'] );
			if ( array_key_exists( 'width', $args['dimensions'] ) )  $product->set_width( $args['dimensions']['width'] );
			if ( array_key_exists( 'height', $args['dimensions'] ) ) $product->set_height( $args['dimensions']['height'] );
		}

		foreach ( [ 'sale_price_dates_from', 'sale_price_dates_to' ] as $date_field ) {
			if ( isset( $args[ $date_field ] ) && '' !== $args[ $date_field ] ) {
				$ts = strtotime( (string) $args[ $date_field ] );
				if ( $ts ) {
					$setter = 'set_date_on_sale_' . ( 'sale_price_dates_from' === $date_field ? 'from' : 'to' );
					$product->$setter( $ts );
				}
			}
		}

		if ( array_key_exists( 'categories', $args ) ) {
			$product->set_category_ids( self::ensure_int_list( $args['categories'] ) );
		}
		if ( array_key_exists( 'tags', $args ) ) {
			$product->set_tag_ids( self::ensure_int_list( $args['tags'] ) );
		}
		if ( array_key_exists( 'images', $args ) && is_array( $args['images'] ) ) {
			self::apply_images( $product, $args['images'] );
		}
		if ( array_key_exists( 'attributes', $args ) && is_array( $args['attributes'] ) ) {
			self::apply_attributes( $product, $args['attributes'] );
		}
		if ( array_key_exists( 'meta_data', $args ) && is_array( $args['meta_data'] ) ) {
			foreach ( $args['meta_data'] as $meta ) {
				if ( isset( $meta['key'] ) ) {
					$product->update_meta_data( sanitize_key( (string) $meta['key'] ), $meta['value'] ?? '' );
				}
			}
		}
	}

	private static function apply_images( \WC_Product $product, array $images ): void {
		$ids  = [];
		foreach ( $images as $image ) {
			$id = 0;
			if ( ! empty( $image['id'] ) ) {
				$id = (int) $image['id'];
			} elseif ( ! empty( $image['src'] ) ) {
				$id = self::sideload_image( (string) $image['src'], $product->get_id() );
			}
			if ( $id > 0 ) {
				if ( ! empty( $image['alt'] ) ) {
					update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) $image['alt'] ) );
				}
				$ids[] = $id;
			}
		}
		if ( $ids ) {
			$product->set_image_id( array_shift( $ids ) );
			$product->set_gallery_image_ids( $ids );
		}
	}

	private static function sideload_image( string $url, int $parent ): int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			return 0;
		}
		$file_array = [ 'name' => wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ?: 'image' ), 'tmp_name' => $tmp ];
		$id         = media_handle_sideload( $file_array, $parent );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return 0;
		}
		return (int) $id;
	}

	private static function apply_attributes( \WC_Product $product, array $attributes ): void {
		$product_attributes = [];
		$position = 0;
		foreach ( $attributes as $data ) {
			$name      = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
			$options   = array_map( 'sanitize_text_field', (array) ( $data['options'] ?? [] ) );
			$visible   = ! isset( $data['visible'] ) || (bool) $data['visible'];
			$variation = ! empty( $data['variation'] );
			if ( '' === $name ) continue;

			$attr = new \WC_Product_Attribute();
			$attr->set_name( $name );
			$attr->set_options( $options );
			$attr->set_visible( $visible );
			$attr->set_variation( $variation );
			$attr->set_position( $position++ );

			$taxonomy = wc_attribute_taxonomy_name( $name );
			if ( taxonomy_exists( $taxonomy ) ) {
				$attr->set_id( wc_attribute_taxonomy_id_by_name( $name ) );
				$attr->set_name( $taxonomy );
				$term_ids = [];
				foreach ( $options as $term_name ) {
					$term = get_term_by( 'name', $term_name, $taxonomy );
					if ( ! $term ) {
						$term = wp_insert_term( $term_name, $taxonomy );
						if ( ! is_wp_error( $term ) ) {
							$term_ids[] = (int) $term['term_id'];
						}
					} else {
						$term_ids[] = (int) $term->term_id;
					}
				}
				$attr->set_options( $term_ids );
			}

			$product_attributes[] = $attr;
		}
		$product->set_attributes( $product_attributes );
	}

	private static function ensure_int_list( $value ): array {
		return array_values( array_filter( array_map( 'intval', (array) $value ) ) );
	}
}

add_action( 'store_mcp_register_tools', [ Products_Tools::class, 'register' ] );
