<?php
/**
 * Admin pages — registers menu, dispatches views, enqueues assets.
 *
 * @package StoreMCP\Admin
 */

namespace StoreMCP\Admin;

use StoreMCP\Plugin;
use StoreMCP\License;

defined( 'ABSPATH' ) || exit;

final class Admin_Page {

	const SLUG       = 'store-mcp';
	const CAPABILITY = 'manage_options';

	private array $pages = [];

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_store_mcp_export_log', [ $this, 'handle_export_log' ] );
		add_action( 'admin_notices', [ $this, 'maybe_show_db_notice' ] );
		add_filter( 'plugin_action_links_' . STORE_MCP_BASENAME, [ $this, 'plugin_action_links' ] );
	}

	public function register_menu(): void {
		$white_label = (array) get_option( 'store_mcp_white_label', [] );
		$is_agency   = Plugin::instance()->license->is_agency();

		$menu_title = ( $is_agency && ! empty( $white_label['name'] ) )
			? sanitize_text_field( $white_label['name'] )
			: 'StoreMCP';

		$icon = ( $is_agency && ! empty( $white_label['icon'] ) )
			? esc_url_raw( $white_label['icon'] )
			: 'dashicons-rest-api';

		add_menu_page(
			$menu_title,
			$menu_title,
			self::CAPABILITY,
			self::SLUG,
			[ $this, 'render_dashboard' ],
			$icon,
			58
		);

		$this->pages[] = add_submenu_page( self::SLUG, __( 'Dashboard', 'store-mcp' ), __( 'Dashboard', 'store-mcp' ), self::CAPABILITY, self::SLUG, [ $this, 'render_dashboard' ] );
		$this->pages[] = add_submenu_page( self::SLUG, __( 'Settings', 'store-mcp' ), __( 'Settings', 'store-mcp' ), self::CAPABILITY, self::SLUG . '-settings', [ $this, 'render_settings' ] );
		$this->pages[] = add_submenu_page( self::SLUG, __( 'Tools', 'store-mcp' ), __( 'Tools', 'store-mcp' ), self::CAPABILITY, self::SLUG . '-tools', [ $this, 'render_tools' ] );
		$this->pages[] = add_submenu_page( self::SLUG, __( 'Activity Log', 'store-mcp' ), __( 'Activity Log', 'store-mcp' ), self::CAPABILITY, self::SLUG . '-log', [ $this, 'render_log' ] );
		$this->pages[] = add_submenu_page( self::SLUG, __( 'License', 'store-mcp' ), __( 'License', 'store-mcp' ), self::CAPABILITY, self::SLUG . '-license', [ $this, 'render_license' ] );

		if ( $is_agency ) {
			$this->pages[] = add_submenu_page( self::SLUG, __( 'White Label', 'store-mcp' ), __( 'White Label', 'store-mcp' ), self::CAPABILITY, self::SLUG . '-white-label', [ $this, 'render_white_label' ] );
		}
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, $this->pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'store-mcp-admin',
			STORE_MCP_URL . 'admin/css/admin-style.css',
			[],
			STORE_MCP_VERSION
		);

		wp_enqueue_script(
			'store-mcp-admin',
			STORE_MCP_URL . 'admin/js/admin-script.js',
			[ 'jquery', 'wp-i18n' ],
			STORE_MCP_VERSION,
			true
		);

		wp_localize_script( 'store-mcp-admin', 'StoreMCPAdmin', [
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'store_mcp_admin' ),
			'restEndpoint' => rest_url( STORE_MCP_REST_NAMESPACE . '/mcp' ),
			'sseEndpoint'  => rest_url( STORE_MCP_REST_NAMESPACE . '/sse' ),
			'i18n'         => [
				'confirmRevoke'       => __( 'Revoke this API key? Clients using it will stop working.', 'store-mcp' ),
				'confirmDelete'       => __( 'Permanently delete this key?', 'store-mcp' ),
				'confirmClearLog'     => __( 'Delete all activity log entries?', 'store-mcp' ),
				'confirmRevokeClient' => __( 'Disconnect this app? It will need to authorize again to reconnect.', 'store-mcp' ),
				'copied'              => __( 'Copied', 'store-mcp' ),
				'saved'               => __( 'Saved', 'store-mcp' ),
				'error'               => __( 'Something went wrong', 'store-mcp' ),
				'newKeyWarning'       => __( 'Copy this key now — it will not be shown again.', 'store-mcp' ),
				'showSteps'           => __( 'Show steps', 'store-mcp' ),
				'hideSteps'           => __( 'Hide steps', 'store-mcp' ),
			],
		] );
	}

	public function render_dashboard(): void {
		$this->render_view( 'dashboard' );
	}

	public function render_settings(): void {
		$this->render_view( 'settings' );
	}

	public function render_tools(): void {
		$this->render_view( 'tools' );
	}

	public function render_log(): void {
		$this->render_view( 'activity-log' );
	}

	public function render_license(): void {
		$this->render_view( 'license' );
	}

	public function render_white_label(): void {
		if ( ! Plugin::instance()->license->is_agency() ) {
			wp_die( esc_html__( 'White Label requires the Agency plan.', 'store-mcp' ) );
		}
		$this->render_view( 'white-label' );
	}

	private function render_view( string $name ): void {
		$path = STORE_MCP_PATH . 'admin/views/' . $name . '.php';
		if ( ! file_exists( $path ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'StoreMCP', 'store-mcp' ) . '</h1><p>' . esc_html__( 'View not found.', 'store-mcp' ) . '</p></div>';
			return;
		}
		$plugin = Plugin::instance();
		include $path;
	}

	public function handle_export_log(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Forbidden', 'store-mcp' ), 403 );
		}
		check_admin_referer( 'store_mcp_export_log' );

		$args = [
			'tool'      => isset( $_GET['tool'] ) ? sanitize_text_field( wp_unslash( $_GET['tool'] ) ) : '',
			'status'    => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'search'    => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
		];

		$csv = Plugin::instance()->activity_log->export_csv( $args );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=storemcp-activity-' . gmdate( 'Ymd-His' ) . '.csv' );
		header( 'Content-Length: ' . strlen( $csv ) );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function maybe_show_db_notice(): void {
		if ( get_option( 'store_mcp_db_version' ) !== STORE_MCP_DB_VERSION ) {
			$screen = get_current_screen();
			if ( $screen && false !== strpos( (string) $screen->id, 'store-mcp' ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'StoreMCP database is out of date. Please deactivate and reactivate the plugin.', 'store-mcp' ) . '</p></div>';
			}
		}
	}

	public function plugin_action_links( array $links ): array {
		$custom = [
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Dashboard', 'store-mcp' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG . '-settings' ) ) . '">' . esc_html__( 'Settings', 'store-mcp' ) . '</a>',
		];
		if ( ! Plugin::instance()->license->is_pro() ) {
			$custom[] = '<a href="https://storemcp.io/pricing" target="_blank" style="color:#d63638;font-weight:600;">' . esc_html__( 'Upgrade to Pro', 'store-mcp' ) . '</a>';
		}
		return array_merge( $custom, $links );
	}
}
