=== StoreMCP — AI Control Center ===
Contributors: storemcp
Tags: mcp, woocommerce, ai, claude, chatgpt
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Expose your WordPress and WooCommerce site as an MCP server for Claude, ChatGPT, Cursor and any MCP-compatible AI client.

== Description ==

**StoreMCP** turns your WordPress site and WooCommerce store into an MCP server, so any compatible AI client (Claude.ai, Claude Desktop, ChatGPT, Cursor, Windsurf, Zed, custom agents via the Anthropic or OpenAI SDK…) can manage your content and your store using natural language.

= What you can do =

* Create, edit, publish and delete posts, pages, media, menus and widgets.
* Manage users, categories, tags and SEO metadata (Yoast and Rank Math compatible).
* Manage WooCommerce products, variations, attributes, categories and tags.
* Read, create and update orders (HPOS-compatible), customers, coupons and reviews.
* Issue refunds, configure shipping zones, tax rates, payment gateways and webhooks.
* Get analytics: sales reports, top sellers, stock-low / out-of-stock lists, daily time-series ready for charts.
* Administer the site itself: list & activate plugins and themes, run system maintenance tools, get a full system status report.
* Expose a live context (site info, recent posts, pending orders, low stock) as MCP resources.

= Why MCP? =

The Model Context Protocol (MCP) is Anthropic's open standard for connecting AI assistants to data sources. With a single MCP connection you can:

* Skip writing brittle REST integrations for every tool.
* Get typed, self-describing tools that AI clients can call safely.
* Keep your data on your own server — no third-party brokers.

= How it works =

StoreMCP exposes a standards-compliant **Streamable HTTP** MCP endpoint at `/wp-json/store-mcp/v1/mcp` (with a fallback SSE endpoint at `/sse`), secured by per-site API keys, WordPress Application Passwords, or an OAuth hook for custom identity providers.

From the admin panel you can:

* Generate and revoke API keys.
* Configure rate limits, CORS, auth methods and cache TTL.
* Enable / disable entire tool modules.
* Audit every single MCP call in the Activity Log (with CSV export).
* Activate your Pro license to unlock the full tool catalogue.

= What's included =

This free plugin includes full WordPress core CRUD (pages, posts, media, menus, widgets, user read access, site info), WooCommerce read access (products, categories, tags, basic order and product totals), 30 requests per minute and 1 connected site.

