# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Added

- The `maxFiles` and `maxTotalBytes` options limit what one run downloads.
- `Psr18Fetcher` refuses hosts with a private, loopback, link local, CGNAT, multicast, reserved or IPv4 mapped address, unless `allowPrivateAddresses` is set.
- `Psr18Fetcher` tries a failed request, or a 429 or 5xx response, twice more.

### Changed

- A redirect fails like any other response that is not a 2xx, configure a client that follows redirects not to.
- A config lists at most 50 families.
- Weights must be from 1 to 1000, and a range from low to high. A range with a third part, such as `'100 200 300'`, is refused instead of cut to its first two parts.
- A `cssSelector` may not contain a backslash or an unclosed quote, and is at most 200 characters.

### Fixed

- Two URLs that map to the same file name no longer overwrite each other. The second is skipped with a warning.

## 1.0.0 - 2026-11-23

Initial Release 🎉
