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
npm run pr:check
npm run release:check
npm run publish-test -- --no-ci
```

`pr:check` runs the practical, stubbed pull request suite:

- Composer metadata validation.
- Composer dependency install for Twig smoke coverage.
- PHP linting.
- ACF Local JSON smoke test.
- Twig attribute helper smoke test.
- Twig switch tag smoke test.
- Child theme generator smoke test.
- Component locator smoke test.
- Parent theme filter smoke test.
- Whisk dependency installation.

`pr:check` does not build the empty Whisk starter. Whisk's build script delegates directly to Emulsify Core, and that build is expected to fail until a project installs or configures a component system with Vite input files.

`release:check` adds static release-readiness checks and the full WordPress fixture smoke path. The fixture installs the parent theme in an isolated WordPress site, generates and activates a child theme from Whisk, adds neutral built asset and block fixtures, renders frontend routes through Timber, fetches those built child assets, and checks ACF/Twig and native `block.json` discovery. It requires WP-CLI and MySQL. It skips gracefully when WP-CLI or database settings are unavailable unless `WP_SMOKE_REQUIRED=1` is set.

## CI

The WordPress Theme Readiness workflow runs on pull requests, manual dispatch, and a weekly schedule.

Pull requests run pragmatic checks:

- Clean root npm installation.
- Composer metadata validation.
- Runtime and full npm audits.
- PHP linting.
- The practical `pr:check` suite, including WP-CLI child theme generation smoke coverage, Whisk dependency installation, and standalone starter init smoke coverage for Emulsify CLI.
- Static release readiness checks through `release:check`.

Normal pull requests do not start MySQL or run the full WordPress fixture. Manual and scheduled runs execute the full WordPress fixture smoke test with WP-CLI and MySQL so heavier runtime coverage stays available without slowing every pull request.

Manual dispatch can also run the Whisk Storybook build and accessibility audit for extended frontend confidence.

## 2.0 merge and release gate

Before merging the 2.0 release branch, maintainers should run the full fixture through GitHub Actions:

1. Open GitHub Actions for `emulsify-ds/emulsify-wordpress`.
2. Select the `WordPress Theme Readiness` workflow.
3. Choose `Run workflow`.
4. Select the `release-2.x` branch.
5. Keep `wordpress_fixture` enabled. It defaults to enabled for manual runs.
6. Leave `extended_checks` disabled unless Storybook and accessibility coverage is needed for that release decision.

Success means both the `Practical theme readiness` job and the `WordPress fixture smoke` job pass. The fixture job installs WP-CLI, starts MySQL, sets `WP_SMOKE_REQUIRED=1`, and runs `npm run release:check` against the isolated WordPress fixture. Record the successful workflow run in the 2.0 release PR before merging.

The semantic-release workflow remains release-gated. It runs release readiness, requires the full WordPress fixture smoke path with WP-CLI and MySQL, runs a semantic-release dry run, and then allows the final publish job only after the readiness job succeeds. Release publishing must not proceed when the full fixture path is skipped or failed.

## License

The 2.x branch uses GPL-2.0-only metadata across npm, Composer, WordPress theme headers, and repository license files.
