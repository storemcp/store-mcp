# StoreMCP — AI Control Center

[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress tested](https://img.shields.io/badge/WordPress-6.4%2B-21759b.svg)](https://wordpress.org/)
[![PHP version](https://img.shields.io/badge/PHP-8.0%2B-777bb3.svg)](https://www.php.net/)
[![MCP protocol](https://img.shields.io/badge/MCP-2025--03--26-7c3aed.svg)](https://modelcontextprotocol.io/)
[![Release](https://img.shields.io/badge/release-1.1.1-success.svg)](./CHANGELOG.md)

Universal **MCP (Model Context Protocol)** server for WordPress and WooCommerce.
Control your site from Claude, ChatGPT, Cursor and any MCP-compatible client.

Website: [storemcp.io](https://storemcp.io)

## What is StoreMCP?

StoreMCP turns your WordPress site and WooCommerce store into an MCP server,
so any compatible AI client (Claude.ai, Claude Desktop, ChatGPT, Cursor,
Windsurf, Zed, custom agents via the Anthropic or OpenAI SDK…) can manage
your content and your store using natural language.

The **Model Context Protocol** is Anthropic's open standard for connecting
AI assistants to data sources. With a single MCP connection you can:

- Skip writing brittle REST integrations for every tool.
- Get typed, self-describing tools that AI clients can call safely.
- Keep your data on your own server — no third-party brokers.

## Features

- **Streamable HTTP MCP transport** (revision `2025-03-26`) at
  `/wp-json/store-mcp/v1/mcp`, with a fallback legacy SSE endpoint.
- **Three auth methods**: per-site API keys (hashed with `wp_hash_password`),
  WordPress Application Passwords, and a full OAuth 2.1 authorization server
  (PKCE-S256, Dynamic Client Registration, refresh tokens).
- **137 tools across 22 modules** covering WordPress core (pages, posts, media,
  menus, widgets, users, SEO) and WooCommerce (products, orders, customers,
  coupons, variations, shipping, tax, refunds, webhooks, reviews, reports).
- **8 MCP resources** for passive context (site info, active plugins, recent
  posts, drafts, scheduled, store stats, low-stock, pending orders).
- **Admin panel** with dashboard, settings, per-module toggles, activity log
  with CSV export, license manager.
- **Rate limiting**, **CORS allow-list**, **redacted activity log**,
  **HPOS-compatible** order queries, **multisite-aware**.
- **Translation-ready** (English + Spanish bundled).

## Installation

### From WordPress.org (recommended)

1. Go to **Plugins → Add New** in WordPress admin.
2. Search for "StoreMCP".
3. Click **Install Now**, then **Activate**.

### From the release zip

1. Download the latest `store-mcp-x.y.z.zip` from the
   [releases page](https://github.com/storemcp/store-mcp/releases) or from
   [storemcp.io](https://storemcp.io).
2. **Plugins → Add New → Upload Plugin** → select the zip → **Install Now** →
   **Activate**.

### From source

```bash
git clone https://github.com/storemcp/store-mcp.git wp-content/plugins/store-mcp
```

Then activate the plugin from WordPress admin.

## Usage

After activation:

1. Go to **StoreMCP → Settings**, click **Generate API key** and copy the key
   once — it is only shown at creation time.
2. Copy the MCP endpoint URL shown on the Dashboard
   (`https://<your-site>/wp-json/store-mcp/v1/mcp`).
3. Add it to your MCP client.

### Claude Desktop / Claude Code / Cursor

```json
{
  "mcpServers": {
    "my-wordpress-site": {
      "url": "https://example.com/wp-json/store-mcp/v1/mcp",
      "headers": {
        "Authorization": "Bearer smcp_xxxxxxxxxxxx"
      }
    }
  }
}
```

### Claude.ai (web)

Go to **Settings → Connectors → Add MCP Server**, paste the URL and key.

## Free vs Pro

This repository contains the **full, functional plugin** under GPL-2.0. The free
tier is fully usable on its own: WordPress core CRUD, WooCommerce read access,
30 req/min and one connected site.

An optional paid license at [storemcp.io](https://storemcp.io) unlocks the
WooCommerce write tools, advanced reports, plugin/theme management, higher
rate limits and multi-site support. License verification is a single HTTPS
call to `storemcp.io/api/license` with your license key and site URL — no
telemetry, no third-party brokers.

## Architecture

For a technical walk-through of the boot flow, transports, tool dispatch,
permissioning, storage and extension hooks, see
[ARCHITECTURE.md](./ARCHITECTURE.md).

Quick pointers:

- Plugin bootstrap: [store-mcp.php](./store-mcp.php)
- Main singleton: [includes/class-store-mcp.php](./includes/class-store-mcp.php)
- JSON-RPC dispatcher: [includes/class-mcp-server.php](./includes/class-mcp-server.php)
- Auth layer: [includes/class-mcp-auth.php](./includes/class-mcp-auth.php)
- OAuth 2.1 server: [includes/class-mcp-oauth.php](./includes/class-mcp-oauth.php)
- Tools: [includes/tools/](./includes/tools)
- Resources: [includes/resources/](./includes/resources)
- Admin UI: [admin/](./admin)

## Extending

StoreMCP exposes six hooks for third-party integration:

| Hook | Type | Purpose |
| --- | --- | --- |
| `store_mcp_register_tools` | action | Register your own tools. |
| `store_mcp_register_resources` | action | Register your own resources. |
| `store_mcp_auth_context` | filter | Rewrap or augment the `AuthContext`. |
| `store_mcp_oauth_authenticate` | filter | Return an `AuthContext` for a custom OAuth bearer. |
| `store_mcp_server_name` | filter | Override the name returned in `initialize`. |
| `store_mcp_flush_cache` | action | Fires after the cache-flush tool runs. |

Minimal tool example:

```php
add_action( 'store_mcp_register_tools', function ( $registry ) {
    $registry->register( [
        'name'        => 'my_plugin_hello',
        'description' => 'Say hello.',
        'tier'        => 'free',
        'module'      => 'my-plugin',
        'inputSchema' => [ 'type' => 'object' ],
    ], function ( array $args, $ctx ) {
        return [ 'message' => 'Hello, ' . $ctx->user_id ];
    } );
} );
```

See [ARCHITECTURE.md](./ARCHITECTURE.md#registering-a-tool) for a full example.

## Building from source

```bash
./build-zip.sh               # produces dist/store-mcp-<version>.zip
./build-screenshots.sh       # renders wordpress.org screenshots from HTML mockups
```

## Contributing

Contributions are welcome! See [CONTRIBUTING.md](./CONTRIBUTING.md) for setup,
coding style and PR guidelines. Please read our
[Code of Conduct](./CODE_OF_CONDUCT.md) before participating.

## Security

If you discover a security vulnerability, please do **not** open a public issue.
See [SECURITY.md](./SECURITY.md) for responsible disclosure instructions.

## License

GPL-2.0-or-later. See [LICENSE](./LICENSE).

WordPress and WooCommerce are trademarks of their respective owners. StoreMCP
is not affiliated with, endorsed by, or sponsored by the WordPress Foundation,
Automattic, Anthropic, OpenAI, or any other referenced trademark holder.
