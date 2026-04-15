<?php
/**
 * Reviews tools — product reviews moderation (PRO).
 *
 * Reviews are stored as comments with comment_type = 'review'.
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Reviews_Tools {
	use Tool_Base;

	const STATUSES = [ 'approved', 'hold', 'spam', 'trash' ];

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_reviews',
			'description' => 'List product reviews with filters.',
			'tier'        => 'pro',
			'module'      => 'class-reviews-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'product_id' => [ 'type' => 'integer' ],
					'status'     => [ 'type' => 'string', 'enum' => array_merge( [ 'all' ], self::STATUSES ), 'default' => 'all' ],
					'rating'     => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 5 ],
					'page'       => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page'   => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_reviews' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_review',
			'description' => 'Get a single review.',
			'tier'        => 'pro',
			'module'      => 'class-reviews-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_review' ] );

		$registry->register( [
			'name'        => 'store_mcp_create_review',
			'description' => 'Create a review on a product.',
			'tier'        => 'pro',
			'module'      => 'class-reviews-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'product_id', 'review', 'reviewer', 'reviewer_email' ],
				'properties' => [
					'product_id'     => [ 'type' => 'integer' ],
					'review'         => [ 'type' => 'string' ],
					'reviewer'       => [ 'type' => 'string' ],
					'reviewer_email' => [ 'type' => 'string', 'format' => 'email' ],
					'rating'         => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 5 ],
					'status'         => [ 'type' => 'string', 'enum' => self::STATUSES, 'default' => 'approved' ],
					'verified'       => [ 'type' => 'boolean', 'default' => false ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'create_review' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_review',
			'description' => 'Update a review (moderation).',
			'tier'        => 'pro',
			'module'      => 'class-reviews-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'     => [ 'type' => 'integer' ],
					'review' => [ 'type' => 'string' ],
					'rating' => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 5 ],
					'status' => [ 'type' => 'string', 'enum' => self::STATUSES ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_review' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_review',
			'description' => 'Delete a review.',
			'tier'        => 'pro',
			'module'      => 'class-reviews-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'    => [ 'type' => 'integer' ],
					'force' => [ 'type' => 'boolean', 'default' => true ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_review' ] );
	}

	public static function list_reviews( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'moderate_comments' );

		$pagination = self::pagination( $args );
		$query_args = [
			'type'    => 'review',
			'status'  => self::resolve_status( self::arg_enum( $args, 'status', array_merge( [ 'all' ], self::STATUSES ), 'all' ) ),
			'number'  => $pagination['per_page'],
			'offset'  => ( $pagination['page'] - 1 ) * $pagination['per_page'],
			'post_type' => 'product',
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		];
		if ( $pid = self::arg_int( $args, 'product_id' ) ) {
			$query_args['post_id'] = $pid;
		}
		if ( $rating = self::arg_int( $args, 'rating' ) ) {
			$query_args['meta_query'] = [ [ 'key' => 'rating', 'value' => $rating ] ];
		}

		$comments = get_comments( $query_args );
		$total_args = $query_args;
		$total_args['count']  = true;
		$total_args['number'] = 0;
		$total_args['offset'] = 0;
		$total = (int) get_comments( $total_args );

		$items = array_map( [ self::class, 'format_review' ], $comments );
		return self::paginated( $items, $total, $pagination['page'], $pagination['per_page'] );
	}

	public static function get_review( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'moderate_comments' );

		$id      = (int) self::require_arg( $args, 'id' );
		$comment = get_comment( $id );
		if ( ! $comment || 'review' !== $comment->comment_type ) {
			throw new Tool_Exception( esc_html__( 'Review not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		return self::format_review( $comment );
	}

	public static function create_review( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'moderate_comments' );

		$pid = (int) self::require_arg( $args, 'product_id' );
		if ( ! get_post( $pid ) ) {
			throw new Tool_Exception( esc_html__( 'Product not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$status = (string) self::arg_enum( $args, 'status', self::STATUSES, 'approved' );
		$data   = [
			'comment_post_ID'      => $pid,
			'comment_author'       => sanitize_text_field( (string) self::require_arg( $args, 'reviewer' ) ),
			'comment_author_email' => sanitize_email( (string) self::require_arg( $args, 'reviewer_email' ) ),
			'comment_content'      => wp_kses_post( (string) self::require_arg( $args, 'review' ) ),
			'comment_type'         => 'review',
			'comment_approved'     => 'approved' === $status ? 1 : ( 'hold' === $status ? 0 : $status ),
			'user_id'              => $context->user_id,
		];

		$id = wp_insert_comment( wp_slash( $data ) );
		if ( ! $id ) {
			throw new Tool_Exception( esc_html__( 'Could not create review', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}

		$rating = self::arg_int( $args, 'rating' );
		if ( $rating ) {
			add_comment_meta( $id, 'rating', max( 1, min( 5, $rating ) ) );
		}
		if ( (bool) self::arg_bool( $args, 'verified', false ) ) {
			add_comment_meta( $id, 'verified', 1 );
		}

		return self::format_review( get_comment( $id ) );
	}

	public static function update_review( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'moderate_comments' );

		$id      = (int) self::require_arg( $args, 'id' );
		$comment = get_comment( $id );
		if ( ! $comment || 'review' !== $comment->comment_type ) {
			throw new Tool_Exception( esc_html__( 'Review not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$update = [ 'comment_ID' => $id ];
		if ( array_key_exists( 'review', $args ) ) {
			$update['comment_content'] = wp_kses_post( (string) $args['review'] );
		}
		if ( array_key_exists( 'status', $args ) ) {
			$status = (string) self::arg_enum( $args, 'status', self::STATUSES );
			if ( 'trash' === $status ) {
				wp_trash_comment( $id );
			} elseif ( 'spam' === $status ) {
				wp_spam_comment( $id );
			} else {
				wp_set_comment_status( $id, 'approved' === $status ? 'approve' : 'hold' );
			}
		}
		if ( isset( $update['comment_content'] ) ) {
			wp_update_comment( $update );
		}

		if ( array_key_exists( 'rating', $args ) ) {
			update_comment_meta( $id, 'rating', max( 1, min( 5, (int) $args['rating'] ) ) );
		}

		return self::format_review( get_comment( $id ) );
	}

	public static function delete_review( array $args, AuthContext $context ): array {
		Permissions::require_woocommerce();
		Permissions::require_cap( $context, 'moderate_comments' );

		$id    = (int) self::require_arg( $args, 'id' );
		$force = (bool) self::arg_bool( $args, 'force', true );
		$ok    = wp_delete_comment( $id, $force );
		if ( ! $ok ) {
			throw new Tool_Exception( esc_html__( 'Could not delete review', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}
		return [ 'id' => $id, 'deleted' => true, 'force' => $force ];
	}

	private static function resolve_status( ?string $status ): string {
		return match ( $status ) {
			'approved' => 'approve',
			'hold'     => 'hold',
			'spam'     => 'spam',
			'trash'    => 'trash',
			default    => '',
		};
	}

	private static function format_review( \WP_Comment $c ): array {
		$rating = (int) get_comment_meta( $c->comment_ID, 'rating', true );
		$map    = [ '1' => 'approved', '0' => 'hold', 'spam' => 'spam', 'trash' => 'trash' ];
		$status = $map[ (string) $c->comment_approved ] ?? $c->comment_approved;

		return [
			'id'             => (int) $c->comment_ID,
			'product_id'     => (int) $c->comment_post_ID,
			'status'         => (string) $status,
			'reviewer'       => $c->comment_author,
			'reviewer_email' => $c->comment_author_email,
			'review'         => $c->comment_content,
			'rating'         => $rating ?: null,
			'verified'       => (bool) get_comment_meta( $c->comment_ID, 'verified', true ),
			'date_created'   => mysql2date( 'c', $c->comment_date_gmt ?: $c->comment_date ),
		];
	}
}

add_action( 'store_mcp_register_tools', [ Reviews_Tools::class, 'register' ] );
