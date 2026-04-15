<?php
/**
 * Content resources — recent posts, drafts, scheduled.
 *
 * @package StoreMCP\Resources
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Content_Resources {
	use Tool_Base;

	public static function register( Resources_Registry $registry ): void {
		$registry->register( [
			'uri'         => 'store://content/recent-posts',
			'name'        => 'Recent posts',
			'description' => 'Ten most recent published posts.',
			'tier'        => 'free',
		], [ self::class, 'recent_posts' ] );

		$registry->register( [
			'uri'         => 'store://content/drafts',
			'name'        => 'Drafts',
			'description' => 'Posts currently in draft status.',
			'tier'        => 'free',
		], [ self::class, 'drafts' ] );

		$registry->register( [
			'uri'         => 'store://content/scheduled',
			'name'        => 'Scheduled posts',
			'description' => 'Posts scheduled for future publication.',
			'tier'        => 'free',
		], [ self::class, 'scheduled' ] );
	}

	public static function recent_posts( AuthContext $context ): array {
		Permissions::require_cap( $context, 'read' );
		return self::posts_by_status( 'publish', 10 );
	}

	public static function drafts( AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_posts' );
		return self::posts_by_status( 'draft', 25 );
	}

	public static function scheduled( AuthContext $context ): array {
		Permissions::require_cap( $context, 'edit_posts' );
		return self::posts_by_status( 'future', 25 );
	}

	private static function posts_by_status( string $status, int $limit ): array {
		$posts = get_posts( [
			'post_type'      => [ 'post', 'page' ],
			'post_status'    => $status,
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'suppress_filters' => true,
		] );

		return [
			'items' => array_map( static fn( \WP_Post $p ) => self::format_post( $p, false ), $posts ),
			'count' => count( $posts ),
		];
	}
}

add_action( 'store_mcp_register_resources', [ Content_Resources::class, 'register' ] );
