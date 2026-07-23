# Release process

This repository uses semantic-release with Conventional Commits. Releases are prepared from `main`, and Git tags use non-prefixed SemVer such as `2.0.0`.

## Release branch target

The `release-2.x` branch prepares the next stable release as `2.0.0`. Until that stable tag exists, the semantic-release config forces the first `main` publish to use a major release type and then verifies the computed release is exactly `2.0.0`. After the `2.0.0` tag exists, normal Conventional Commit analysis drives later releases.

## Commit messages

Use commit messages that describe the public change:

- `fix: correct timber attribute helpers` creates a patch release.
- `feat: add native block registration` creates a minor release.
- `feat!: change generated child theme structure` or a `BREAKING CHANGE:` footer creates a major release.

## Local checks

Run:

```sh
npm run lint:php
npm run pr:check
npm run release:check
npm run publish-test -- --no-ci
```

`lint:php` is the local PHPCS and PHPStan entry point. Install Composer development dependencies first; use `npm run lint:php:fix` for PHPCBF auto-fixes.

`pr:check` runs the practical, stubbed pull request suite:

- Composer metadata validation.
- Composer dependency install for Twig smoke coverage.
- ACF Local JSON smoke test.
- Twig attribute helper smoke test.
- Twig switch tag smoke test.
- Child theme generator smoke test.
- Component locator smoke test.
- Parent theme filter smoke test.
- Whisk dependency installation.

`pr:check` does not build the empty Whisk starter. The dedicated extended CI job copies `.github/fixtures/whisk-a11y` into `whisk/src/components` before building, so a real Twig story and Vite entry exercise Core without adding a component system to the committed starter.

`release:check` adds static release-readiness checks and the full WordPress fixture smoke path. The fixture installs the parent theme in an isolated WordPress site, generates and activates a child theme from Whisk, adds neutral built asset and block fixtures, renders frontend routes through Timber, fetches those built child assets, and checks ACF/Twig and native `block.json` discovery. It requires WP-CLI and MySQL. It skips gracefully when WP-CLI or database settings are unavailable unless `WP_SMOKE_REQUIRED=1` is set.

## CI

The WordPress Theme Readiness workflow runs on pull requests, manual dispatch, and a nightly schedule.

Pull requests run four independently visible jobs:

- `Practical theme readiness`: clean root npm installation, Composer validation, npm audits, the practical `pr:check` suite, and static `release:check` assertions.
- `PHP coding standards and static analysis`: PHP 8.3, Composer development dependencies, PHPCS, and PHPStan through `npm run lint:php`.
- `WordPress fixture smoke`: MySQL, WP-CLI, Timber, generated-child activation, block discovery, assets, and home/page/single/archive/search/author/404 rendering with `WP_SMOKE_REQUIRED=1`.
- `Extended Whisk Storybook and a11y`: a CI-only component seed, the Core 4 Vite and Storybook builds, and an axe audit of the discovered story.

Nightly scheduled runs repeat both extended jobs. Manual dispatch runs the WordPress fixture when `wordpress_fixture` is enabled and the Whisk build/audit when `extended_checks` is enabled. The workflow concurrency group cancels superseded pull-request runs, and both extended jobs have explicit runtime limits.

## 2.0 merge and release gate

The four pull-request jobs run automatically for changes targeting `main`, `release-2.x`, and the other configured integration branches. For an additional final rerun before merging the 2.0 release branch:

1. Open GitHub Actions for `emulsify-ds/emulsify-wordpress`.
2. Select the `WordPress Theme Readiness` workflow.
3. Choose `Run workflow`.
4. Select the `release-2.x` branch.
5. Keep `wordpress_fixture` enabled. It defaults to enabled for manual runs.
6. Enable `extended_checks` to rerun the CI-seeded Storybook and accessibility audit.

Success means `Practical theme readiness`, `PHP coding standards and static analysis`, `WordPress fixture smoke`, and `Extended Whisk Storybook and a11y` pass. The fixture job installs WP-CLI, starts MySQL, sets `WP_SMOKE_REQUIRED=1`, and runs `npm run release:check` against the isolated WordPress fixture. Record the successful workflow run in the 2.0 release PR before merging.

The semantic-release workflow remains release-gated. It runs release readiness, requires the full WordPress fixture smoke path with WP-CLI and MySQL, runs a semantic-release dry run, and then allows the final publish job only after the readiness job succeeds. Release publishing must not proceed when the full fixture path is skipped or failed.

## License

The 2.x branch uses GPL-2.0-only metadata across npm, Composer, WordPress theme headers, and repository license files.
