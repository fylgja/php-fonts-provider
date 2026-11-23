# Agent guide

A framework neutral PHP library that resolves web font families from a provider API, downloads the woff2 files and renders the `@font-face` rules and CSS variables.
Its scope is that and nothing else: how the families are configured, where the CSS is stored and when it runs belongs to the application using it.

The Node version, [`@fylgja/fonts-provider`](https://github.com/fylgja/js-fonts-provider), is the source of truth. This library mirrors its API and output byte for byte, so a change there should land here as well.

## Layout

| Folder | |
| --- | --- |
| `src/FontsProvider.php` | Entry point: `normalize()` validates, `generate()` resolves, downloads and measures. |
| `src/Config/` | Validating the families (`FontConfig`) into value objects (`Family`, `Weight`, `LocalVariant`). |
| `src/Provider/` | One class per provider, each turns a `Family` into `Face`s. `Css2Provider` covers Google Fonts and Bunny Fonts. |
| `src/Api/` | Parsing provider stylesheets (`Stylesheet`) and shared face handling (`Faces`). |
| `src/Metrics/` | Reading font metrics from woff2, woff and sfnt, and building the fallback face. |
| `src/Render/` | The CSS and the preload links. |
| `src/Storage/`, `src/Http/` | The two dependencies an application provides. |

## Rules

- **Everything printed is validated.** The families can come from a form, so any value that ends up in CSS, a file name or a URL goes through `FontConfig::isSafe()` (config) or `Faces::isSafe()` (provider responses). Add a check for every new option.
- **Downloads are checked.** https only, the provider's hosts (`allowsDownload()`), the woff2 signature and `maxFileSize`.
- **No request in `normalize()`.** Validation must work offline, so a form can show errors before anything is fetched.
- **One family failing never fails the run.** It becomes a warning, unless `strict` is set.

## Tests

`composer test` runs on recorded HTTP responses in `tests/fixtures/http`, keyed by the SHA-1 of the URL (`tests/Support/Fixtures.php`).
A test that makes a new request fails with the URL. Run `FONTS_RECORD=1 composer test` to record it from the live API. Fontshare files are replaced by an OFL font (`tests/fixtures/fonts/substitute.*`), since their license does not allow redistributing them.
Tests that read woff2 need `ext-brotli` and are skipped without it.

## Parity with the Node version

`composer parity` runs every case in `tests/parity/cases.json` through both versions, on the recorded responses of this repo, and diffs the reports (`run.mjs`, `run.php`).
Install the Node version with `npm install --prefix tests/parity`, or pass a checkout: `tests/parity/run.sh ../js-fonts-provider`.
Without `ext-brotli`, `run.php` decompresses woff2 files through Node, so both sides measure the same file.

Add a case for every new option, provider or message. Where the languages differ:

- Measuring without Brotli (`ProviderInterface::measureUrl()`, the woff or ttf fallback) only exists here, since Node always has Brotli. The parity check runs with Brotli.
- A JSON `{}` and `[]` are the same array in PHP, so an empty options object and an empty list both count as a set of options.

## Code style

- PHP 8.2 is the minimum. Value objects are `final readonly class`, other classes `final`.
- `declare(strict_types=1);`, PSR-12 with 4 space indents, the Fylgja license header on top of every file.
- A docblock on every function: a short description, `@param` and `@return`. Methods implementing an interface inherit theirs. Comment why, not what.
- US English in code and docs.

Commit with the house style: a prefix (`ADD`, `IMP`, `DEL`, `FIX`, `UPD`), imperative mood, around 50 characters.
