# 2.0.0 Release Notes Draft

Status date: 2026-08-02

## Version strategy

Latest published tag: none on the stable line. The `release-2.x` branch prepares
the first stable release as `2.0.0`.

Until the `2.0.0` tag exists, `release.config.js` forces the first `main` publish
to a major release type and verifies the computed version is exactly `2.0.0`.
After that tag exists, normal Conventional Commit analysis drives later releases
and this file should be rewritten for the next version.

## Compatibility notes

- WordPress 6.7 or newer; PHP 8.3 or newer.
- Timber `^2.3` is required by the parent theme.
- Generated child themes use Emulsify Core `^4.3.2` and the Vite build workflow.
- Generated child themes keep `Template: emulsify`; `whisk` remains a
  generation-only starter source and must not be activated directly.
- Root release tooling requires Node.js `>=24.10`; generated child theme tooling
  requires Node.js `>=24`.

## Draft GitHub release notes

Title: `2.0.0`

### Added

- Timber-first WordPress parent theme with Twig route fallbacks, global context,
  namespace registration, and Emulsify-compatible switch tags.
- `wp emulsify` WP-CLI child theme generation with `--machine-name`,
  `--description`, `--parent`, `--dry-run`, `--force`, and `--activate`.
- Standalone starter generation through
  [`emulsify-wordpress-starter`](https://github.com/emulsify-ds/emulsify-wordpress-starter)
  and its `.cli/init.js` hook.
- Project-specific documentation in generated child themes: `README.md`,
  `docs/development.md`, `docs/upgrading.md`, and `docs/support-information.md`,
  resolved from `%%EMULSIFY_*%%` tokens at generation time.
- A generated child theme contract with focused tests, plus a cross-path parity
  smoke that requires both generation paths to produce the same file tree.
- ACF/Twig block registration, native `block.json` registration, block pattern
  registration, editor policy controls, and editor enhancements.
- Asset discovery and enqueuing for built child theme output under `dist/`.

### Changed

- Root `package.json` is now the single source of truth for the release version.
  Theme headers, starter metadata, generated lineage, and smoke assertions all
  derive from it instead of restating a literal.
- Both generation paths emit two-space JSON so generated metadata is identical
  regardless of which path a project used.
- Generation-only tooling is no longer left in generated child themes. The
  WP-CLI generator excludes `.cli`, and the standalone starter hook removes
  itself after a successful run.
- `docs-command-check.cjs` now exports a reusable validator and can validate a
  real generated child theme directory, not just repository documentation.

### Fixed

- `style.css` header replacement no longer warns when a header already holds the
  requested value; only a genuinely missing header is reported.

### Internal

- Added `npm run test:generated-theme` and `npm run smoke:generation-parity`.
- Added release checks for generated documentation sections, the documentation
  token list across both generators, and the generated child theme contract.

## Validation notes

- Run `npm run release:check -- --skip-smoke` for static validation.
- Run `npm run test:generated-theme` and `npm run smoke:generation-parity` for
  generation coverage.
- Run `npm run release:check` with WP-CLI and MySQL available for the full
  WordPress fixture path.
- Run `npm run publish-test -- --no-ci` before publishing.
- Publish the compatible `@emulsify/core` release before Emulsify WordPress
  `2.0.0`.
