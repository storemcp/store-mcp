<?php
/**
 * Product categories tools — list/get (FREE), create/update/delete (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Categories_Tools {
	use Tool_Base;

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_product_categories',
			'description' => 'List WooCommerce product categories.',
			'tier'        => 'free',
			'module'      => 'class-categories-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'search'     => [ 'type' => 'string' ],
					'parent'     => [ 'type' => 'integer' ],
					'hide_empty' => [ 'type' => 'boolean', 'default' => false ],
					'orderby'    => [ 'type' => 'string', 'enum' => [ 'name', 'slug', 'count', 'id' ], 'default' => 'name' ],
					'order'      => [ 'type' => 'string', 'enum' => [ 'asc', 'desc' ], 'default' => 'asc' ],
					'page'       => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page'   => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_product_categories' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_product_category',
			'description' => 'Get a single product category.',
			'tier'        => 'free',
			'module'      => 'class-categories-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_product_category' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_product_category',
			'description' => 'Create a product category (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-categories-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'name' ],
				'properties' => [
					'name'        => [ 'type' => 'string' ],
					'slug'        => [ 'type' => 'string' ],
					'parent'      => [ 'type' => 'integer' ],
					'description' => [ 'type' => 'string' ],
					'image'       => [
						'type' => 'object',
						'properties' => [
							'id'  => [ 'type' => 'integer' ],
							'src' => [ 'type' => 'string' ],
							'alt' => [ 'type' => 'string' ],
						],
					],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_product_category' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_product_category',
			'description' => 'Update a product category (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-categories-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'          => [ 'type' => 'integer' ],
					'name'        => [ 'type' => 'string' ],
					'slug'        => [ 'type' => 'string' ],
					'parent'      => [ 'type' => 'integer' ],
					'description' => [ 'type' => 'string' ],
					'image'       => [
						'type' => 'object',
						'properties' => [
							'id'  => [ 'type' => 'integer' ],
							'src' => [ 'type' => 'string' ],
							'alt' => [ 'type' => 'string' ],
						],
					],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_product_category' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_product_category',
			'description' => 'Delete a product category (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-categories-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'    => [ 'type' => 'integer' ],
					'force' => [ 'type' => 'boolean', 'default' => true ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_product_category' ] );
	}

	public static function list_product_categories( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();

		$pagination = self::pagination( $args, 50 );
		$query_args = [
			'taxonomy'   => 'product_cat',
			'hide_empty' => (bool) self::arg_bool( $args, 'hide_empty', false ),
			'orderby'    => self::arg_enum( $args, 'orderby', [ 'name', 'slug', 'count', 'id' ], 'name' ),
			'order'      => strtoupper( (string) self::arg_enum( $args, 'order', [ 'asc', 'desc' ], 'asc' ) ),
			'search'     => self::arg_string( $args, 'search', '' ),
			'number'     => $pagination['per_page'],
			'offset'     => ( $pagination['page'] - 1 ) * $pagination['per_page'],
		];
		$parent = self::arg_int( $args, 'parent' );
		if ( null !== $parent ) {
			$query_args['parent'] = $parent;
		}

		$terms = get_terms( $query_args );
		if ( is_wp_error( $terms ) ) {
			throw new Tool_Exception( esc_html( $terms->get_error_message() ), Server::ERR_TOOL_FAILED );
		}
		$total = (int) wp_count_terms( array_merge( $query_args, [ 'fields' => 'count' ] ) );

		$items = array_map( [ self::class, 'format_category' ], $terms );
		return self::paginated( $items, $total, $pagination['page'], $pagination['per_page'] );
	}

	public static function get_product_category( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();

		$id   = (int) self::require_arg( $args, 'id' );
		$term = get_term( $id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			throw new Tool_Exception( esc_html__( 'Category not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		return self::format_category( $term );
	}

	public static function create_product_category( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_product_terms' );

		$opts = [];
		if ( ! empty( $args['slug'] ) )        $opts['slug']        = sanitize_title( (string) $args['slug'] );
		if ( ! empty( $args['parent'] ) )      $opts['parent']      = (int) $args['parent'];
		if ( ! empty( $args['description'] ) ) $opts['description'] = wp_kses_post( (string) $args['description'] );

		$result = wp_insert_term( (string) self::require_arg( $args, 'name' ), 'product_cat', $opts );
		if ( is_wp_error( $result ) ) {
			throw new Tool_Exception( esc_html( $result->get_error_message() ), Server::ERR_TOOL_FAILED );
		}

		if ( ! empty( $args['image'] ) ) {
			self::apply_category_image( (int) $result['term_id'], (array) $args['image'] );
		}

		return self::format_category( get_term( (int) $result['term_id'], 'product_cat' ) );
	}

	public static function update_product_category( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_product_terms' );

		$id = (int) self::require_arg( $args, 'id' );
		$update = [];
		if ( array_key_exists( 'name', $args ) )        $update['name']        = sanitize_text_field( (string) $args['name'] );
		if ( array_key_exists( 'slug', $args ) )        $update['slug']        = sanitize_title( (string) $args['slug'] );
		if ( array_key_exists( 'parent', $args ) )      $update['parent']      = (int) $args['parent'];
		if ( array_key_exists( 'description', $args ) ) $update['description'] = wp_kses_post( (string) $args['description'] );

		if ( $update ) {
			$result = wp_update_term( $id, 'product_cat', $update );
			if ( is_wp_error( $result ) ) {
				throw new Tool_Exception( esc_html( $result->get_error_message() ), Server::ERR_TOOL_FAILED );
			}
		}

		if ( array_key_exists( 'image', $args ) && is_array( $args['image'] ) ) {
			self::apply_category_image( $id, $args['image'] );
		}

		return self::format_category( get_term( $id, 'product_cat' ) );
	}

	public static function delete_product_category( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_product_terms' );

		$id = (int) self::require_arg( $args, 'id' );
		$ok = wp_delete_term( $id, 'product_cat' );
		if ( ! $ok || is_wp_error( $ok ) ) {
			$msg = is_wp_error( $ok ) ? $ok->get_error_message() : __( 'Could not delete category', 'store-mcp' );
			throw new Tool_Exception( esc_html( $msg ), Server::ERR_TOOL_FAILED );
		}
		return [ 'id' => $id, 'deleted' => true ];
	}

	private static function apply_category_image( int $term_id, array $image ): void {
		$attachment_id = 0;
		if ( ! empty( $image['id'] ) ) {
			$attachment_id = (int) $image['id'];
		} elseif ( ! empty( $image['src'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$tmp = download_url( (string) $image['src'], 60 );
			if ( ! is_wp_error( $tmp ) ) {
				$file_array = [ 'name' => wp_basename( wp_parse_url( (string) $image['src'], PHP_URL_PATH ) ?: 'image' ), 'tmp_name' => $tmp ];
				$id         = media_handle_sideload( $file_array, 0 );
				if ( ! is_wp_error( $id ) ) {
					$attachment_id = (int) $id;
				} else {
					wp_delete_file( $tmp );
				}
			}
		}
		if ( $attachment_id ) {
			update_term_meta( $term_id, 'thumbnail_id', $attachment_id );
			if ( ! empty( $image['alt'] ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $image['alt'] ) );
			}
		}
	}

	public static function format_category( \WP_Term $term ): array {
		$thumb_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		return [
			'id'          => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'parent'      => (int) $term->parent,
			'description' => $term->description,
			'count'       => (int) $term->count,
			'image'       => $thumb_id ? [
				'id'  => $thumb_id,
				'src' => wp_get_attachment_url( $thumb_id ),
				'alt' => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
			] : null,
		];
	}
}

add_action( 'store_mcp_register_tools', [ Categories_Tools::class, 'register' ] );
