# Security Policy

The StoreMCP team takes security seriously. Thank you for helping keep the
plugin and its users safe.

## Supported versions

Security fixes are issued for the current minor release series. Older
releases may receive fixes at the maintainers' discretion.

| Version | Status      |
| ------- | ----------- |
| 1.1.x   | Supported   |
| 1.0.x   | Best effort |
| < 1.0   | Unsupported |

## Reporting a vulnerability

**Please do not open a public GitHub issue for security vulnerabilities.**
Public disclosure before a patch is available puts every StoreMCP user at
risk.

Email reports to **security@storemcp.io**. If you need encryption, request
the PGP key in your initial (non-sensitive) email and we will send it by
reply.

Include as much of the following as you can:

- A clear description of the issue and its impact.
- The StoreMCP version(s) affected, plus PHP and WordPress versions used.
- Step-by-step reproduction (a proof-of-concept request, payload or script
  is ideal).
- Any known mitigations or workarounds.

## What to expect

| Milestone                                   | Target timeline               |
| ------------------------------------------- | ----------------------------- |
| Acknowledgement of your report              | within 3 business days        |
| Initial triage and severity assessment      | within 7 business days        |
| Patch in a released version (or mitigation) | within 90 days for most bugs  |
| Public disclosure coordinated with reporter | after patch is released       |

We follow a **90-day coordinated disclosure window** from the date of our
acknowledgement. If the fix is delayed beyond 90 days we will keep you
informed and agree on a revised timeline.

## Scope

In scope:

- The PHP code in this repository (the plugin itself).
- The REST/MCP endpoints it registers under `/wp-json/store-mcp/v1/*`.
- The `.well-known` OAuth discovery endpoints and the
  `/store-mcp/authorize` consent screen.
- The admin UI, admin-AJAX handlers and the uninstall routine.

Out of scope:

- Vulnerabilities in WordPress core or third-party plugins — report those
  to the respective vendor.
- Vulnerabilities in `storemcp.io` itself (the license/update backend) —
  those belong to the operators of that service; you may still email
  security@storemcp.io and we will route the report internally.
- Reports that require the attacker to already be an administrator on the
  target site (administrators are trusted by the WordPress security model).
- Social-engineering, phishing, physical or DoS attacks.

## Recognition

If you would like public credit we will happily name you in the release notes
and in a `SECURITY_HALL_OF_FAME.md` file once a fix is published. Let us know
your preferred name and link when you report.

## Safe harbor

We will not pursue legal action against researchers who:

- Make a good-faith effort to follow this policy.
- Report promptly and avoid unnecessary privacy violations, service
  disruption, or data destruction.
- Give us a reasonable window to remediate before public disclosure.

Thank you for helping keep StoreMCP and its users safe.
