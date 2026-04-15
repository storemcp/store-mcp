---
name: Bug report
about: Report a reproducible bug in StoreMCP
title: "[bug] "
labels: bug
assignees: ''
---

**⚠️ Do not report security vulnerabilities here.** See [SECURITY.md](../../SECURITY.md).

## Describe the bug

A clear and concise description of what's broken.

## Steps to reproduce

1. Go to '…'
2. Click on '…'
3. Run tool/call '…' with args `{ … }`
4. See error

## Expected behaviour

What you expected to happen.

## Actual behaviour

What actually happened. Include the full JSON-RPC error response or PHP error
if relevant.

## Logs

Paste the relevant rows from **StoreMCP → Activity Log**, and any PHP errors
from `wp-content/debug.log` if `WP_DEBUG_LOG` is enabled. Redact API keys.

```
paste logs here
```

## Environment

- StoreMCP version:
- WordPress version:
- WooCommerce version (if applicable):
- PHP version:
- Web server (apache/nginx/litespeed):
- MCP client (Claude Desktop / Cursor / ChatGPT / custom):
- MCP transport used (`streamable-http` / `sse`):
- Auth method (`api_key` / `application_password` / `oauth`):
- Multisite? (yes / no)
- HPOS enabled? (yes / no)

## Additional context

Screenshots, related plugins, recent changes, anything else that might help.