An optional paid upgrade is available at [storemcp.io](https://storemcp.io) with additional WooCommerce write tools, advanced reports, plugin/theme management, higher rate limits and multi-site support. The upgrade is entirely optional — this free plugin is fully functional on its own.

= Privacy =

StoreMCP does **not** send any site data to external services in Free mode. All MCP traffic goes directly from your AI client to your WordPress REST API. Pro licence verification contacts `https://storemcp.io/api/license` with your license key and site URL only.

== Installation ==

1. Upload the plugin zip to **Plugins → Add New → Upload Plugin** or extract it into `/wp-content/plugins/`.
2. Activate **StoreMCP — AI Control Center**.
3. Go to **StoreMCP → Settings**, click **Generate API key** and copy the key once — it is only shown at creation time.
4. Copy the MCP endpoint URL shown on the Dashboard (`/wp-json/store-mcp/v1/mcp`).
5. Add it to your MCP client. For Claude Desktop / Claude Code / Cursor, your config looks like:

`{
  "mcpServers": {
    "my-wordpress-site": {
      "url": "https://example.com/wp-json/store-mcp/v1/mcp",
      "headers": {
        "Authorization": "Bearer smcp_xxxxxxxxxxxx"
      }
    }
  }
}`

For Claude.ai go to **Settings → Connectors → Add MCP Server**, paste the URL and key.

== Frequently Asked Questions ==

= Does StoreMCP work without WooCommerce? =

Yes. WooCommerce tools are only registered when WooCommerce is active. All WordPress-core tools (pages, posts, media, menus, widgets, users, site info, SEO, plugins, themes) work on any WordPress site.

= Is this HPOS compatible? =

Yes. All order queries go through `wc_get_orders()` and all order reads/writes use `wc_get_order()` / `WC_Order` object methods, which are High-Performance Order Storage compatible.

= How are API keys stored? =

Keys are stored as hashes using `wp_hash_password()`. The raw key is shown only once, at creation. Revoked keys can be deleted at any time.

= What happens if I disable StoreMCP? =

All MCP endpoints return HTTP 503. Your API keys and logs are kept. Deactivate to stop accepting requests; uninstall to wipe all plugin data.

= Which MCP protocol version is supported? =

StoreMCP implements MCP protocol revision **2025-03-26** (Streamable HTTP transport) with a fallback legacy SSE endpoint for older clients.

= Can I disable individual tools? =

Yes. Go to **StoreMCP → Tools** and toggle any module on or off. Disabled modules are not loaded, reducing attack surface.

= Is my order data sent anywhere? =

Never. All data stays on your server. AI clients call your REST API directly.

= Does it support multisite? =

Yes. The plugin is multisite-aware; Application Passwords work per-user, and API keys are generated per-user on the subsite where they are created.

= How do rate limits work? =

Rate limits are keyed by API key (or user id if using Application Passwords) in fixed 60-second windows. Exceeded requests return HTTP 429 with a `retry_after` hint.

= Where are logs stored? =

In a custom table `{prefix}storemcp_activity`. Retention is configurable (default 30 days) and sensitive parameters (passwords, secrets, tokens) are redacted before storage.

== Screenshots ==

1. Dashboard — live stats, quick-setup guide and endpoint copy.
2. Settings — API keys, rate limits, CORS, log retention.
3. Tools — per-module toggles with FREE/PRO badges.
4. Activity log — filters, expandable params, CSV export.
5. License — activation and pricing overview.

== Changelog ==

= 1.1.4 — 2026-04-15 =
* Fix ChatGPT setup instructions: correct path to Settings → Apps & Connectors → Advanced settings (renamed in December 2025).

= 1.1.3 — 2026-04-15 =
* Plugin Check pass: escape exception messages, replace `parse_url()` with `wp_parse_url()`, replace `unlink()` with `wp_delete_file()`, prepare all custom-table queries, remove deprecated `load_plugin_textdomain()` call.
* Stop relying on `wp_get_sidebars_widgets()` in widget tools (now reads the `sidebars_widgets` option directly).
* Activity-log CSV export no longer touches the filesystem.
* Trim the readme short description to fit the wp.org 150-char limit; bump "Tested up to" to 6.9.

= 1.1.2 — 2026-04-15 =
* License activation now uses Lemon Squeezy: per-site instance tracking, correct seat counting and proper deactivation.
* Added self-hosted auto-updater pulling from storemcp.io (only active when not installed from wordpress.org).

= 1.1.1 — 2026-04-14 =
* Added full Spanish (es_ES) translation.
* Added plain-language explainer and example prompts for every module and every tool in the admin UI.
* Moved official website from storemcp.com to storemcp.io (plugin URI, license API, docs URLs, pricing links).

= 1.0.0 — 2026-04-14 =
* Initial public release.
* Streamable HTTP + legacy SSE MCP transports.
* 137 tools across 22 modules (WordPress core + WooCommerce).
* 8 resources for passive context.
* Admin panel with dashboard, settings, tools toggle, activity log and license manager.
* API keys, Application Passwords and OAuth hook.
* Rate limiting, activity log with CSV export, redaction of sensitive params.
* HPOS-compatible order queries.
* Yoast and Rank Math compatible SEO tools.

== Upgrade Notice ==

= 1.1.4 =
Fixes the ChatGPT setup instructions in the dashboard (path renamed in December 2025).

= 1.1.3 =
Plugin Check / wordpress.org compliance pass. No behavioural changes.

= 1.1.2 =
Fixes Pro license activation (Lemon Squeezy integration) and enables self-hosted auto-updates.

= 1.1.1 =
Adds Spanish translation and in-admin explainers for every module and tool. Official website moved to storemcp.io.

= 1.0.0 =
First public release of StoreMCP.
