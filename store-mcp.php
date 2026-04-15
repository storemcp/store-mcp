<?php
/**
 * Plugin Name:       StoreMCP — AI Control Center
 * Plugin URI:        https://storemcp.io
 * Description:       Universal MCP (Model Context Protocol) server for WordPress and WooCommerce. Control your site from Claude, ChatGPT, Cursor and any MCP-compatible client.
 * Version:           1.1.2
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            StoreMCP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       store-mcp
 * Domain Path:       /languages
 *
 * @package StoreMCP
 */

defined( 'ABSPATH' ) || exit;

define( 'STORE_MCP_VERSION', '1.1.2' );
define( 'STORE_MCP_FILE', __FILE__ );
define( 'STORE_MCP_PATH', plugin_dir_path( __FILE__ ) );
define( 'STORE_MCP_URL', plugin_dir_url( __FILE__ ) );
define( 'STORE_MCP_BASENAME', plugin_basename( __FILE__ ) );
define( 'STORE_MCP_REST_NAMESPACE', 'store-mcp/v1' );
define( 'STORE_MCP_DB_VERSION', '1.1.0' );
define( 'STORE_MCP_LICENSE_API', 'https://storemcp.io/api/license' );
define( 'STORE_MCP_UPDATE_API', 'https://storemcp.io/api/update' );
define( 'STORE_MCP_PROTOCOL_VERSION', '2025-03-26' );

require_once STORE_MCP_PATH . 'includes/class-store-mcp.php';

register_activation_hook( __FILE__, [ 'StoreMCP\\Plugin', 'on_activate' ] );
register_deactivation_hook( __FILE__, [ 'StoreMCP\\Plugin', 'on_deactivate' ] );

add_action( 'plugins_loaded', [ 'StoreMCP\\Plugin', 'instance' ], 5 );
