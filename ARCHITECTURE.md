# StoreMCP — Architecture

A technical walk-through of how StoreMCP is wired: boot flow, transports, tool
dispatch, permissioning, storage and extension points.

## Load sequence

```
wp-content/plugins/store-mcp/store-mcp.php
        │
        ├─ register_activation_hook   → Installer::install()        (tables + seed options)
        ├─ register_deactivation_hook → Maintenance::unschedule()   (clear cron)
        └─ plugins_loaded (priority 5) → Plugin::instance()
                 │
                 └─ Plugin::boot()
                         ├─ require includes/*.php
                         ├─ new License, Activity_Log, Rate_Limiter, Auth,
                         │        Tools_Registry, Resources_Registry, Server
                         ├─ add_action('init', [$tools, 'bootstrap'], 20)   (requires tool files)
                         ├─ add_action('init', [$resources, 'bootstrap'], 20)
                         ├─ add_action('rest_api_init', [$server, 'register_routes'])
                         ├─ Maintenance::schedule()                 (daily cron)
                         └─ (admin) new Admin\Admin_Page + Admin\Admin_Ajax
```

Tool and resource modules each call `add_action('store_mcp_register_tools', …)`
at file load. The registry's `bootstrap()` method requires every module file and
then fires the action, so all registrations happen in a single deterministic
pass on `init` priority 20.

## Transports

StoreMCP implements the MCP Streamable HTTP transport (revision `2025-03-26`)
as the primary endpoint, with a legacy SSE endpoint for older clients.

| Route | Method | Purpose |
| --- | --- | --- |
| `/wp-json/store-mcp/v1/mcp` | POST | Single JSON-RPC message or batch. Primary transport. |
| `/wp-json/store-mcp/v1/mcp` | GET / DELETE | 405 / 204 (session management placeholders). |
| `/wp-json/store-mcp/v1/message` | POST | Legacy alias for `/mcp`. |
| `/wp-json/store-mcp/v1/sse` | GET | Legacy SSE with keep-alive pings. |
| `/wp-json/store-mcp/v1/info` | GET | Public discovery (no auth): name, version, endpoints, tier. |

All REST routes are registered via `register_rest_route()` with
`permission_callback => [$server, 'permission_check']`, which runs auth and
stashes the resolved `AuthContext` on the request for the handler.

### JSON-RPC error codes

```
-32700  Parse error            (malformed JSON body)
-32600  Invalid Request        (missing jsonrpc: 2.0)
-32601  Method not found       (unknown method or tool)
-32602  Invalid params         (missing / wrong-typed args)
-32603  Internal error         (uncaught Throwable)

StoreMCP-specific (reserved range -32000 / -32099):
-32001  Unauthorized
-32002  Forbidden               (missing capability)
-32003  Rate limited
-32004  License required        (PRO-only tool / resource)
-32005  Disabled                (MCP off in settings)
-32010  Tool failed             (domain error; message describes cause)
-32011  Resource not found
```

## Authentication

Three methods, tried in order, all resolved by `Auth::authenticate()`:

1. **StoreMCP API key** — `Authorization: Bearer smcp_<keyId>_<secret>` or
   `X-StoreMCP-Key: …`. Looked up in `wp_storemcp_api_keys` by `key_id`, then
   verified with `wp_check_password($raw, $hash)`. On success,
   `wp_set_current_user()` is called so that WP capability checks later in the
   pipeline see the right user.
2. **WordPress Application Passwords** — If no API key is present, falls through
   to WP's native `determine_current_user`. Toggled from admin
   (`store_mcp_allow_app_passwords`).
3. **OAuth 2.1** — Filter hook `store_mcp_oauth_authenticate` lets third-party
   plugins inject an `AuthContext` for their own token schemes.

Every handler receives an `AuthContext` object with `user_id`, `key_id`,
`method`, `roles`, `scopes`, `label` and helpers `user_can()` and `bucket()`.

## Tool dispatch

```
Server::dispatch()
  ├─ parse JSON-RPC envelope
  ├─ Rate_Limiter::check()                          (DB-backed fixed window)
  ├─ switch on method
  │     ├─ tools/list  → Tools_Registry::list_for($ctx)
  │     └─ tools/call  → Tools_Registry::call($name, $args, $ctx)
  │           ├─ gate by tier (PRO → License::is_pro())
  │           └─ call handler → returns raw array
  ├─ wrap in MCP content + structuredContent
  ├─ Activity_Log::record()
  └─ return JSON-RPC response (or null for notifications)
```

