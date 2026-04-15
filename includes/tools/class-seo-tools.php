<?php
/**
 * SEO tools — post meta, Yoast/RankMath-compatible metadata, site options (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class SEO_Tools {
	use Tool_Base;

	/**
	 * Allow-list of site options that can be read/written via MCP. Anything
	 * not on this list is rejected to avoid accidental site breakage.
	 */
	const SITE_OPTION_ALLOWLIST = [
		'blogname', 'blogdescription', 'admin_email', 'siteurl', 'home',
		'timezone_string', 'gmt_offset', 'date_format', 'time_format',
		'start_of_week', 'WPLANG', 'default_category', 'default_comment_status',
		'default_ping_status', 'posts_per_page', 'posts_per_rss',
		'rss_use_excerpt', 'show_on_front', 'page_on_front', 'page_for_posts',
		'permalink_structure', 'category_base', 'tag_base', 'users_can_register',
		'default_role', 'thread_comments', 'thread_comments_depth', 'page_comments',
		'comments_per_page', 'default_comments_page', 'comment_order',
		'comments_notify', 'moderation_notify', 'comment_moderation',
		'comment_registration', 'close_comments_for_old_posts', 'close_comments_days_old',
	];

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_get_post_meta',
			'description' => 'Get post meta. Omit meta_key to return all public meta.',
			'tier'        => 'pro',
			'module'      => 'class-seo-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'post_id' ],
				'properties' => [
					'post_id'           => [ 'type' => 'integer' ],
					'meta_key'          => [ 'type' => 'string' ],
					'include_protected' => [ 'type' => 'boolean', 'default' => false, 'description' => 'Include keys starting with underscore.' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_post_meta' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_post_meta',
			'description' => 'Set or update a post meta value.',
			'tier'        => 'pro',
			'module'      => 'class-seo-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'post_id', 'meta_key', 'meta_value' ],
				'properties' => [
					'post_id'    => [ 'type' => 'integer' ],
					'meta_key'   => [ 'type' => 'string' ],
					'meta_value' => [ 'description' => 'Scalar, array or object.' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_post_meta' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_post_meta',
			'description' => 'Delete a post meta key.',
			'tier'        => 'pro',
			'module'      => 'class-seo-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'post_id', 'meta_key' ],
				'properties' => [
					'post_id'  => [ 'type' => 'integer' ],
					'meta_key' => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_post_meta' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_yoast_meta',
			'description' => 'Get SEO metadata — auto-detects Yoast or Rank Math and returns a normalized payload.',
			'tier'        => 'pro',
			'module'      => 'class-seo-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'post_id' ],
				'properties' => [ 'post_id' => [ 'type' => 'integer' ] ],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_yoast_meta' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_yoast_meta',
			'description' => 'Update SEO metadata (Yoast or Rank Math compatible).',
			'tier'        => 'pro',
			'module'      => 'class-seo-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'post_id' ],
				'properties' => [
					'post_id'         => [ 'type' => 'integer' ],
					'title'           => [ 'type' => 'string' ],
					'description'     => [ 'type' => 'string' ],
					'focus_keyword'   => [ 'type' => 'string' ],
					'canonical'       => [ 'type' => 'string' ],
					'og_title'        => [ 'type' => 'string' ],
					'og_description'  => [ 'type' => 'string' ],
					'robots_noindex'  => [ 'type' => 'boolean' ],
					'robots_nofollow' => [ 'type' => 'boolean' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_yoast_meta' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_site_options',
			'description' => 'Read site options from an allow-list.',
			'tier'        => 'pro',
			'module'      => 'class-seo-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'keys' => [
						'type'  => 'array',
						'items' => [ 'type' => 'string' ],
						'description' => 'Option names. Omit to return all allow-listed options.',
					],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_site_options' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_site_option',
			'description' => 'Update a single allow-listed site option.',
			'tier'        => 'pro',
			'module'      => 'class-seo-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'key', 'value' ],
				'properties' => [
					'key'   => [ 'type' => 'string' ],
					'value' => [],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_site_option' ] );
	}

	public static function get_post_meta( array $args, AuthContext $context ): array {
		$post_id = (int) self::require_arg( $args, 'post_id' );
		Permissions::require_cap( $context, 'edit_post', $post_id );

		$key = self::arg_string( $args, 'meta_key', '' );
		if ( '' !== $key ) {
			self::guard_meta_key( $key, self::arg_bool( $args, 'include_protected', false ) );
			return [
				'post_id'  => $post_id,
				'meta_key' => $key,
				'value'    => get_post_meta( $post_id, $key, false ),
			];
		}

		$include_protected = (bool) self::arg_bool( $args, 'include_protected', false );
		$all               = (array) get_post_meta( $post_id );
		$out               = [];
		foreach ( $all as $k => $values ) {
			if ( ! $include_protected && str_starts_with( (string) $k, '_' ) ) {
				continue;
			}
			$out[ $k ] = count( $values ) === 1 ? maybe_unserialize( $values[0] ) : array_map( 'maybe_unserialize', $values );
		}
		return [ 'post_id' => $post_id, 'meta' => $out ];
	}

	public static function update_post_meta( array $args, AuthContext $context ): array {
		$post_id = (int) self::require_arg( $args, 'post_id' );
		Permissions::require_cap( $context, 'edit_post', $post_id );

		$key = sanitize_key( (string) self::require_arg( $args, 'meta_key' ) );
		self::guard_meta_key( $key, true );
		$value = self::arg( $args, 'meta_value', '' );

		update_post_meta( $post_id, $key, $value );
		return [ 'post_id' => $post_id, 'meta_key' => $key, 'value' => get_post_meta( $post_id, $key, false ) ];
	}

	public static function delete_post_meta( array $args, AuthContext $context ): array {
		$post_id = (int) self::require_arg( $args, 'post_id' );
		Permissions::require_cap( $context, 'edit_post', $post_id );

		$key = sanitize_key( (string) self::require_arg( $args, 'meta_key' ) );
		self::guard_meta_key( $key, true );

		delete_post_meta( $post_id, $key );
		return [ 'post_id' => $post_id, 'meta_key' => $key, 'deleted' => true ];
	}

	public static function get_yoast_meta( array $args, AuthContext $context ): array {
		$post_id = (int) self::require_arg( $args, 'post_id' );
		Permissions::require_cap( $context, 'edit_post', $post_id );

		$engine = self::detect_seo_engine();

		if ( 'rank-math' === $engine ) {
			$robots         = (array) get_post_meta( $post_id, 'rank_math_robots', true ) ?: [];
			return [
				'engine'          => 'rank-math',
				'title'           => (string) get_post_meta( $post_id, 'rank_math_title', true ),
				'description'     => (string) get_post_meta( $post_id, 'rank_math_description', true ),
				'focus_keyword'   => (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true ),
				'canonical'       => (string) get_post_meta( $post_id, 'rank_math_canonical_url', true ),
				'og_title'        => (string) get_post_meta( $post_id, 'rank_math_facebook_title', true ),
				'og_description'  => (string) get_post_meta( $post_id, 'rank_math_facebook_description', true ),
				'robots_noindex'  => in_array( 'noindex', $robots, true ),
				'robots_nofollow' => in_array( 'nofollow', $robots, true ),
			];
		}

		return [
			'engine'          => 'yoast',
			'title'           => (string) get_post_meta( $post_id, '_yoast_wpseo_title', true ),
			'description'     => (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
			'focus_keyword'   => (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
			'canonical'       => (string) get_post_meta( $post_id, '_yoast_wpseo_canonical', true ),
			'og_title'        => (string) get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true ),
			'og_description'  => (string) get_post_meta( $post_id, '_yoast_wpseo_opengraph-description', true ),
			'robots_noindex'  => '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ),
			'robots_nofollow' => '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true ),
		];
	}

	public static function update_yoast_meta( array $args, AuthContext $context ): array {
		$post_id = (int) self::require_arg( $args, 'post_id' );
		Permissions::require_cap( $context, 'edit_post', $post_id );

		$engine = self::detect_seo_engine();
		$map    = 'rank-math' === $engine ? self::rank_math_map() : self::yoast_map();

		$transforms = 'rank-math' === $engine ? self::rank_math_transforms() : self::yoast_transforms();

		foreach ( $args as $key => $value ) {
			if ( 'post_id' === $key || ! isset( $map[ $key ] ) ) {
				continue;
			}
			$meta_key = $map[ $key ];
			$transform = $transforms[ $key ] ?? null;
			$stored    = $transform ? $transform( $post_id, $value ) : ( is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $value );
			if ( null === $stored ) {
				continue;
			}
			update_post_meta( $post_id, $meta_key, $stored );
		}

		return self::get_yoast_meta( [ 'post_id' => $post_id ], $context );
	}

	public static function get_site_options( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'manage_options' );

		$keys = self::arg_array( $args, 'keys', [] );
		if ( empty( $keys ) ) {
			$keys = self::SITE_OPTION_ALLOWLIST;
		}

		$out = [];
		foreach ( $keys as $key ) {
			$k = sanitize_key( (string) $key );
			if ( ! in_array( $k, self::SITE_OPTION_ALLOWLIST, true ) ) {
				continue;
			}
			$out[ $k ] = get_option( $k );
		}
		return [ 'options' => $out, 'count' => count( $out ) ];
	}

	public static function update_site_option( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'manage_options' );

		$key = sanitize_key( (string) self::require_arg( $args, 'key' ) );
		if ( ! in_array( $key, self::SITE_OPTION_ALLOWLIST, true ) ) {
			throw new Tool_Exception( esc_html( sprintf( /* translators: %s: option */ __( 'Option not in allow-list: %s', 'store-mcp' ), $key ) ), Server::ERR_FORBIDDEN );
		}
		$value = self::arg( $args, 'value' );
		update_option( $key, $value );
		return [ 'key' => $key, 'value' => get_option( $key ) ];
	}

	private static function detect_seo_engine(): string {
		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( '\\RankMath' ) ) {
			return 'rank-math';
		}
		if ( defined( 'WPSEO_VERSION' ) || defined( 'YOAST_ENVIRONMENT' ) ) {
			return 'yoast';
		}
		return 'yoast';
	}

	private static function guard_meta_key( string $key, bool $allow_protected ): void {
		if ( ! $allow_protected && str_starts_with( $key, '_' ) ) {
			throw new Tool_Exception( esc_html__( 'Protected meta keys require include_protected=true', 'store-mcp' ), Server::ERR_FORBIDDEN );
		}
		$blocked = [ 'user_pass', 'user_activation_key', 'session_tokens' ];
		if ( in_array( $key, $blocked, true ) ) {
			throw new Tool_Exception( esc_html__( 'Meta key is blocked', 'store-mcp' ), Server::ERR_FORBIDDEN );
		}
	}

	private static function yoast_map(): array {
		return [
			'title'          => '_yoast_wpseo_title',
			'description'    => '_yoast_wpseo_metadesc',
			'focus_keyword'  => '_yoast_wpseo_focuskw',
			'canonical'      => '_yoast_wpseo_canonical',
			'og_title'       => '_yoast_wpseo_opengraph-title',
			'og_description' => '_yoast_wpseo_opengraph-description',
			'robots_noindex' => '_yoast_wpseo_meta-robots-noindex',
			'robots_nofollow'=> '_yoast_wpseo_meta-robots-nofollow',
		];
	}

	private static function rank_math_map(): array {
		return [
			'title'          => 'rank_math_title',
			'description'    => 'rank_math_description',
			'focus_keyword'  => 'rank_math_focus_keyword',
			'canonical'      => 'rank_math_canonical_url',
			'og_title'       => 'rank_math_facebook_title',
			'og_description' => 'rank_math_facebook_description',
			'robots_noindex' => 'rank_math_robots',
			'robots_nofollow'=> 'rank_math_robots',
		];
	}

	private static function yoast_transforms(): array {
		return [
			'robots_noindex'  => static fn( $pid, $v ) => filter_var( $v, FILTER_VALIDATE_BOOLEAN ) ? '1' : '0',
			'robots_nofollow' => static fn( $pid, $v ) => filter_var( $v, FILTER_VALIDATE_BOOLEAN ) ? '1' : '0',
		];
	}

	private static function rank_math_transforms(): array {
		$mutate = static function ( int $pid, string $flag, bool $on ): array {
			$current = (array) get_post_meta( $pid, 'rank_math_robots', true ) ?: [];
			$current = array_values( array_diff( $current, [ $flag ] ) );
			if ( $on ) {
				$current[] = $flag;
			}
			return $current;
		};
		return [
			'robots_noindex'  => static fn( $pid, $v ) => $mutate( $pid, 'noindex', filter_var( $v, FILTER_VALIDATE_BOOLEAN ) ),
			'robots_nofollow' => static fn( $pid, $v ) => $mutate( $pid, 'nofollow', filter_var( $v, FILTER_VALIDATE_BOOLEAN ) ),
		];
	}
}

add_action( 'store_mcp_register_tools', [ SEO_Tools::class, 'register' ] );
