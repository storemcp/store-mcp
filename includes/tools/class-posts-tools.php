<?php
/**
 * Posts tools — posts, categories and tags (all FREE).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Posts_Tools {
	use Tool_Base;

	const STATUSES = [ 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'any' ];
	const FORMATS  = [ 'standard', 'aside', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio', 'chat' ];

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_posts',
			'description' => 'List blog posts with rich filtering (categories, tags, author, dates).',
			'tier'        => 'free',
			'module'      => 'class-posts-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'search'      => [ 'type' => 'string' ],
					'status'      => [ 'type' => 'string', 'enum' => self::STATUSES, 'default' => 'any' ],
					'categories'  => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ], 'description' => 'Category ids.' ],
					'tags'        => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
					'author'      => [ 'type' => 'integer' ],
					'date_after'  => [ 'type' => 'string', 'description' => 'ISO 8601 (inclusive lower bound).' ],
					'date_before' => [ 'type' => 'string' ],
					'sticky'      => [ 'type' => 'boolean' ],
					'orderby'     => [ 'type' => 'string', 'enum' => [ 'date', 'modified', 'title', 'comment_count', 'id' ], 'default' => 'date' ],
					'order'       => [ 'type' => 'string', 'enum' => [ 'asc', 'desc' ], 'default' => 'desc' ],
					'page'        => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page'    => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_posts' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_post',
			'description' => 'Get a single blog post by id with content, taxonomies and meta.',
			'tier'        => 'free',
			'module'      => 'class-posts-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id' => [ 'type' => 'integer' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_post' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_post',
			'description' => 'Create a new blog post.',
			'tier'        => 'free',
			'module'      => 'class-posts-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'title' ],
				'properties' => [
					'title'          => [ 'type' => 'string' ],
					'content'        => [ 'type' => 'string' ],
					'slug'           => [ 'type' => 'string' ],
					'status'         => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending', 'private', 'future' ], 'default' => 'draft' ],
					'categories'     => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
					'tags'           => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
					'featured_media' => [ 'type' => 'integer' ],
					'excerpt'        => [ 'type' => 'string' ],
					'format'         => [ 'type' => 'string', 'enum' => self::FORMATS ],
					'sticky'         => [ 'type' => 'boolean' ],
					'date'           => [ 'type' => 'string', 'description' => 'ISO 8601 publish date.' ],
					'meta'           => [ 'type' => 'object', 'additionalProperties' => true ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_post' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_post',
			'description' => 'Update an existing blog post.',
			'tier'        => 'free',
			'module'      => 'class-posts-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'             => [ 'type' => 'integer' ],
					'title'          => [ 'type' => 'string' ],
					'content'        => [ 'type' => 'string' ],
					'slug'           => [ 'type' => 'string' ],
					'status'         => [ 'type' => 'string', 'enum' => self::STATUSES ],
					'categories'     => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
					'tags'           => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
					'featured_media' => [ 'type' => 'integer' ],
					'excerpt'        => [ 'type' => 'string' ],
					'format'         => [ 'type' => 'string', 'enum' => self::FORMATS ],
					'sticky'         => [ 'type' => 'boolean' ],
					'meta'           => [ 'type' => 'object', 'additionalProperties' => true ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_post' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_post',
			'description' => 'Delete a blog post (trash unless force=true).',
			'tier'        => 'free',
			'module'      => 'class-posts-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'    => [ 'type' => 'integer' ],
					'force' => [ 'type' => 'boolean', 'default' => false ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_post' ] );

		// Categories.
		$registry->register( [
			'name'        => 'store_mcp_list_post_categories',
			'description' => 'List blog post categories.',
			'tier'        => 'free',
			'module'      => 'class-posts-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'search'     => [ 'type' => 'string' ],
					'parent'     => [ 'type' => 'integer' ],
					'hide_empty' => [ 'type' => 'boolean', 'default' => false ],
					'page'       => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page'   => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_post_categories' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_post_category',
			'description' => 'Create a blog post category.',
			'tier'        => 'free',
			'module'      => 'class-posts-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'name' ],
				'properties' => [
					'name'        => [ 'type' => 'string' ],
					'slug'        => [ 'type' => 'string' ],
					'parent'      => [ 'type' => 'integer' ],
					'description' => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_post_category' ] );

		// Tags.
		$registry->register( [
			'name'        => 'store_mcp_list_post_tags',
			'description' => 'List blog post tags.',
			'tier'        => 'free',
			'module'      => 'class-posts-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'search'   => [ 'type' => 'string' ],
					'page'     => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_post_tags' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_post_tag',
			'description' => 'Create a blog post tag.',
			'tier'        => 'free',
			'module'      => 'class-posts-tools',
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
		], [ self::class, 'create_post_tag' ] );
	}

	public static function list_posts( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'read' );

		$pagination = self::pagination( $args );

		$query_args = [
			'post_type'      => 'post',
			'post_status'    => self::arg_enum( $args, 'status', self::STATUSES, 'any' ),
			'posts_per_page' => $pagination['per_page'],
			'paged'          => $pagination['page'],
			'orderby'        => self::arg_enum( $args, 'orderby', [ 'date', 'modified', 'title', 'comment_count', 'id' ], 'date' ),
			'order'          => strtoupper( (string) self::arg_enum( $args, 'order', [ 'asc', 'desc' ], 'desc' ) ),
		];

		$search = self::arg_string( $args, 'search', '' );
		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}
		$cats = self::arg_int_list( $args, 'categories' );
		if ( $cats ) {
			$query_args['category__in'] = $cats;
		}
		$tags = self::arg_int_list( $args, 'tags' );
		if ( $tags ) {
			$query_args['tag__in'] = $tags;
		}
		$author = self::arg_int( $args, 'author' );
		if ( null !== $author ) {
			$query_args['author'] = $author;
		}
		$sticky = self::arg_bool( $args, 'sticky' );
		if ( true === $sticky ) {
			$query_args['post__in'] = (array) get_option( 'sticky_posts', [] ) ?: [ 0 ];
		}

		$after  = self::arg_string( $args, 'date_after', '' );
		$before = self::arg_string( $args, 'date_before', '' );
		if ( $after || $before ) {
			$clause = [];
			if ( $after )  $clause['after']  = $after;
			if ( $before ) $clause['before'] = $before;
			$query_args['date_query'] = [ $clause ];
		}

		$query = new \WP_Query( $query_args );
		$items = array_map( static fn( \WP_Post $p ) => self::format_post( $p, false ), $query->posts );

		return self::paginated( $items, (int) $query->found_posts, $pagination['page'], $pagination['per_page'] );
	}

	public static function get_post( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'read' );

		$id   = (int) self::require_arg( $args, 'id' );
		$post = get_post( $id );
		if ( ! $post || 'post' !== $post->post_type ) {
			throw new Tool_Exception( esc_html__( 'Post not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$data = self::format_post( $post, true );
		$data['categories'] = self::format_term_refs( wp_get_post_categories( $id ), 'category' );
		$data['tags']       = self::format_term_refs( wp_get_post_tags( $id, [ 'fields' => 'ids' ] ), 'post_tag' );
		$data['format']     = get_post_format( $id ) ?: 'standard';
		$data['sticky']     = is_sticky( $id );
		$data['meta']       = self::collect_meta( $id );
		return $data;
	}

	public static function create_post( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_posts' );

		$status = (string) self::arg_enum( $args, 'status', [ 'publish', 'draft', 'pending', 'private', 'future' ], 'draft' );
		if ( 'publish' === $status ) {
			Permissions::require_cap( $context, 'publish_posts' );
		}

		$post_data = [
			'post_type'    => 'post',
			'post_title'   => self::arg_string( $args, 'title' ),
			'post_content' => self::arg_html( $args, 'content', '' ),
			'post_excerpt' => self::arg_textarea( $args, 'excerpt', '' ),
			'post_status'  => $status,
			'post_author'  => $context->user_id,
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
			throw new Tool_Exception( esc_html( $id->get_error_message() ), Server::ERR_TOOL_FAILED );
		}

		self::apply_post_taxonomies( $id, $args );
		self::apply_post_extras( $id, $args );

		return self::get_post( [ 'id' => $id ], $context );
	}

	public static function update_post( array $args, AuthContext $context ): array {
		$id = (int) self::require_arg( $args, 'id' );
		Permissions::require_cap( $context, 'edit_post', $id );

		$post = get_post( $id );
		if ( ! $post || 'post' !== $post->post_type ) {
			throw new Tool_Exception( esc_html__( 'Post not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$update = [ 'ID' => $id ];
		if ( array_key_exists( 'title', $args ) )      $update['post_title']   = self::arg_string( $args, 'title' );
		if ( array_key_exists( 'content', $args ) )    $update['post_content'] = self::arg_html( $args, 'content' );
		if ( array_key_exists( 'excerpt', $args ) )    $update['post_excerpt'] = self::arg_textarea( $args, 'excerpt' );
		if ( array_key_exists( 'slug', $args ) )       $update['post_name']    = sanitize_title( self::arg_string( $args, 'slug' ) );
		if ( array_key_exists( 'status', $args ) ) {
			$update['post_status'] = (string) self::arg_enum( $args, 'status', self::STATUSES );
		}

		$result = wp_update_post( wp_slash( $update ), true );
		if ( is_wp_error( $result ) ) {
			throw new Tool_Exception( esc_html( $result->get_error_message() ), Server::ERR_TOOL_FAILED );
		}

		self::apply_post_taxonomies( $id, $args );
		self::apply_post_extras( $id, $args );

		return self::get_post( [ 'id' => $id ], $context );
	}

	public static function delete_post( array $args, AuthContext $context ): array {
		$id = (int) self::require_arg( $args, 'id' );
		Permissions::require_cap( $context, 'delete_post', $id );

		$force = (bool) self::arg_bool( $args, 'force', false );
		$ok    = wp_delete_post( $id, $force );

		if ( ! $ok ) {
			throw new Tool_Exception( esc_html__( 'Could not delete post', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}
		return [ 'id' => $id, 'deleted' => true, 'force' => $force ];
	}

	public static function list_post_categories( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'read' );
		return self::list_terms( 'category', $args );
	}

	public static function create_post_category( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'manage_categories' );
		return self::create_term( 'category', $args );
	}

	public static function list_post_tags( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'read' );
		return self::list_terms( 'post_tag', $args );
	}

	public static function create_post_tag( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'manage_categories' );
		return self::create_term( 'post_tag', $args );
	}

	private static function list_terms( string $taxonomy, array $args ): array {
		$pagination = self::pagination( $args, 50 );
		$query_args = [
			'taxonomy'   => $taxonomy,
			'hide_empty' => (bool) self::arg_bool( $args, 'hide_empty', false ),
			'number'     => $pagination['per_page'],
			'offset'     => ( $pagination['page'] - 1 ) * $pagination['per_page'],
			'search'     => self::arg_string( $args, 'search', '' ),
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

		$items = array_map( [ self::class, 'format_term' ], $terms );
		return self::paginated( $items, $total, $pagination['page'], $pagination['per_page'] );
	}

	private static function create_term( string $taxonomy, array $args ): array {
		$name = (string) self::require_arg( $args, 'name' );
		$opts = [];
		if ( ! empty( $args['slug'] ) )        $opts['slug']        = sanitize_title( (string) $args['slug'] );
		if ( ! empty( $args['parent'] ) )      $opts['parent']      = (int) $args['parent'];
		if ( ! empty( $args['description'] ) ) $opts['description'] = wp_kses_post( (string) $args['description'] );

		$result = wp_insert_term( $name, $taxonomy, $opts );
		if ( is_wp_error( $result ) ) {
			throw new Tool_Exception( esc_html( $result->get_error_message() ), Server::ERR_TOOL_FAILED );
		}
		$term = get_term( (int) $result['term_id'], $taxonomy );
		return self::format_term( $term );
	}

	private static function apply_post_taxonomies( int $id, array $args ): void {
		if ( array_key_exists( 'categories', $args ) ) {
			wp_set_post_categories( $id, self::arg_int_list( $args, 'categories' ), false );
		}
		if ( array_key_exists( 'tags', $args ) ) {
			wp_set_post_tags( $id, self::arg_int_list( $args, 'tags' ), false );
		}
	}

	private static function apply_post_extras( int $id, array $args ): void {
		if ( isset( $args['featured_media'] ) ) {
			$thumb = (int) $args['featured_media'];
			if ( $thumb > 0 ) {
				set_post_thumbnail( $id, $thumb );
			} else {
				delete_post_thumbnail( $id );
			}
		}
		if ( isset( $args['format'] ) ) {
			set_post_format( $id, sanitize_text_field( (string) $args['format'] ) );
		}
		if ( isset( $args['sticky'] ) ) {
			if ( filter_var( $args['sticky'], FILTER_VALIDATE_BOOLEAN ) ) {
				stick_post( $id );
			} else {
				unstick_post( $id );
			}
		}
		if ( ! empty( $args['meta'] ) && is_array( $args['meta'] ) ) {
			foreach ( $args['meta'] as $key => $value ) {
				$key = sanitize_key( (string) $key );
				if ( '' === $key || str_starts_with( $key, '_' ) ) {
					continue;
				}
				update_post_meta( $id, $key, $value );
			}
		}
	}

	private static function collect_meta( int $post_id ): array {
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

add_action( 'store_mcp_register_tools', [ Posts_Tools::class, 'register' ] );
