<?php
/**
 * Self-hosted updater.
 *
 * Lets the plugin receive updates directly from storemcp.io when it is
 * installed from the official website (instead of wordpress.org).
 *
 * Design:
 *  - Filters `pre_set_site_transient_update_plugins` to inject an update
 *    entry when the remote version is greater than the installed version.
 *  - If an entry for this plugin already exists in the transient (e.g. the
 *    plugin was installed from wordpress.org and .org offers an update), we
 *    do NOT overwrite it — the .org update wins, by design.
 *  - Filters `plugins_api` to supply the "View details" popup.
 *  - Response cached for 12 hours via transient to avoid hammering the API.
 *
 * Remote endpoint contract (GET STORE_MCP_UPDATE_API):
 *    {
 *      "version": "1.1.2",
 *      "tested": "6.7",
 *      "requires": "6.4",
 *      "requires_php": "8.0",
 *      "download_url": "https://storemcp.io/downloads/store-mcp-1.1.2.zip",
 *      "details_url": "https://storemcp.io/changelog",
 *      "last_updated": "2026-04-15",
 *      "sections": { "description": "...", "changelog": "..." },
 *      "banners": { "low": "...", "high": "..." },
 *      "icons":   { "1x": "...", "2x": "..." }
 *    }
 *
 * @package StoreMCP
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Updater {

	const CACHE_KEY = 'store_mcp_update_info';
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	public function bootstrap(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugins_api' ], 10, 3 );
		add_action( 'upgrader_process_complete', [ $this, 'flush_cache' ], 10, 0 );
	}

	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$basename = STORE_MCP_BASENAME;

		if ( isset( $transient->response[ $basename ] ) ) {
			return $transient;
		}

		$info = $this->fetch();
		if ( ! $info || empty( $info['version'] ) ) {
			return $transient;
		}

		if ( version_compare( $info['version'], STORE_MCP_VERSION, '<=' ) ) {
			if ( ! isset( $transient->no_update ) ) {
				$transient->no_update = [];
			}
			$transient->no_update[ $basename ] = $this->to_object( $info, $basename );
			return $transient;
		}

		if ( ! isset( $transient->response ) ) {
			$transient->response = [];
		}
		$transient->response[ $basename ] = $this->to_object( $info, $basename );

		return $transient;
	}

	public function plugins_api( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( ! isset( $args->slug ) || 'store-mcp' !== $args->slug ) {
			return $result;
		}

		$info = $this->fetch();
		if ( ! $info ) {
			return $result;
		}

		$data = new \stdClass();
		$data->name           = 'StoreMCP — AI Control Center';
		$data->slug           = 'store-mcp';
		$data->version        = $info['version'] ?? STORE_MCP_VERSION;
		$data->author         = '<a href="https://storemcp.io">StoreMCP</a>';
		$data->homepage       = 'https://storemcp.io';
		$data->requires       = $info['requires'] ?? '6.4';
		$data->tested         = $info['tested'] ?? '6.7';
		$data->requires_php   = $info['requires_php'] ?? '8.0';
		$data->last_updated   = $info['last_updated'] ?? '';
		$data->download_link  = $info['download_url'] ?? '';
		$data->trunk          = $info['download_url'] ?? '';
		$data->sections       = isset( $info['sections'] ) && is_array( $info['sections'] )
			? array_map( 'wp_kses_post', $info['sections'] )
			: [ 'description' => esc_html__( 'Universal MCP server for WordPress and WooCommerce.', 'store-mcp' ) ];
		if ( ! empty( $info['banners'] ) ) {
			$data->banners = $info['banners'];
		}
		if ( ! empty( $info['icons'] ) ) {
			$data->icons = $info['icons'];
		}

		return $data;
	}

	public function flush_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	private function to_object( array $info, string $basename ): \stdClass {
		$obj = new \stdClass();
		$obj->id           = 'storemcp/' . $basename;
		$obj->slug         = 'store-mcp';
		$obj->plugin       = $basename;
		$obj->new_version  = (string) ( $info['version'] ?? STORE_MCP_VERSION );
		$obj->url          = (string) ( $info['details_url'] ?? 'https://storemcp.io' );
		$obj->package      = (string) ( $info['download_url'] ?? '' );
		$obj->tested       = (string) ( $info['tested'] ?? '6.7' );
		$obj->requires     = (string) ( $info['requires'] ?? '6.4' );
		$obj->requires_php = (string) ( $info['requires_php'] ?? '8.0' );
		if ( ! empty( $info['icons'] ) ) {
			$obj->icons = $info['icons'];
		}
		if ( ! empty( $info['banners'] ) ) {
			$obj->banners = $info['banners'];
		}
		return $obj;
	}

	private function fetch(): ?array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url = add_query_arg(
			[
				'slug'    => 'store-mcp',
				'version' => STORE_MCP_VERSION,
				'site'    => rawurlencode( home_url() ),
			],
			STORE_MCP_UPDATE_API
		);

		$response = wp_remote_get( $url, [
			'timeout' => 10,
			'headers' => [
				'Accept'     => 'application/json',
				'User-Agent' => 'StoreMCP-Updater/' . STORE_MCP_VERSION,
			],
		] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) {
			set_transient( self::CACHE_KEY, [], HOUR_IN_SECONDS );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['version'] ) ) {
			set_transient( self::CACHE_KEY, [], HOUR_IN_SECONDS );
			return null;
		}

		set_transient( self::CACHE_KEY, $data, self::CACHE_TTL );
		return $data;
	}
}
