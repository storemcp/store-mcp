<?php
/**
 * Site tools — site info (FREE), health/flush/search-replace (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Site_Tools {
	use Tool_Base;

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_get_site_info',
			'description' => 'Get general WordPress and WooCommerce site information: versions, theme, active plugins, locale, URLs.',
			'tier'        => 'free',
			'module'      => 'class-site-tools',
			'inputSchema' => [
				'type'                 => 'object',
				'properties'           => (object) [],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_site_info' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_site_health',
			'description' => 'Run WordPress Site Health checks (PRO). Returns critical, recommended and good results.',
			'tier'        => 'pro',
			'module'      => 'class-site-tools',
			'inputSchema' => [
				'type'                 => 'object',
				'properties'           => (object) [],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_site_health' ] );

		$registry->register( [
			'name'        => 'store_mcp_flush_cache',
			'description' => 'Flush WordPress object cache and any detected page-cache plugin (W3TC, WP Super Cache, LiteSpeed, WP Rocket).',
			'tier'        => 'pro',
			'module'      => 'class-site-tools',
			'inputSchema' => [
				'type'                 => 'object',
				'properties'           => (object) [],
				'additionalProperties' => false,
			],
		], [ self::class, 'flush_cache' ] );

		$registry->register( [
			'name'        => 'store_mcp_search_replace',
			'description' => 'Search and replace text in post content. By default runs in dry-run mode and only reports matches.',
			'tier'        => 'pro',
			'module'      => 'class-site-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'search', 'replace' ],
				'properties' => [
					'search'     => [ 'type' => 'string', 'description' => 'Text to search for (case sensitive).' ],
					'replace'    => [ 'type' => 'string', 'description' => 'Replacement text.' ],
					'post_types' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Post types to scan. Defaults to public types.' ],
					'dry_run'    => [ 'type' => 'boolean', 'default' => true, 'description' => 'When true, only reports matches without writing.' ],
					'limit'      => [ 'type' => 'integer', 'default' => 200, 'minimum' => 1, 'maximum' => 5000 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'search_replace' ] );
	}

	public static function get_site_info( array $args, AuthContext $context ): array {
		global $wp_version;

		Permissions::require_cap( $context, 'read' );

		$theme = wp_get_theme();
		$active_plugins = (array) get_option( 'active_plugins', [] );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
		}

		return [
			'name'                => get_bloginfo( 'name' ),
			'description'         => get_bloginfo( 'description' ),
			'url'                 => home_url(),
			'admin_url'           => admin_url(),
			'rest_url'            => rest_url(),
			'mcp_url'             => rest_url( STORE_MCP_REST_NAMESPACE . '/mcp' ),
			'language'            => get_locale(),
			'timezone'            => wp_timezone_string(),
			'date_format'         => get_option( 'date_format' ),
			'time_format'         => get_option( 'time_format' ),
			'is_ssl'              => is_ssl(),
			'is_multisite'        => is_multisite(),
			'permalink_structure' => get_option( 'permalink_structure' ),
			'wp_version'          => $wp_version,
			'php_version'         => PHP_VERSION,
			'mysql_version'       => $GLOBALS['wpdb']->db_version(),
			'mcp_version'         => STORE_MCP_VERSION,
			'mcp_protocol'        => STORE_MCP_PROTOCOL_VERSION,
			'tier'                => Plugin::instance()->license->tier(),
			'wc_version'          => defined( 'WC_VERSION' ) ? WC_VERSION : null,
			'wc_active'           => class_exists( 'WooCommerce' ),
			'theme'               => [
				'name'       => $theme->get( 'Name' ),
				'version'    => $theme->get( 'Version' ),
				'author'     => $theme->get( 'Author' ),
				'template'   => $theme->get_template(),
				'stylesheet' => $theme->get_stylesheet(),
			],
			'active_plugins'      => array_values( array_unique( $active_plugins ) ),
			'users_count'         => array_sum( (array) count_users()['avail_roles'] ),
		];
	}

	public static function get_site_health( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'manage_options' );

		require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';

		$health = \WP_Site_Health::get_instance();
		$tests  = \WP_Site_Health::get_tests();

		$results = [ 'good' => [], 'recommended' => [], 'critical' => [] ];

		foreach ( $tests['direct'] as $test_id => $test ) {
			if ( ! is_array( $test ) || empty( $test['test'] ) ) {
				continue;
			}
			$method = 'get_test_' . $test['test'];
			if ( ! method_exists( $health, $method ) ) {
				continue;
			}
			try {
				$result = $health->$method();
			} catch ( \Throwable $e ) {
				continue;
			}
			$status = $result['status'] ?? 'good';
			$bucket = isset( $results[ $status ] ) ? $status : 'good';
			$results[ $bucket ][] = [
				'test'        => $test_id,
				'label'       => $result['label'] ?? $test_id,
				'status'      => $status,
				'badge'       => $result['badge'] ?? null,
				'description' => isset( $result['description'] ) ? wp_strip_all_tags( $result['description'] ) : '',
			];
		}

		return $results;
	}

	public static function flush_cache( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'manage_options' );

		$cleared = [ 'object_cache' => false, 'plugins' => [] ];

		if ( function_exists( 'wp_cache_flush' ) ) {
			$cleared['object_cache'] = wp_cache_flush();
		}

		if ( function_exists( 'w3tc_flush_all' ) ) {
			\w3tc_flush_all();
			$cleared['plugins'][] = 'w3-total-cache';
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			\wp_cache_clear_cache();
			$cleared['plugins'][] = 'wp-super-cache';
		}
		if ( class_exists( '\\LiteSpeed\\Purge' ) ) {
			do_action( 'litespeed_purge_all' );
			$cleared['plugins'][] = 'litespeed-cache';
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			\rocket_clean_domain();
			$cleared['plugins'][] = 'wp-rocket';
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			\sg_cachepress_purge_cache();
			$cleared['plugins'][] = 'sg-optimizer';
		}

		do_action( 'store_mcp_flush_cache', $cleared );

		return $cleared;
	}

	public static function search_replace( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'manage_options' );

		$search  = (string) self::require_arg( $args, 'search' );
		$replace = (string) self::arg( $args, 'replace', '' );
		$dry     = self::arg_bool( $args, 'dry_run', true );
		$limit   = self::arg_int( $args, 'limit', 200, 1, 5000 );
		$types   = self::arg_array( $args, 'post_types', [] );

		if ( empty( $types ) ) {
			$types = array_values( get_post_types( [ 'public' => true ], 'names' ) );
		}

		$query = new \WP_Query( [
			'post_type'      => $types,
			'post_status'    => 'any',
			'posts_per_page' => $limit,
			's'              => $search,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );

		$matches  = [];
		$updated  = 0;
		$total    = 0;
		foreach ( $query->posts as $pid ) {
			$content = get_post_field( 'post_content', $pid );
			if ( false === strpos( $content, $search ) ) {
				continue;
			}
			$count = substr_count( $content, $search );
			$total += $count;
			$matches[] = [ 'id' => (int) $pid, 'title' => get_the_title( $pid ), 'matches' => $count ];

			if ( ! $dry ) {
				$new = str_replace( $search, $replace, $content );
				wp_update_post( [ 'ID' => $pid, 'post_content' => $new ] );
				++$updated;
			}
		}

		return [
			'dry_run'         => $dry,
			'matched_posts'   => count( $matches ),
			'matched_strings' => $total,
			'updated_posts'   => $updated,
			'matches'         => $matches,
		];
	}
}

add_action( 'store_mcp_register_tools', [ Site_Tools::class, 'register' ] );
