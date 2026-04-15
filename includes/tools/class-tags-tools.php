<?php
/**
 * Product tags tools — list (FREE), create/update/delete (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Tags_Tools {
	use Tool_Base;

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_product_tags',
			'description' => 'List product tags.',
			'tier'        => 'free',
			'module'      => 'class-tags-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'search'   => [ 'type' => 'string' ],
					'orderby'  => [ 'type' => 'string', 'enum' => [ 'name', 'slug', 'count', 'id' ], 'default' => 'name' ],
					'order'    => [ 'type' => 'string', 'enum' => [ 'asc', 'desc' ], 'default' => 'asc' ],
					'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_product_tags' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_product_tag',
			'description' => 'Create a product tag (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-tags-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'name' ],
				'properties' => [
					'name'        => [ 'type' => 'string' ],
					'slug'        => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_product_tag' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_product_tag',
			'description' => 'Update a product tag (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-tags-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'          => [ 'type' => 'integer' ],
					'name'        => [ 'type' => 'string' ],
					'slug'        => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_product_tag' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_product_tag',
			'description' => 'Delete a product tag (PRO).',
			'tier'        => 'pro',
			'module'      => 'class-tags-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'    => [ 'type' => 'integer' ],
					'force' => [ 'type' => 'boolean', 'default' => true ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_product_tag' ] );
	}

	public static function list_product_tags( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();

		$pagination = self::pagination( $args, 50 );
		$query_args = [
			'taxonomy'   => 'product_tag',
			'hide_empty' => false,
			'orderby'    => self::arg_enum( $args, 'orderby', [ 'name', 'slug', 'count', 'id' ], 'name' ),
			'order'      => strtoupper( (string) self::arg_enum( $args, 'order', [ 'asc', 'desc' ], 'asc' ) ),
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

	public static function create_product_tag( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_product_terms' );

		$opts = [];
		if ( ! empty( $args['slug'] ) )        $opts['slug']        = sanitize_title( (string) $args['slug'] );
		if ( ! empty( $args['description'] ) ) $opts['description'] = wp_kses_post( (string) $args['description'] );

		$result = wp_insert_term( (string) self::require_arg( $args, 'name' ), 'product_tag', $opts );
		if ( is_wp_error( $result ) ) {
			throw new Tool_Exception( esc_html( $result->get_error_message() ), Server::ERR_TOOL_FAILED );
		}
		return self::format_term( get_term( (int) $result['term_id'], 'product_tag' ) );
	}

	public static function update_product_tag( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_product_terms' );

		$id = (int) self::require_arg( $args, 'id' );
		$update = [];
		if ( array_key_exists( 'name', $args ) )        $update['name']        = sanitize_text_field( (string) $args['name'] );
		if ( array_key_exists( 'slug', $args ) )        $update['slug']        = sanitize_title( (string) $args['slug'] );
		if ( array_key_exists( 'description', $args ) ) $update['description'] = wp_kses_post( (string) $args['description'] );

		if ( $update ) {
			$result = wp_update_term( $id, 'product_tag', $update );
			if ( is_wp_error( $result ) ) {
				throw new Tool_Exception( esc_html( $result->get_error_message() ), Server::ERR_TOOL_FAILED );
			}
		}
		return self::format_term( get_term( $id, 'product_tag' ) );
	}

	public static function delete_product_tag( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'manage_product_terms' );

		$id = (int) self::require_arg( $args, 'id' );
		$ok = wp_delete_term( $id, 'product_tag' );
		if ( ! $ok || is_wp_error( $ok ) ) {
			$msg = is_wp_error( $ok ) ? $ok->get_error_message() : __( 'Could not delete tag', 'store-mcp' );
			throw new Tool_Exception( esc_html( $msg ), Server::ERR_TOOL_FAILED );
		}
		return [ 'id' => $id, 'deleted' => true ];
	}
}

add_action( 'store_mcp_register_tools', [ Tags_Tools::class, 'register' ] );
