# Security Policy

## Supported Versions

Only the latest major version of Fylgja Fonts Provider receives security updates.

## Reporting a Vulnerability

If you discover a security vulnerability, please **do not** open a public GitHub issue.

Instead, report it privately via [GitHub Security Advisories](https://github.com/fylgja/php-fonts-provider/security/advisories/new).

We'll acknowledge your report as soon as possible and keep you updated as the issue is investigated and resolved. Once a fix is released, we'll credit you in the advisory unless you prefer to remain anonymous.

## Scope

This library is built to run from an admin form, so these are in scope:

- A font config that passes `normalize()` but breaks out of a CSS declaration, a file name or the fonts folder.
- A provider response that makes the library download from a host other than the provider's, or write outside the fonts folder.
- A config or response that makes `Psr18Fetcher` request a host with a non public address.
- A malformed font file that makes `MetricsReader` crash, hang or use excessive memory, rather than return `null`.

Out of scope: the content of the font files themselves, which are served to browsers as they are, and the trust you place in the providers you enable.
