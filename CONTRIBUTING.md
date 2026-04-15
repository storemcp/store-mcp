# Contributing to StoreMCP

Thanks for your interest in contributing! This document outlines how to set up
a development environment, the coding style the project follows, and the
process for submitting patches.

By contributing you agree that your contributions will be licensed under the
same [GPL-2.0-or-later license](./LICENSE) as the rest of the project, and
that you abide by our [Code of Conduct](./CODE_OF_CONDUCT.md).

## Ways to contribute

- **Report bugs**: open an issue using the
  [bug report template](./.github/ISSUE_TEMPLATE/bug_report.md). Include your
  WordPress, WooCommerce and PHP versions plus steps to reproduce.
- **Suggest features**: open an issue using the
  [feature request template](./.github/ISSUE_TEMPLATE/feature_request.md).
- **Write tools/resources**: add a new MCP module under `includes/tools/` or
  `includes/resources/`. See "Adding a tool" below.
- **Improve translations**: `.pot` template lives in `languages/`. PRs with new
  `.po`/`.mo` pairs are welcome.
- **Improve the docs**: README, ARCHITECTURE, inline PHPDoc.

## Development setup

Requirements:

- PHP 8.0 or later
- WordPress 6.4 or later (WooCommerce 8.0+ if you work on WC tools)
- Composer (only needed to run PHPCS locally)

```bash
git clone https://github.com/storemcp/store-mcp.git wp-content/plugins/store-mcp
cd wp-content/plugins/store-mcp
```

Activate the plugin from WordPress admin. Generate an API key from
**StoreMCP → Settings** to exercise the MCP endpoints locally.

Quick endpoint sanity check:

```bash
curl -s https://<your-site>/wp-json/store-mcp/v1/info | jq .
```

## Coding style

StoreMCP broadly follows the
[WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/),
with a few pragmatic relaxations for a PHP 8+, namespaced codebase:

- Short array syntax `[]` is preferred over `array()`.
- Not every private helper needs a docblock — reserve them for public API.
- Interior multi-line function call alignment is up to you.

The security- and correctness-relevant sniffs (escaping, `$wpdb->prepare`,
capability checks, i18n text domain, global-prefix discipline) are all
enforced in the ruleset at [phpcs.xml.dist](./phpcs.xml.dist).

PHPCS is **optional** and **not part of CI** — run it locally if you want.
CI only enforces PHP syntax (`php -l`) across PHP 8.0 / 8.1 / 8.2 / 8.3.

Install PHPCS with the WPCS ruleset:

```bash
composer global require --dev \
  squizlabs/php_codesniffer:^3.9 \
  wp-coding-standards/wpcs:^3 \
  phpcsstandards/phpcsextra:^1 \
  phpcsstandards/phpcsutils:^1
phpcs --config-set installed_paths \
  "$HOME/.composer/vendor/wp-coding-standards/wpcs,$HOME/.composer/vendor/phpcsstandards/phpcsextra,$HOME/.composer/vendor/phpcsstandards/phpcsutils"
```

Run against the repo:

```bash
phpcs --standard=phpcs.xml.dist
```

Conventions worth repeating:

- PHP files begin with `<?php` and have `defined( 'ABSPATH' ) || exit;` on
  line 3 or 4.
- Class files use the `class-<name>.php` naming scheme.
- Everything lives in the `StoreMCP` namespace (or a sub-namespace).
- All user-facing strings go through i18n functions with the `store-mcp` text
  domain: `__()`, `esc_html__()`, `esc_attr__()`, `_n()`, `sprintf()`.
- All database reads/writes use `$wpdb->prepare()`.
- All REST and admin-AJAX handlers are guarded by capability checks and
  nonces.
- No external HTTP calls from tool handlers without an explicit option toggle.

## Adding a tool

1. Pick or create a module file under `includes/tools/`
   (`class-<domain>-tools.php`).
2. Use the `Tool_Base` trait for helpers (`arg_*`, `require_arg`,
   `paginated`, formatters).
3. Register the tool inside a `register()` static method and hook it on
   `store_mcp_register_tools`:

```php
add_action( 'store_mcp_register_tools', [ My_Tools::class, 'register' ] );
```

4. Provide a JSON Schema `inputSchema` with `required` and
   `additionalProperties => false`.
5. Throw `Tool_Exception( $message, $code )` on domain errors — the registry
   converts them into JSON-RPC errors.
6. Add an explainer row in [admin/views/tools.php](./admin/views/tools.php)
   so the admin UI shows what the tool does.
7. Run `./build-zip.sh` to verify the zip still builds cleanly.

Full walk-through in [ARCHITECTURE.md](./ARCHITECTURE.md#registering-a-tool).

## Commit messages

- Short imperative subject line (≤72 chars): `Add shipping zone create tool`
- Optional body explaining the *why*, wrapped at 72 chars.
- Reference issues with `Fixes #123` when applicable.

## Pull requests

1. Fork the repo, create a topic branch from `main`:
   `git checkout -b feat/my-change`.
2. Make focused commits — one logical change per PR when possible.
3. Run `phpcs --standard=phpcs.xml.dist` and fix any warnings.
4. Update [CHANGELOG.md](./CHANGELOG.md) under an `## [Unreleased]` heading
   (create it if missing).
5. Update `.pot` if you added translatable strings:
   `wp i18n make-pot . languages/store-mcp.pot` (requires WP-CLI).
6. Open the PR using the [template](./.github/PULL_REQUEST_TEMPLATE.md),
   describe the change, how you tested it, and any screenshots for UI work.

Maintainers aim to review within a week. Smaller, focused PRs are reviewed
faster than large sweeping ones.

## Release process

Releases are cut by maintainers. The flow is:

1. Bump the version in `store-mcp.php`, `readme.txt` (Stable tag) and
   `CHANGELOG.md`.
2. Run `./build-zip.sh` — it enforces that both version numbers match.
3. Tag `vX.Y.Z` and push; the release workflow publishes the zip.
4. Update the remote update feed on `storemcp.io/api/update`.

## Questions

- General questions / usage: open a
  [GitHub discussion](https://github.com/storemcp/store-mcp/discussions).
- Bugs: file an issue.
- Security: do **not** open an issue — see [SECURITY.md](./SECURITY.md).
