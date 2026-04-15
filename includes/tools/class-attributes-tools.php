<?php
/**
 * Product attributes tools — global attributes and their terms (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Attributes_Tools {
	use Tool_Base;

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_attributes',
			'description' => 'List global product attributes.',
			'tier'        => 'pro',
			'module'      => 'class-attributes-tools',
			'inputSchema' => [ 'type' => 'object', 'properties' => (object) [], 'additionalProperties' => false ],
		], [ self::class, 'list_attributes' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_attribute',
			'description' => 'Create a global product attribute.',
			'tier'        => 'pro',
			'module'      => 'class-attributes-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'name' ],
				'properties' => [
					'name'         => [ 'type' => 'string' ],
					'slug'         => [ 'type' => 'string' ],
					'type'         => [ 'type' => 'string', 'enum' => [ 'select' ], 'default' => 'select' ],
					'order_by'     => [ 'type' => 'string', 'enum' => [ 'menu_order', 'name', 'name_num', 'id' ], 'default' => 'menu_order' ],
					'has_archives' => [ 'type' => 'boolean', 'default' => false ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_attribute' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_attribute',
			'description' => 'Update a global product attribute.',
			'tier'        => 'pro',
			'module'      => 'class-attributes-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'           => [ 'type' => 'integer' ],
					'name'         => [ 'type' => 'string' ],
					'slug'         => [ 'type' => 'string' ],
					'order_by'     => [ 'type' => 'string', 'enum' => [ 'menu_order', 'name', 'name_num', 'id' ] ],
					'has_archives' => [ 'type' => 'boolean' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_attribute' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_attribute',
			'description' => 'Delete a global product attribute and all its terms.',
			'tier'        => 'pro',
			'module'      => 'class-attributes-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_attribute' ] );

		$registry->register( [
			'name'        => 'store_mcp_list_attribute_terms',
			'description' => 'List terms of a global attribute.',
			'tier'        => 'pro',
			'module'      => 'class-attributes-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'attribute_id' ],
				'properties' => [
					'attribute_id' => [ 'type' => 'integer' ],
					'search'       => [ 'type' => 'string' ],
					'page'         => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page'     => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 100 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_attribute_terms' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_attribute_term',
			'description' => 'Create a term under a global attribute.',
			'tier'        => 'pro',
			'module'      => 'class-attributes-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'attribute_id', 'name' ],
				'properties' => [
					'attribute_id' => [ 'type' => 'integer' ],
					'name'         => [ 'type' => 'string' ],
					'slug'         => [ 'type' => 'string' ],
					'description'  => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_attribute_term' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_attribute_term',
			'description' => 'Update a term of a global attribute.',
			'tier'        => 'pro',
			'module'      => 'class-attributes-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'attribute_id', 'term_id' ],
				'properties' => [
					'attribute_id' => [ 'type' => 'integer' ],
					'term_id'      => [ 'type' => 'integer' ],
					'name'         => [ 'type' => 'string' ],
					'slug'         => [ 'type' => 'string' ],
					'description'  => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_attribute_term' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_attribute_term',
			'description' => 'Delete an attribute term.',
			'tier'        => 'pro',
			'module'      => 'class-attributes-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'attribute_id', 'term_id' ],
				'properties' => [
					'attribute_id' => [ 'type' => 'integer' ],
					'term_id'      => [ 'type' => 'integer' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_attribute_term' ] );
	}

	public static function list_attributes( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_product_terms' );

		$attrs = wc_get_attribute_taxonomies();
		$items = array_map( [ self::class, 'format_attribute' ], $attrs );
		return [ 'items' => array_values( $items ), 'count' => count( $items ) ];
	}

	public static function create_attribute( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_product_terms' );

		$name = sanitize_text_field( (string) self::require_arg( $args, 'name' ) );
		$slug = self::arg_string( $args, 'slug', '' ) ?: wc_sanitize_taxonomy_name( $name );

		$id = wc_create_attribute( [
			'name'         => $name,
			'slug'         => $slug,
			'type'         => self::arg_string( $args, 'type', 'select' ),
			'order_by'     => self::arg_string( $args, 'order_by', 'menu_order' ),
			'has_archives' => (bool) self::arg_bool( $args, 'has_archives', false ),
		] );
		if ( is_wp_error( $id ) ) {
			throw new Tool_Exception( esc_html( $id->get_error_message() ), Server::ERR_TOOL_FAILED );
		}
		return self::get_by_id( (int) $id );
	}

	public static function update_attribute( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_product_terms' );

		$id     = (int) self::require_arg( $args, 'id' );
		$update = [];
		if ( array_key_exists( 'name', $args ) )         $update['name']         = sanitize_text_field( (string) $args['name'] );
		if ( array_key_exists( 'slug', $args ) )         $update['slug']         = sanitize_title( (string) $args['slug'] );
		if ( array_key_exists( 'order_by', $args ) )     $update['order_by']     = (string) $args['order_by'];
		if ( array_key_exists( 'has_archives', $args ) ) $update['has_archives'] = (bool) $args['has_archives'];

		$result = wc_update_attribute( $id, $update );
		if ( is_wp_error( $result ) ) {
			throw new Tool_Exception( esc_html( $result->get_error_message() ), Server::ERR_TOOL_FAILED );
		}
		return self::get_by_id( $id );
	}

	public static function delete_attribute( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_product_terms' );

		$id = (int) self::require_arg( $args, 'id' );
		$ok = wc_delete_attribute( $id );
		if ( ! $ok ) {
			throw new Tool_Exception( esc_html__( 'Could not delete attribute', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}
		return [ 'id' => $id, 'deleted' => true ];
	}

	public static function list_attribute_terms( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_product_terms' );

		$attr_id  = (int) self::require_arg( $args, 'attribute_id' );
		$taxonomy = self::get_taxonomy_from_id( $attr_id );

		$pagination = self::pagination( $args, 100, 200 );
		$query_args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'search'     => self::arg_string( $args, 'search', '' ),
			'number'     => $pagination['per_page'],
			'offset'     => ( $pagination['page'] - 1 ) * $pagination['per_page'],
		];

		$terms = get_terms( $query_args );
		if ( is_wp_error( $terms ) ) {
			throw new Tool_Exception( esc_html( $terms->get_error_message() ), Server::ERR_TOOL_FAILED );
		}
		$total = (int) wp_count_terms( array_merge( $query_args, [ 'fields' => 'count' ] ) );
		$items = array_map( [ self::class, 'format_term' ], $terms );
		return self::paginated( $items, $total, $pagination['page'], $pagination['per_page'] );
	}

	public static function create_attribute_term( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_product_terms' );

		$attr_id  = (int) self::require_arg( $args, 'attribute_id' );
		$taxonomy = self::get_taxonomy_from_id( $attr_id );
		$opts     = [];
		if ( ! empty( $args['slug'] ) )        $opts['slug']        = sanitize_title( (string) $args['slug'] );
		if ( ! empty( $args['description'] ) ) $opts['description'] = wp_kses_post( (string) $args['description'] );

		$res = wp_insert_term( (string) self::require_arg( $args, 'name' ), $taxonomy, $opts );
		if ( is_wp_error( $res ) ) {
			throw new Tool_Exception( esc_html( $res->get_error_message() ), Server::ERR_TOOL_FAILED );
		}
		return self::format_term( get_term( (int) $res['term_id'], $taxonomy ) );
	}

	public static function update_attribute_term( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_product_terms' );

		$attr_id  = (int) self::require_arg( $args, 'attribute_id' );
		$term_id  = (int) self::require_arg( $args, 'term_id' );
		$taxonomy = self::get_taxonomy_from_id( $attr_id );

		$update = [];
		if ( array_key_exists( 'name', $args ) )        $update['name']        = sanitize_text_field( (string) $args['name'] );
		if ( array_key_exists( 'slug', $args ) )        $update['slug']        = sanitize_title( (string) $args['slug'] );
		if ( array_key_exists( 'description', $args ) ) $update['description'] = wp_kses_post( (string) $args['description'] );

		if ( $update ) {
			$res = wp_update_term( $term_id, $taxonomy, $update );
			if ( is_wp_error( $res ) ) {
				throw new Tool_Exception( esc_html( $res->get_error_message() ), Server::ERR_TOOL_FAILED );
			}
		}
		return self::format_term( get_term( $term_id, $taxonomy ) );
	}

	public static function delete_attribute_term( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_product_terms' );

		$attr_id  = (int) self::require_arg( $args, 'attribute_id' );
		$term_id  = (int) self::require_arg( $args, 'term_id' );
		$taxonomy = self::get_taxonomy_from_id( $attr_id );

		$ok = wp_delete_term( $term_id, $taxonomy );
		if ( ! $ok || is_wp_error( $ok ) ) {
			$msg = is_wp_error( $ok ) ? $ok->get_error_message() : __( 'Could not delete term', 'store-mcp' );
			throw new Tool_Exception( esc_html( $msg ), Server::ERR_TOOL_FAILED );
		}
		return [ 'attribute_id' => $attr_id, 'term_id' => $term_id, 'deleted' => true ];
	}

	private static function get_by_id( int $id ): array {
		$all = wc_get_attribute_taxonomies();
		foreach ( $all as $a ) {
			if ( (int) $a->attribute_id === $id ) {
				return self::format_attribute( $a );
			}
		}
		throw new Tool_Exception( esc_html__( 'Attribute not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
	}

	private static function get_taxonomy_from_id( int $id ): string {
		$slug = wc_attribute_taxonomy_name_by_id( $id );
		if ( ! $slug ) {
			throw new Tool_Exception( esc_html__( 'Attribute not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		return $slug;
	}

	private static function format_attribute( $attr ): array {
		return [
			'id'           => (int) $attr->attribute_id,
			'name'         => $attr->attribute_label,
			'slug'         => wc_attribute_taxonomy_name( $attr->attribute_name ),
			'type'         => $attr->attribute_type,
			'order_by'     => $attr->attribute_orderby,
			'has_archives' => (bool) $attr->attribute_public,
		];
	}
}

add_action( 'store_mcp_register_tools', [ Attributes_Tools::class, 'register' ] );
