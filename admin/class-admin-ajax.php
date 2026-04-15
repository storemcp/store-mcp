<?php
/**
 * AJAX handlers for the admin panel.
 *
 * Every handler validates the `store_mcp_admin` nonce + manage_options cap.
 *
 * @package StoreMCP\Admin
 */

namespace StoreMCP\Admin;

use StoreMCP\Plugin;

defined( 'ABSPATH' ) || exit;

final class Admin_Ajax {

	const ACTIONS = [
		'generate_key',
		'revoke_key',
		'delete_key',
		'save_settings',
		'toggle_module',
		'activate_license',
		'deactivate_license',
		'recheck_license',
		'clear_log',
		'save_white_label',
		'test_connection',
		'revoke_client',
	];

	public function __construct() {
		foreach ( self::ACTIONS as $action ) {
			add_action( 'wp_ajax_store_mcp_' . $action, [ $this, $action ] );
		}
	}

	private function guard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden', 'store-mcp' ) ], 403 );
		}
		check_ajax_referer( 'store_mcp_admin', 'nonce' );
	}

	public function generate_key(): void {
		$this->guard();

		$label   = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : get_current_user_id();

		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$result = Plugin::instance()->auth->generate_api_key( $user_id, $label );

		wp_send_json_success( [
			'message'   => __( 'API key generated. Copy it now — it will not be shown again.', 'store-mcp' ),
			'key'       => $result['plain_key'],
			'key_id'    => $result['key_id'],
			'id'        => $result['id'],
			'created_at'=> $result['created_at'],
			'label'     => $label,
			'user_id'   => $user_id,
		] );
	}

	public function revoke_key(): void {
		$this->guard();
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid id', 'store-mcp' ) ], 400 );
		}
		$ok = Plugin::instance()->auth->revoke_api_key( $id );
		if ( ! $ok ) {
			wp_send_json_error( [ 'message' => __( 'Could not revoke key', 'store-mcp' ) ], 500 );
		}
		wp_send_json_success( [ 'message' => __( 'Key revoked', 'store-mcp' ) ] );
	}

	public function delete_key(): void {
		$this->guard();
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid id', 'store-mcp' ) ], 400 );
		}
		Plugin::instance()->auth->delete_api_key( $id );
		wp_send_json_success( [ 'message' => __( 'Key deleted', 'store-mcp' ) ] );
	}

	public function save_settings(): void {
		$this->guard();

		$enabled        = isset( $_POST['enabled'] ) ? (bool) $_POST['enabled'] : false;
		$rate_free      = isset( $_POST['rate_limit_free'] ) ? max( 1, (int) $_POST['rate_limit_free'] ) : 30;
		$rate_pro       = isset( $_POST['rate_limit_pro'] ) ? max( 1, (int) $_POST['rate_limit_pro'] ) : 120;
		$retention      = isset( $_POST['log_retention_days'] ) ? max( 1, (int) $_POST['log_retention_days'] ) : 30;
		$cache_ttl      = isset( $_POST['cache_ttl_seconds'] ) ? max( 0, (int) $_POST['cache_ttl_seconds'] ) : 300;
		$allow_app_pwd  = isset( $_POST['allow_app_passwords'] ) ? (bool) $_POST['allow_app_passwords'] : false;
		$oauth_cap_raw  = isset( $_POST['oauth_min_capability'] ) ? sanitize_key( wp_unslash( $_POST['oauth_min_capability'] ) ) : 'manage_options';
		$oauth_cap      = $oauth_cap_raw !== '' ? $oauth_cap_raw : 'manage_options';

		$cors_raw = isset( $_POST['cors_allowed'] ) ? (string) wp_unslash( $_POST['cors_allowed'] ) : '';
		$cors     = array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $cors_raw ) ?: [] ) ) );
		$cors     = array_map( static fn( $o ) => '*' === $o ? '*' : esc_url_raw( $o ), $cors );
		$cors     = array_values( array_filter( $cors ) );

		update_option( 'store_mcp_enabled', $enabled, false );
		update_option( 'store_mcp_rate_limit_free', $rate_free, false );
		update_option( 'store_mcp_rate_limit_pro', $rate_pro, false );
		update_option( 'store_mcp_log_retention_days', $retention, false );
		update_option( 'store_mcp_cache_ttl_seconds', $cache_ttl, false );
		update_option( 'store_mcp_allow_app_passwords', $allow_app_pwd, false );
		update_option( 'store_mcp_cors_allowed', $cors, false );
		update_option( 'store_mcp_oauth_min_capability', $oauth_cap, false );

		wp_send_json_success( [ 'message' => __( 'Settings saved', 'store-mcp' ) ] );
	}

	public function revoke_client(): void {
		$this->guard();
		$client_id = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
		if ( '' === $client_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing client_id', 'store-mcp' ) ], 400 );
		}
		$ok = Plugin::instance()->oauth->revoke_client( $client_id );
		if ( ! $ok ) {
			wp_send_json_error( [ 'message' => __( 'Could not revoke app', 'store-mcp' ) ], 500 );
		}
		wp_send_json_success( [ 'message' => __( 'App disconnected', 'store-mcp' ) ] );
	}

	public function toggle_module(): void {
		$this->guard();

		$module  = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
		$enabled = isset( $_POST['enabled'] ) ? (bool) $_POST['enabled'] : true;

		if ( '' === $module ) {
			wp_send_json_error( [ 'message' => __( 'Invalid module', 'store-mcp' ) ], 400 );
		}

		$disabled = (array) get_option( 'store_mcp_disabled_modules', [] );
		if ( $enabled ) {
			$disabled = array_values( array_diff( $disabled, [ $module ] ) );
		} elseif ( ! in_array( $module, $disabled, true ) ) {
			$disabled[] = $module;
		}

		update_option( 'store_mcp_disabled_modules', $disabled, false );
		wp_send_json_success( [ 'message' => __( 'Module updated', 'store-mcp' ), 'disabled' => $disabled ] );
	}

	public function activate_license(): void {
		$this->guard();
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		$result = Plugin::instance()->license->activate( $key );
		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		}
		wp_send_json_error( $result, 400 );
	}

	public function deactivate_license(): void {
		$this->guard();
		$result = Plugin::instance()->license->deactivate();
		wp_send_json_success( $result );
	}

	public function recheck_license(): void {
		$this->guard();
		$result = Plugin::instance()->license->verify_remote( true );
		wp_send_json_success( $result );
	}

	public function clear_log(): void {
		$this->guard();
		$days = isset( $_POST['days'] ) ? max( 0, (int) $_POST['days'] ) : 0;
		if ( 0 === $days ) {
			global $wpdb;
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}storemcp_activity" );
			wp_send_json_success( [ 'message' => __( 'Log cleared', 'store-mcp' ) ] );
		}
		$deleted = Plugin::instance()->activity_log->prune( $days );
		wp_send_json_success( [ 'message' => sprintf( /* translators: %d: rows */ __( '%d entries removed', 'store-mcp' ), $deleted ) ] );
	}

	public function save_white_label(): void {
		$this->guard();
		if ( ! Plugin::instance()->license->is_agency() ) {
			wp_send_json_error( [ 'message' => __( 'Agency plan required', 'store-mcp' ) ], 403 );
		}

		$data = [
			'name' => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'logo' => isset( $_POST['logo'] ) ? esc_url_raw( wp_unslash( $_POST['logo'] ) ) : '',
			'icon' => isset( $_POST['icon'] ) ? esc_url_raw( wp_unslash( $_POST['icon'] ) ) : '',
			'support_url' => isset( $_POST['support_url'] ) ? esc_url_raw( wp_unslash( $_POST['support_url'] ) ) : '',
		];
		update_option( 'store_mcp_white_label', $data, false );
		wp_send_json_success( [ 'message' => __( 'White label saved', 'store-mcp' ) ] );
	}

	public function test_connection(): void {
		$this->guard();

		$response = wp_remote_post( rest_url( STORE_MCP_REST_NAMESPACE . '/info' ), [
			'timeout' => 5,
			'method'  => 'GET',
		] );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'message' => $response->get_error_message() ], 500 );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		wp_send_json_success( [
			'message' => __( 'MCP endpoint reachable', 'store-mcp' ),
			'info'    => is_array( $body ) ? $body : [],
		] );
	}
}
