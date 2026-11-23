# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Changed

- A family or variant that is not a set of options is reported as "must be a set of options".
- A font that can be read but has no usable metrics is reported as a warning, instead of silently getting no fallback face.
- Fallback percentages are rounded the same way as the Node version.

### Added

- A parity check against the Node version, `@fylgja/fonts-provider`.

## 1.0.0 - 2026-11-19

Initial Release 🎉
