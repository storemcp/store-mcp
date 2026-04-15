<?php
/**
 * License manager.
 *
 * Talks to the storemcp.io license API, which proxies Lemon Squeezy:
 *   POST /api/license/activate    → registers this site as an "instance",
 *                                    returns instance_id.
 *   POST /api/license/validate    → checks the current state of the license
 *                                    (optionally verifying the instance).
 *   POST /api/license/deactivate  → removes this site from the license's
 *                                    active instances.
 *
 * All three routes return a normalized shape:
 *   { valid, tier, expires_at, sites_used, sites_max, customer, instance_id, message }
 *
 * If no license is present, the plugin operates as FREE. Daily cron re-checks
 * active licenses; on failure the last known state is preserved.
 *
 * @package StoreMCP
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class License {

	const TIER_FREE   = 'free';
	const TIER_PRO    = 'pro';
	const TIER_AGENCY = 'agency';

	const STATE_OPTION = 'store_mcp_license_state';
	const KEY_OPTION   = 'store_mcp_license_key';

	public function tier(): string {
		$state = $this->state();
		if ( ! $state || empty( $state['valid'] ) ) {
			return self::TIER_FREE;
		}
		$tier = $state['tier'] ?? self::TIER_FREE;
		return in_array( $tier, [ self::TIER_FREE, self::TIER_PRO, self::TIER_AGENCY ], true ) ? $tier : self::TIER_FREE;
	}

	public function is_pro(): bool {
		return in_array( $this->tier(), [ self::TIER_PRO, self::TIER_AGENCY ], true );
	}

	public function is_agency(): bool {
		return self::TIER_AGENCY === $this->tier();
	}

	public function key(): string {
		return (string) get_option( self::KEY_OPTION, '' );
	}

	public function state(): array {
		$state = get_option( self::STATE_OPTION, [] );
		return is_array( $state ) ? $state : [];
	}

	public function instance_id(): string {
		$state = $this->state();
		return isset( $state['instance_id'] ) ? (string) $state['instance_id'] : '';
	}

	/**
	 * Activate a license key. Calls /activate, stores the key and instance_id
	 * on success. Call this from the UI when the user submits a new key.
	 */
	public function activate( string $license_key ): array {
		$license_key = trim( sanitize_text_field( $license_key ) );
		if ( '' === $license_key ) {
			return [ 'success' => false, 'message' => __( 'License key is empty', 'store-mcp' ) ];
		}

		$response = $this->remote_request( 'activate', [
			'license_key' => $license_key,
			'site_url'    => home_url(),
		] );

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'message' => $response->get_error_message() ];
		}

		$state = $this->store_state( $response );

		if ( empty( $state['valid'] ) ) {
			delete_option( self::KEY_OPTION );
			return [ 'success' => false, 'message' => $state['message'] ?: __( 'License invalid', 'store-mcp' ), 'tier' => self::TIER_FREE ];
		}

		update_option( self::KEY_OPTION, $license_key, false );

		return [
			'success' => true,
			'message' => $state['message'] ?: __( 'License active', 'store-mcp' ),
			'tier'    => $state['tier'],
		];
	}

	/**
	 * Deactivate this site's instance on the remote server, then clear local state.
	 */
	public function deactivate(): array {
		$key         = $this->key();
		$instance_id = $this->instance_id();

		if ( '' !== $key && '' !== $instance_id ) {
			$this->remote_request( 'deactivate', [
				'license_key' => $key,
				'instance_id' => $instance_id,
			] );
		}

		delete_option( self::KEY_OPTION );
		update_option( self::STATE_OPTION, [
			'valid'      => false,
			'tier'       => self::TIER_FREE,
			'checked_at' => time(),
		], false );

		return [ 'success' => true, 'message' => __( 'License deactivated', 'store-mcp' ) ];
	}

	/**
	 * Re-verify the current license against the remote. Preserves the last
	 * known state on network errors to avoid flapping.
	 */
	public function verify_remote( bool $force = false ): array {
		unset( $force );

		$key         = $this->key();
		$instance_id = $this->instance_id();

		if ( '' === $key ) {
			$state = [
				'valid'      => false,
				'tier'       => self::TIER_FREE,
				'checked_at' => time(),
				'message'    => __( 'No license configured', 'store-mcp' ),
			];
			update_option( self::STATE_OPTION, $state, false );
			return [ 'success' => true, 'message' => $state['message'], 'tier' => self::TIER_FREE ];
		}

		$body = [ 'license_key' => $key ];
		if ( '' !== $instance_id ) {
			$body['instance_id'] = $instance_id;
		}

		$response = $this->remote_request( 'validate', $body );

		if ( is_wp_error( $response ) ) {
			$state = $this->state();
			$state['checked_at'] = time();
			$state['last_error'] = $response->get_error_message();
			update_option( self::STATE_OPTION, $state, false );
			return [ 'success' => false, 'message' => $response->get_error_message(), 'tier' => $this->tier() ];
		}

		$state = $this->store_state( $response );

		return [
			'success' => $state['valid'],
			'message' => $state['message'] ?: ( $state['valid'] ? __( 'License active', 'store-mcp' ) : __( 'License invalid', 'store-mcp' ) ),
			'tier'    => $state['tier'],
		];
	}

	/**
	 * Build the full state array from a normalized API response, preserving
	 * the existing instance_id when the new response omits it (LS validate
	 * does not echo the instance).
	 */
	private function store_state( array $response ): array {
		$existing = $this->state();

		$instance_id = '';
		if ( ! empty( $response['instance_id'] ) ) {
			$instance_id = (string) $response['instance_id'];
		} elseif ( ! empty( $existing['instance_id'] ) ) {
			$instance_id = (string) $existing['instance_id'];
		}

		$state = [
			'valid'       => ! empty( $response['valid'] ),
			'tier'        => isset( $response['tier'] ) ? (string) $response['tier'] : self::TIER_FREE,
			'expires_at'  => isset( $response['expires_at'] ) ? (string) $response['expires_at'] : '',
			'sites_used'  => isset( $response['sites_used'] ) ? (int) $response['sites_used'] : 0,
			'sites_max'   => isset( $response['sites_max'] ) ? (int) $response['sites_max'] : 1,
			'customer'    => isset( $response['customer'] ) ? (string) $response['customer'] : '',
			'instance_id' => $instance_id,
			'checked_at'  => time(),
			'message'     => isset( $response['message'] ) ? (string) $response['message'] : '',
		];

		update_option( self::STATE_OPTION, $state, false );
		return $state;
	}

	private function remote_request( string $endpoint, array $args ) {
		$url = trailingslashit( STORE_MCP_LICENSE_API ) . $endpoint;

		$response = wp_remote_post( $url, [
			'timeout'   => 15,
			'body'      => wp_json_encode( $args ),
			'headers'   => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'User-Agent'   => 'StoreMCP/' . STORE_MCP_VERSION . '; ' . home_url(),
			],
			'sslverify' => true,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'store_mcp_license_http', __( 'License server returned an invalid response', 'store-mcp' ) );
		}

		return $data;
	}
}
