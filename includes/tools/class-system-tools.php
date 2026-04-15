<?php
/**
 * System tools — environment status, maintenance tools (PRO).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class System_Tools {
	use Tool_Base;

	const TOOLS = [
		'clear_transients',
		'clear_expired_transients',
		'clear_sessions',
		'recount_terms',
		'regenerate_thumbnails_meta',
		'db_update',
		'wc_update',
	];

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_system_status',
			'description' => 'Full environment report: WP/PHP/MySQL versions, memory limits, active plugins, theme, database tables.',
			'tier'        => 'pro',
			'module'      => 'class-system-tools',
			'inputSchema' => [ 'type' => 'object', 'properties' => (object) [], 'additionalProperties' => false ],
		], [ self::class, 'system_status' ] );

		$registry->register( [
			'name'        => 'store_mcp_list_system_tools',
			'description' => 'List maintenance tools that can be invoked with store_mcp_run_system_tool.',
			'tier'        => 'pro',
			'module'      => 'class-system-tools',
			'inputSchema' => [ 'type' => 'object', 'properties' => (object) [], 'additionalProperties' => false ],
		], [ self::class, 'list_system_tools' ] );

		$registry->register( [
			'name'        => 'store_mcp_run_system_tool',
			'description' => 'Run a maintenance tool. Some tools only take effect when WooCommerce is active.',
			'tier'        => 'pro',
			'module'      => 'class-system-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'tool_id' ],
				'properties' => [
					'tool_id' => [ 'type' => 'string', 'enum' => self::TOOLS ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'run_system_tool' ] );
	}

	public static function system_status( array $args, AuthContext $context ): array {
		global $wpdb, $wp_version;

		Permissions::require_cap( $context, 'manage_options' );

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$active      = (array) get_option( 'active_plugins', [] );

		$active_list   = [];
		$inactive_list = [];
		foreach ( $all_plugins as $file => $data ) {
			$entry = [ 'file' => $file, 'name' => $data['Name'] ?? '', 'version' => $data['Version'] ?? '' ];
			if ( in_array( $file, $active, true ) ) {
				$active_list[] = $entry;
			} else {
				$inactive_list[] = $entry;
			}
		}

		$theme   = wp_get_theme();
		$tables  = self::database_tables( $wpdb );

		return [
			'environment' => [
				'wp_version'   => $wp_version,
				'php_version'  => PHP_VERSION,
				'mysql_version'=> $wpdb->db_version(),
				'php_sapi'     => PHP_SAPI,
				'memory_limit' => ini_get( 'memory_limit' ),
				'wp_memory'    => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : null,
				'max_input_vars' => ini_get( 'max_input_vars' ),
				'max_execution_time' => (int) ini_get( 'max_execution_time' ),
				'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
				'post_max_size'       => ini_get( 'post_max_size' ),
				'is_multisite'  => is_multisite(),
				'is_ssl'        => is_ssl(),
				'locale'        => get_locale(),
				'timezone'      => wp_timezone_string(),
				'url'           => home_url(),
				'wp_debug'      => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'wp_debug_log'  => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			],
			'database' => [
				'prefix'      => $wpdb->prefix,
				'collation'   => $wpdb->collate,
				'charset'     => $wpdb->charset,
				'tables'      => $tables,
				'total_size'  => array_sum( array_column( $tables, 'size_mb' ) ),
			],
			'theme' => [
				'name'       => $theme->get( 'Name' ),
				'version'    => $theme->get( 'Version' ),
				'template'   => $theme->get_template(),
				'stylesheet' => $theme->get_stylesheet(),
				'is_child'   => $theme->parent() ? $theme->parent()->get_stylesheet() : null,
			],
			'plugins' => [
				'active'   => $active_list,
				'inactive' => $inactive_list,
			],
			'woocommerce' => class_exists( 'WooCommerce' ) ? [
				'active'       => true,
				'version'      => defined( 'WC_VERSION' ) ? WC_VERSION : null,
				'hpos_enabled' => class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' )
					&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),
				'currency'     => get_woocommerce_currency(),
				'country'      => wc_get_base_location()['country'] ?? '',
			] : [ 'active' => false ],
			'security' => [
				'https'              => is_ssl(),
				'disable_file_edit'  => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
				'automatic_updates'  => defined( 'AUTOMATIC_UPDATER_DISABLED' ) ? ! AUTOMATIC_UPDATER_DISABLED : true,
			],
			'store_mcp' => [
				'version'          => STORE_MCP_VERSION,
				'protocol_version' => STORE_MCP_PROTOCOL_VERSION,
				'tier'             => Plugin::instance()->license->tier(),
			],
		];
	}

	public static function list_system_tools( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'manage_options' );

		$descriptions = [
			'clear_transients'           => __( 'Delete all transients stored in the options table.', 'store-mcp' ),
			'clear_expired_transients'   => __( 'Delete only expired transients.', 'store-mcp' ),
			'clear_sessions'             => __( 'Clear WooCommerce customer sessions (requires WC).', 'store-mcp' ),
			'recount_terms'              => __( 'Recount post counts for all taxonomies.', 'store-mcp' ),
			'regenerate_thumbnails_meta' => __( 'Reset attachment metadata so thumbnails can be regenerated on next access.', 'store-mcp' ),
			'db_update'                  => __( 'Run the WP database upgrade routine.', 'store-mcp' ),
			'wc_update'                  => __( 'Run the WC database upgrade routine (requires WC).', 'store-mcp' ),
		];

		$items = [];
		foreach ( self::TOOLS as $id ) {
			$items[] = [ 'id' => $id, 'description' => $descriptions[ $id ] ?? '' ];
		}
		return [ 'items' => $items, 'count' => count( $items ) ];
	}

	public static function run_system_tool( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'manage_options' );

		$tool_id = (string) self::require_arg( $args, 'tool_id' );
		if ( ! in_array( $tool_id, self::TOOLS, true ) ) {
			throw new Tool_Exception( __( 'Unknown tool id', 'store-mcp' ), Server::RPC_INVALID_PARAMS );
		}

		return match ( $tool_id ) {
			'clear_transients'           => self::do_clear_transients( false ),
			'clear_expired_transients'   => self::do_clear_transients( true ),
			'clear_sessions'             => self::do_clear_sessions(),
			'recount_terms'              => self::do_recount_terms(),
			'regenerate_thumbnails_meta' => self::do_regenerate_thumbs_meta(),
			'db_update'                  => self::do_db_update(),
			'wc_update'                  => self::do_wc_update(),
		};
	}

	private static function do_clear_transients( bool $only_expired ): array {
		global $wpdb;

		if ( $only_expired ) {
			$now = time();
			$deleted = (int) $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				$now
			) );
			$wpdb->query( "DELETE o1 FROM {$wpdb->options} o1 LEFT JOIN {$wpdb->options} o2 ON o2.option_name = REPLACE(o1.option_name, '_transient_', '_transient_timeout_') WHERE o1.option_name LIKE '_transient_%' AND o1.option_name NOT LIKE '_transient_timeout_%' AND o2.option_name IS NULL" );
		} else {
			$deleted = (int) $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'" );
		}

		wp_cache_flush();

		return [ 'tool_id' => $only_expired ? 'clear_expired_transients' : 'clear_transients', 'rows_deleted' => $deleted ];
	}

	private static function do_clear_sessions(): array {
		Permissions::require_woocommerce();
		global $wpdb;
		$table = $wpdb->prefix . 'woocommerce_sessions';
		$rows  = (int) $wpdb->query( "TRUNCATE TABLE {$table}" );
		return [ 'tool_id' => 'clear_sessions', 'rows_cleared' => $rows ];
	}

	private static function do_recount_terms(): array {
		$taxonomies = get_taxonomies( [ 'show_ui' => true ] );
		$processed  = [];
		foreach ( $taxonomies as $tax ) {
			$term_ids = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids' ] );
			if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) continue;
			wp_update_term_count_now( $term_ids, $tax );
			$processed[ $tax ] = count( $term_ids );
		}
		return [ 'tool_id' => 'recount_terms', 'processed' => $processed ];
	}

	private static function do_regenerate_thumbs_meta(): array {
		global $wpdb;
		$rows = (int) $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attachment_metadata'" );
		return [ 'tool_id' => 'regenerate_thumbnails_meta', 'rows_cleared' => $rows, 'note' => 'Metadata is rebuilt lazily when attachments are next accessed.' ];
	}

	private static function do_db_update(): array {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		wp_upgrade();
		return [ 'tool_id' => 'db_update', 'completed' => true ];
	}

	private static function do_wc_update(): array {
		Permissions::require_woocommerce();

		if ( class_exists( '\\WC_Install' ) ) {
			\WC_Install::install();
			return [ 'tool_id' => 'wc_update', 'completed' => true ];
		}
		throw new Tool_Exception( __( 'WC_Install not available', 'store-mcp' ), Server::ERR_TOOL_FAILED );
	}

	private static function database_tables( $wpdb ): array {
		$db_name = DB_NAME;
		$rows    = $wpdb->get_results( $wpdb->prepare(
			"SELECT table_name, data_length + index_length AS size, table_rows FROM information_schema.tables WHERE table_schema = %s ORDER BY size DESC",
			$db_name
		) );

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[] = [
				'name'    => (string) $row->table_name,
				'rows'    => (int) $row->table_rows,
				'size_mb' => round( ( (int) $row->size ) / 1024 / 1024, 2 ),
			];
		}
		return $out;
	}
}

add_action( 'store_mcp_register_tools', [ System_Tools::class, 'register' ] );
