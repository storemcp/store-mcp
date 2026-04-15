# Changelog

All notable changes to StoreMCP are documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.2] — 2026-04-15

### Added

- `build-zip.sh --wporg` builds a wordpress.org-safe variant of the plugin
  with the self-hosted `Updater` class stripped out. The default build
  (`build-zip.sh`) keeps the updater for direct distribution from
  storemcp.io.

### Changed

- Shortened the plugin display name to "StoreMCP — AI Control Center".
- Updater is now loaded conditionally — the class file is excluded from the
  wordpress.org build and the bootstrap tolerates its absence.
- Published this repository as open source under GPL-2.0-or-later on GitHub
  at `storemcp/store-mcp`.

## [1.1.1] — 2026-04-14

### Added

- Spanish (es_ES) translation bundled in `languages/` — full coverage of the
  admin UI, including tool module explainers and example prompts.
- Plain-language explainer per module on the **Tools** admin page: each module
  now shows what it does plus two example prompts you can send to Claude,
  ChatGPT or any other MCP-compatible client.
- Plain-language explainer per individual tool in the **Registered tools**
  catalogue — every `store_mcp_*` tool now has a one-line description of what
  it does and why you would call it.

### Changed

- Official website moved from `storemcp.com` to `storemcp.io`. Plugin URI,
  author URI, license API endpoint, OAuth documentation URLs, pricing links and
  admin copy all updated.

## [1.0.0] — 2026-04-14

Initial public release.

### Core

- Streamable HTTP transport at `POST /wp-json/store-mcp/v1/mcp` implementing MCP
  protocol revision `2025-03-26`.
- Legacy SSE transport at `GET /wp-json/store-mcp/v1/sse` with keep-alive pings
  for older clients.
- Public discovery endpoint at `GET /wp-json/store-mcp/v1/info`.
- JSON-RPC 2.0 dispatcher handling `initialize`, `tools/list`, `tools/call`,
  `resources/list`, `resources/read`, `ping`, `prompts/list`,
  `resources/templates/list`, and `logging/setLevel`.
- Three authentication methods:
  1. StoreMCP API keys (hashed with `wp_hash_password`, shown once at creation).
  2. Native WordPress Application Passwords.
  3. OAuth 2.1 filter hook (`store_mcp_oauth_authenticate`) for custom identity
     providers.
- License manager with placeholder remote endpoint and Free / Pro / Agency tiers.
- Per-key rate limiter backed by a custom table (fixed 60-second windows).
- Append-only activity log with redaction of sensitive parameters (passwords,
  secrets, tokens, API keys), CSV export and cron-driven retention pruning.
- CORS allow-list configurable from the admin.

### Admin panel

- Top-level "StoreMCP" menu with six pages: Dashboard, Settings, Tools,
  Activity Log, License and (Agency only) White Label.
- Dashboard with live stats, quick-setup guide, endpoint copy, top-tools panel
  and endpoint health check.
- Settings for API keys, general toggles, rate limits (Free/Pro), report cache
  TTL, CORS origins and log retention.
- Tools page with per-module enable/disable switches, badges marking FREE /
  PRO / WC-required status.
- Activity log with tool/status/date filters, expandable params and errors,
  paginated table and CSV export.
- License activation, re-check and deactivation with comparative pricing panel
  for Free sites.
- White-label customisation (Agency): plugin name, logo, menu icon,
  support URL.
- All admin actions go through nonce + capability checks.

### Tools (137 total)

**WordPress core (FREE)** — 43 tools across 7 modules:
- Site: info, health, cache flush, search-replace.
- Pages: list, get, create, update, delete.
- Posts: CRUD + categories and tags CRUD.
- Media: list, get, upload (base64 or URL side-load), update, delete.
- Menus: list menus, get menu, menu-item CRUD.
- Widgets: list sidebars, list / create / update / delete widgets.
- Users: list, get (FREE); create, update, delete (PRO).

**WooCommerce (FREE reads + PRO writes)** — 56 tools across 6 modules:
- Products: list, get (FREE); create, update, delete, bulk update (PRO).
- Product categories: list, get (FREE); create, update, delete (PRO).
- Product tags: list (FREE); create, update, delete (PRO).
- Reports: orders totals, products totals (FREE); sales, sales by date,
  top sellers, top earners, customers totals, stock low, stock out (PRO).

**WooCommerce PRO** — 59 tools across 11 modules:
- Orders: list, get, create, update, delete, notes list & create.
- Customers: list, get, create, update, delete.
- Coupons: list, get, create, update, delete.
- Variations: list, get, create, update, delete (with image side-load).
- Attributes: attributes CRUD + terms CRUD.
- Shipping: zones CRUD + methods listing & creation.
- Tax: rates CRUD + classes listing.
- Settings: WC settings read / update + payment gateways list / update.
- Refunds: list, create (gateway-refund capable), delete.
- Webhooks: list, get, create, update, delete.
- Reviews: list, get, create, update, delete.

**WordPress PRO (WC-agnostic)** — 16 tools across 4 modules:
- SEO: post meta CRUD, Yoast / Rank Math normalised meta, site-option
  allow-list read/update.
- Plugins: list, activate, deactivate (with self-deactivation guard).
- Themes: get active, list, switch.
- System: status report, list maintenance tools, run maintenance tool
  (transients, sessions, term counts, thumbnail meta, WP and WC DB upgrade).

### Resources (8 total)

- `store://site/info` — live site info snapshot.
- `store://plugins/active` — active plugins with versions.
- `store://content/recent-posts` — ten most recent published posts.
- `store://content/drafts` — draft posts and pages.
- `store://content/scheduled` — scheduled posts.
- `store://store/stats` — store summary (total products, orders today, revenue).
- `store://store/low-stock` — products below threshold.
- `store://store/pending-orders` — orders awaiting processing.

### Compatibility

- WordPress 6.4 or later.
- WooCommerce 8.0 or later (optional; WC tools are registered only when active).
- PHP 8.0 or later.
- High-Performance Order Storage (HPOS) compatible.
- Multisite aware.
- Translation-ready with `store-mcp` textdomain and a generated `.pot`
  template.

[1.0.0]: https://github.com/storemcp/store-mcp/releases/tag/v1.0.0
