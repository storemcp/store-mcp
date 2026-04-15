<!--
Thanks for contributing to StoreMCP! Please fill in the sections below so
reviewers have the context they need.

Security: DO NOT open a PR that discloses an unpatched vulnerability. See
SECURITY.md for responsible disclosure.
-->

## Summary

What does this PR change and why? One or two paragraphs.

Fixes #

## Type of change

- [ ] Bug fix (non-breaking change that fixes an issue)
- [ ] New tool or resource
- [ ] New feature / capability
- [ ] Refactor / internal cleanup
- [ ] Documentation only
- [ ] Translation
- [ ] Breaking change (describe below)

## How I tested it

Describe the manual and/or automated checks you ran. For new tools, paste the
JSON-RPC call and its response. For admin-UI changes, include a screenshot.

```bash
# sample JSON-RPC call
curl -s -X POST https://<site>/wp-json/store-mcp/v1/mcp \
  -H "Authorization: Bearer smcp_xxx" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"…","arguments":{}}}'
```

## Checklist

- [ ] Code follows the WordPress Coding Standards (`phpcs --standard=phpcs.xml.dist` passes).
- [ ] New user-facing strings use i18n functions with the `store-mcp` text domain.
- [ ] DB access uses `$wpdb->prepare()`; REST/AJAX handlers have capability + nonce checks.
- [ ] Added/updated entries in `CHANGELOG.md` under an `## [Unreleased]` heading.
- [ ] Added/updated JSON Schemas for any new tools (`inputSchema` with `required` and `additionalProperties: false`).
- [ ] Updated `ARCHITECTURE.md` and/or `README.md` if behaviour, flow or public API changed.
- [ ] Regenerated `languages/store-mcp.pot` if translatable strings were added/changed.
- [ ] I agree to license my contribution under GPL-2.0-or-later.

## Screenshots (admin UI changes)

| Before | After |
| --- | --- |
|  |  |

## Notes for reviewers

Anything reviewers should pay extra attention to, known limitations, or
follow-ups planned for a later PR.
