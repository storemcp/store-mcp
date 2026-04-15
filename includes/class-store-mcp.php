<?php
/**
 * Main plugin singleton.
 *
 * @package StoreMCP
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	public Server $server;
	public Auth $auth;
	public OAuth $oauth;
	public License $license;
	public Tools_Registry $tools;
	public Resources_Registry $resources;
	public Rate_Limiter $rate_limiter;
	public Activity_Log $activity_log;
	public ?Updater $updater = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function boot(): void {
		$this->load_dependencies();

		Installer::maybe_upgrade();

		$this->license      = new License();
		$this->activity_log = new Activity_Log();
		$this->rate_limiter = new Rate_Limiter();
		$this->oauth        = new OAuth();
		$this->auth         = new Auth();
		$this->tools        = new Tools_Registry( $this->license );
		$this->resources    = new Resources_Registry( $this->license );
		$this->server       = new Server( $this->auth, $this->tools, $this->resources, $this->rate_limiter, $this->activity_log );

		$this->oauth->bootstrap();

		if ( class_exists( __NAMESPACE__ . '\\Updater' ) ) {
			$this->updater = new Updater();
			$this->updater->bootstrap();
		}

		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'rest_api_init', [ $this->server, 'register_routes' ] );
		add_action( 'init', [ $this->tools, 'bootstrap' ], 20 );
		add_action( 'init', [ $this->resources, 'bootstrap' ], 20 );

		if ( is_admin() ) {
			$admin_page = STORE_MCP_PATH . 'admin/class-admin-page.php';
			$admin_ajax = STORE_MCP_PATH . 'admin/class-admin-ajax.php';
			if ( file_exists( $admin_page ) ) {
				require_once $admin_page;
			}
			if ( file_exists( $admin_ajax ) ) {
				require_once $admin_ajax;
			}
			if ( class_exists( __NAMESPACE__ . '\\Admin\\Admin_Page' ) ) {
				new Admin\Admin_Page();
			}
			if ( class_exists( __NAMESPACE__ . '\\Admin\\Admin_Ajax' ) ) {
				new Admin\Admin_Ajax();
			}
		}

		Maintenance::schedule();
	}

	private function load_dependencies(): void {
		$base = STORE_MCP_PATH . 'includes/';

		require_once $base . 'class-mcp-license.php';
		require_once $base . 'class-mcp-activity-log.php';
		require_once $base . 'class-mcp-rate-limiter.php';
		require_once $base . 'class-mcp-permissions.php';
		require_once $base . 'class-mcp-oauth.php';
		require_once $base . 'class-mcp-auth.php';
		require_once $base . 'class-mcp-tools-registry.php';
		require_once $base . 'class-mcp-resources-registry.php';
		require_once $base . 'class-mcp-server.php';
		require_once $base . 'class-mcp-installer.php';
		require_once $base . 'class-mcp-maintenance.php';

		// Optional: present in self-hosted builds (storemcp.io), removed from wordpress.org builds.
		$updater_file = $base . 'class-mcp-updater.php';
		if ( file_exists( $updater_file ) ) {
			require_once $updater_file;
		}
		require_once $base . 'tools/trait-tool-base.php';
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'store-mcp', false, dirname( STORE_MCP_BASENAME ) . '/languages' );
	}

	public static function on_activate(): void {
		require_once STORE_MCP_PATH . 'includes/class-mcp-activity-log.php';
		require_once STORE_MCP_PATH . 'includes/class-mcp-installer.php';
		require_once STORE_MCP_PATH . 'includes/class-mcp-maintenance.php';
		Installer::install();
		Maintenance::schedule();
		flush_rewrite_rules();
	}

	public static function on_deactivate(): void {
		require_once STORE_MCP_PATH . 'includes/class-mcp-maintenance.php';
		Maintenance::unschedule();
		flush_rewrite_rules();
	}
}