Any `Tool_Exception` thrown inside a handler is converted into a JSON-RPC error
with its code; any other `Throwable` becomes `-32603 Internal error` so stack
traces never leak.

## Registering a tool

```php
namespace StoreMCP;

final class My_Tools {
    use Tool_Base;

    public static function register( Tools_Registry $registry ): void {
        $registry->register( [
            'name'        => 'store_mcp_do_thing',
            'description' => 'One-line description for the AI client.',
            'tier'        => 'pro',                         // or 'free'
            'module'      => 'class-my-tools',
            'inputSchema' => [
                'type' => 'object',
                'required' => [ 'id' ],
                'properties' => [ 'id' => [ 'type' => 'integer' ] ],
                'additionalProperties' => false,
            ],
        ], [ self::class, 'do_thing' ] );
    }

    public static function do_thing( array $args, AuthContext $context ): array {
        Permissions::require_cap( $context, 'edit_posts' );
        $id = (int) self::require_arg( $args, 'id' );
        // …
        return [ 'id' => $id, 'done' => true ];
    }
}

add_action( 'store_mcp_register_tools', [ My_Tools::class, 'register' ] );
```

The `Tool_Base` trait provides `arg_*`, `require_arg`, `pagination()`,
`paginated()`, and formatters (`format_post`, `format_product`, etc.). Throwing
`Tool_Exception($message, $code)` returns a clean JSON-RPC error. Return a raw
array from the handler and the registry wraps it in MCP content blocks.

## Storage

Three custom tables (created on activation via `dbDelta`):

| Table | Purpose |
| --- | --- |
| `{prefix}storemcp_api_keys` | Hashed API keys with `key_id` lookup, label, owner, scopes, timestamps, optional revocation. |
| `{prefix}storemcp_activity` | Append-only audit log: timestamp, IP, user id, key id, method, tool name, redacted params, status, duration, error. |
| `{prefix}storemcp_rate_limits` | Bucketed counters `(bucket, window_start)` with a unique index for `INSERT … ON DUPLICATE KEY UPDATE` atomicity. |

All three are dropped in `uninstall.php`.

Options prefixed `store_mcp_*` cover plugin settings, module toggles, license
state and white-label data. The `License::state()` option is refreshed by a
daily cron job (`store_mcp_recheck_license`) that calls the configured
`STORE_MCP_LICENSE_API` endpoint.

## Permissions

`Permissions::require_cap($ctx, 'capability', …args)` throws a
`Tool_Exception(-32002 ERR_FORBIDDEN)` when the user lacks the capability.
`Permissions::require_woocommerce()` throws when `WooCommerce` is not active.

Capability map (summary):

| Operation | Capability |
| --- | --- |
| Read content | `read` |
| Edit pages | `edit_pages`, `publish_pages` |
| Edit posts | `edit_posts`, `publish_posts`, `edit_post` |
| Upload media | `upload_files` |
| Manage menus / widgets | `edit_theme_options` |
| List / read users | `list_users` |
| Mutate users | `create_users`, `edit_users`, `delete_users` |
| WC products | `edit_products`, `edit_product`, `delete_product` |
| WC terms | `manage_product_terms` |
| WC orders | `edit_shop_orders`, `edit_shop_order`, `delete_shop_order` |
| WC reports / settings | `view_woocommerce_reports`, `manage_woocommerce` |
| Comments / reviews | `moderate_comments` |
| Plugins | `activate_plugins` |
| Themes | `switch_themes` |
| System / site options | `manage_options` |

## Admin panel

- `admin/class-admin-page.php` registers the menu, enqueues assets, renders
  views and handles the CSV export via `admin-post.php`.
- `admin/class-admin-ajax.php` registers eleven `wp_ajax_store_mcp_*` handlers,
  each gated by nonce + `manage_options`.
- Views under `admin/views/` are plain PHP files that receive `$plugin` (the
  `Plugin` singleton) and render HTML with native WP admin styles plus a small
  custom stylesheet (`admin/css/admin-style.css`). JavaScript is vanilla
  jQuery (`admin/js/admin-script.js`) — no build step.
