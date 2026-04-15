<?php
/**
 * Pages tools — full CRUD on WordPress pages (FREE).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Pages_Tools {
	use Tool_Base;

	const STATUSES = [ 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'any' ];

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_pages',
			'description' => 'List WordPress pages with filters and pagination.',
			'tier'        => 'free',
			'module'      => 'class-pages-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'search'   => [ 'type' => 'string', 'description' => 'Search query against title and content.' ],
					'status'   => [ 'type' => 'string', 'enum' => self::STATUSES, 'default' => 'any' ],
					'parent'   => [ 'type' => 'integer', 'description' => 'Filter by parent page id (0 = top level).' ],
					'author'   => [ 'type' => 'integer' ],
					'orderby'  => [ 'type' => 'string', 'enum' => [ 'date', 'modified', 'title', 'menu_order', 'id' ], 'default' => 'date' ],
					'order'    => [ 'type' => 'string', 'enum' => [ 'asc', 'desc' ], 'default' => 'desc' ],
					'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_pages' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_page',
			'description' => 'Get a single WordPress page by id, including content.',
			'tier'        => 'free',
			'module'      => 'class-pages-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id' => [ 'type' => 'integer', 'description' => 'Page id.' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_page' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_page',
			'description' => 'Create a new WordPress page.',
			'tier'        => 'free',
			'module'      => 'class-pages-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'title' ],
				'properties' => [
					'title'          => [ 'type' => 'string' ],
					'content'        => [ 'type' => 'string', 'description' => 'HTML or Gutenberg blocks.' ],
					'slug'           => [ 'type' => 'string' ],
					'status'         => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending', 'private', 'future' ], 'default' => 'draft' ],
					'template'       => [ 'type' => 'string' ],
					'parent'         => [ 'type' => 'integer' ],
					'menu_order'     => [ 'type' => 'integer' ],
					'featured_media' => [ 'type' => 'integer' ],
					'excerpt'        => [ 'type' => 'string' ],
					'date'           => [ 'type' => 'string', 'description' => 'ISO 8601 publish date (used when status=future).' ],
					'meta'           => [ 'type' => 'object', 'additionalProperties' => true, 'description' => 'Map of meta_key => value.' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_page' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_page',
			'description' => 'Update an existing WordPress page.',
			'tier'        => 'free',
			'module'      => 'class-pages-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'             => [ 'type' => 'integer' ],
					'title'          => [ 'type' => 'string' ],
					'content'        => [ 'type' => 'string' ],
					'slug'           => [ 'type' => 'string' ],
					'status'         => [ 'type' => 'string', 'enum' => self::STATUSES ],
					'template'       => [ 'type' => 'string' ],
					'parent'         => [ 'type' => 'integer' ],
					'menu_order'     => [ 'type' => 'integer' ],
					'featured_media' => [ 'type' => 'integer' ],
					'excerpt'        => [ 'type' => 'string' ],
					'meta'           => [ 'type' => 'object', 'additionalProperties' => true ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_page' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_page',
			'description' => 'Delete a page. By default sends to trash; pass force=true to permanently delete.',
			'tier'        => 'free',
			'module'      => 'class-pages-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'    => [ 'type' => 'integer' ],
					'force' => [ 'type' => 'boolean', 'default' => false ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_page' ] );
	}

	public static function list_pages( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'read' );

		$pagination = self::pagination( $args );

		$query_args = [
			'post_type'      => 'page',
			'post_status'    => self::arg_enum( $args, 'status', self::STATUSES, 'any' ),
			'posts_per_page' => $pagination['per_page'],
			'paged'          => $pagination['page'],
			'orderby'        => self::arg_enum( $args, 'orderby', [ 'date', 'modified', 'title', 'menu_order', 'id' ], 'date' ),
			'order'          => strtoupper( (string) self::arg_enum( $args, 'order', [ 'asc', 'desc' ], 'desc' ) ),
		];

		$search = self::arg_string( $args, 'search', '' );
		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}
		$parent = self::arg_int( $args, 'parent' );
		if ( null !== $parent ) {
			$query_args['post_parent'] = $parent;
		}
		$author = self::arg_int( $args, 'author' );
		if ( null !== $author ) {
			$query_args['author'] = $author;
		}

		$query = new \WP_Query( $query_args );
		$items = array_map( static fn( \WP_Post $p ) => self::format_post( $p, false ), $query->posts );

		return self::paginated( $items, (int) $query->found_posts, $pagination['page'], $pagination['per_page'] );
	}

	public static function get_page( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'read' );

		$id   = (int) self::require_arg( $args, 'id' );
		$post = get_post( $id );
		if ( ! $post || 'page' !== $post->post_type ) {
			throw new Tool_Exception( __( 'Page not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$data = self::format_post( $post, true );
		$data['meta'] = self::collect_public_meta( $id );
		return $data;
	}

	public static function create_page( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_pages' );

		$status = (string) self::arg_enum( $args, 'status', [ 'publish', 'draft', 'pending', 'private', 'future' ], 'draft' );
		if ( 'publish' === $status ) {
			Permissions::require_cap( $context, 'publish_pages' );
		}

		$post_data = [
			'post_type'      => 'page',
			'post_title'     => self::arg_string( $args, 'title' ),
			'post_content'   => self::arg_html( $args, 'content', '' ),
			'post_excerpt'   => self::arg_textarea( $args, 'excerpt', '' ),
			'post_status'    => $status,
			'post_parent'    => (int) self::arg_int( $args, 'parent', 0 ),
			'menu_order'     => (int) self::arg_int( $args, 'menu_order', 0 ),
			'post_author'    => $context->user_id,
		];

		$slug = self::arg_string( $args, 'slug', '' );
		if ( '' !== $slug ) {
			$post_data['post_name'] = sanitize_title( $slug );
		}
		$date = self::arg_string( $args, 'date', '' );
		if ( '' !== $date ) {
			$ts = strtotime( $date );
			if ( $ts ) {
				$post_data['post_date']     = gmdate( 'Y-m-d H:i:s', $ts );
				$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}

		$id = wp_insert_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $id ) ) {
			throw new Tool_Exception( $id->get_error_message(), Server::ERR_TOOL_FAILED );
		}

		self::apply_optional_meta( $id, $args );

		return self::format_post( get_post( $id ), true );
	}

	public static function update_page( array $args, AuthContext $context ): array {
		$id = (int) self::require_arg( $args, 'id' );
		Permissions::require_cap( $context, 'edit_page', $id );

		$post = get_post( $id );
		if ( ! $post || 'page' !== $post->post_type ) {
			throw new Tool_Exception( __( 'Page not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$update = [ 'ID' => $id ];
		if ( array_key_exists( 'title', $args ) )         $update['post_title']   = self::arg_string( $args, 'title' );
		if ( array_key_exists( 'content', $args ) )       $update['post_content'] = self::arg_html( $args, 'content' );
		if ( array_key_exists( 'excerpt', $args ) )       $update['post_excerpt'] = self::arg_textarea( $args, 'excerpt' );
		if ( array_key_exists( 'parent', $args ) )        $update['post_parent']  = (int) self::arg_int( $args, 'parent', 0 );
		if ( array_key_exists( 'menu_order', $args ) )    $update['menu_order']   = (int) self::arg_int( $args, 'menu_order', 0 );
		if ( array_key_exists( 'slug', $args ) )          $update['post_name']    = sanitize_title( self::arg_string( $args, 'slug' ) );
		if ( array_key_exists( 'status', $args ) ) {
			$update['post_status'] = (string) self::arg_enum( $args, 'status', self::STATUSES );
		}

		$result = wp_update_post( wp_slash( $update ), true );
		if ( is_wp_error( $result ) ) {
			throw new Tool_Exception( $result->get_error_message(), Server::ERR_TOOL_FAILED );
		}

		self::apply_optional_meta( $id, $args );

		return self::format_post( get_post( $id ), true );
	}

	public static function delete_page( array $args, AuthContext $context ): array {
		$id = (int) self::require_arg( $args, 'id' );
		Permissions::require_cap( $context, 'delete_page', $id );

		$force  = (bool) self::arg_bool( $args, 'force', false );
		$result = wp_delete_post( $id, $force );

		if ( ! $result ) {
			throw new Tool_Exception( __( 'Could not delete page', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}

		return [ 'id' => $id, 'deleted' => true, 'force' => $force ];
	}

	private static function apply_optional_meta( int $post_id, array $args ): void {
		if ( isset( $args['template'] ) ) {
			update_post_meta( $post_id, '_wp_page_template', sanitize_text_field( (string) $args['template'] ) );
		}
		if ( isset( $args['featured_media'] ) ) {
			$thumb = (int) $args['featured_media'];
			if ( $thumb > 0 ) {
				set_post_thumbnail( $post_id, $thumb );
			} else {
				delete_post_thumbnail( $post_id );
			}
		}
		if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
			foreach ( $args['meta'] as $key => $value ) {
				$key = sanitize_key( (string) $key );
				if ( '' === $key || str_starts_with( $key, '_' ) ) {
					continue;
				}
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	private static function collect_public_meta( int $post_id ): array {
		$out = [];
		foreach ( (array) get_post_meta( $post_id ) as $key => $values ) {
			if ( str_starts_with( (string) $key, '_' ) ) {
				continue;
			}
			$out[ $key ] = count( $values ) === 1 ? maybe_unserialize( $values[0] ) : array_map( 'maybe_unserialize', $values );
		}
		return $out;
	}
}

add_action( 'store_mcp_register_tools', [ Pages_Tools::class, 'register' ] );