- White Label is only registered as a menu entry when `License::is_agency()`
  returns true, and its AJAX handler double-checks the tier.

## Extension hooks

| Hook | Type | Purpose |
| --- | --- | --- |
| `store_mcp_register_tools` | action | Third-party modules register tools. |
| `store_mcp_register_resources` | action | Third-party modules register resources. |
| `store_mcp_auth_context` | filter | Rewrap or augment the resolved `AuthContext`. |
| `store_mcp_oauth_authenticate` | filter | Return an `AuthContext` for an OAuth bearer. |
| `store_mcp_server_name` | filter | Override the name returned in `initialize`. |
| `store_mcp_flush_cache` | action | Fires after the cache-flush tool runs, with the list of cleared plugins. |

## Caching

- Report tools (`report_sales`, `report_sales_by_date`, top sellers / earners,
  customers totals, orders totals, products totals) cache their result in
  transients keyed by period hash for `store_mcp_cache_ttl_seconds` seconds
  (default 300). Clearing all StoreMCP transients is exposed as the
  `store_mcp_` prefix in `uninstall.php` and via the `clear_transients`
  system tool.
- The tools registry itself does no caching — every request re-runs
  `tools/list` dispatch, since the module set can change with license / option
  toggles.

## Rate limiting

`Rate_Limiter::check()` uses a single `INSERT … ON DUPLICATE KEY UPDATE
counter = counter + 1` statement keyed by `(bucket, window_start)` to keep the
operation atomic even under concurrent writes without relying on a persistent
object cache. `window_start` is `floor(time() / 60) * 60`. One in every 100
calls triggers a cleanup of windows older than 5 minutes to keep the table
small.

Limits:
- Free: 30 req/min (configurable).
- Pro:  120 req/min (configurable).
- Agency: 5× the Pro limit.

## File layout

```
store-mcp/
├── store-mcp.php                  bootstrap
├── uninstall.php                  drops tables + options
├── readme.txt                     wordpress.org metadata
├── CHANGELOG.md
├── ARCHITECTURE.md
├── assets/                        wordpress.org banner / icon / screenshots
├── languages/                     gettext .pot + translations
├── includes/
│   ├── class-store-mcp.php        singleton bootstrap
│   ├── class-mcp-server.php       JSON-RPC + transports
│   ├── class-mcp-auth.php         three-way auth
│   ├── class-mcp-license.php      license manager
│   ├── class-mcp-tools-registry.php
│   ├── class-mcp-resources-registry.php
│   ├── class-mcp-rate-limiter.php
│   ├── class-mcp-activity-log.php
│   ├── class-mcp-permissions.php
│   ├── class-mcp-installer.php    dbDelta + seed
│   ├── class-mcp-maintenance.php  cron
│   ├── tools/
│   │   ├── trait-tool-base.php    shared helpers + formatters
│   │   ├── class-site-tools.php
│   │   ├── class-pages-tools.php
│   │   ├── class-posts-tools.php
│   │   ├── class-media-tools.php
│   │   ├── class-menus-tools.php
│   │   ├── class-widgets-tools.php
│   │   ├── class-users-tools.php
│   │   ├── class-products-tools.php
│   │   ├── class-categories-tools.php
│   │   ├── class-tags-tools.php
│   │   ├── class-reports-tools.php
│   │   ├── class-orders-tools.php
│   │   ├── class-customers-tools.php
│   │   ├── class-coupons-tools.php
│   │   ├── class-variations-tools.php
│   │   ├── class-attributes-tools.php
│   │   ├── class-shipping-tools.php
│   │   ├── class-tax-tools.php
│   │   ├── class-settings-tools.php
│   │   ├── class-refunds-tools.php
│   │   ├── class-webhooks-tools.php
│   │   ├── class-reviews-tools.php
│   │   ├── class-seo-tools.php
│   │   ├── class-plugins-tools.php
│   │   ├── class-themes-tools.php
│   │   └── class-system-tools.php
│   └── resources/
│       ├── class-site-resources.php
│       ├── class-content-resources.php
│       └── class-store-resources.php
└── admin/
    ├── class-admin-page.php
    ├── class-admin-ajax.php
    ├── css/admin-style.css
    ├── js/admin-script.js
    └── views/
        ├── dashboard.php
        ├── settings.php
        ├── tools.php
        ├── activity-log.php
        ├── license.php
        └── white-label.php
```
