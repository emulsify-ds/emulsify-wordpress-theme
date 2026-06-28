# Release process

This repository uses semantic-release with Conventional Commits. Releases are prepared from `main`, and Git tags use non-prefixed SemVer such as `2.0.0`.

## Release branch target

The `release-2.x` branch prepares the next stable release as `2.0.0`. The release dry run must compute `2.0.0` before the publish job runs. If it computes another version, the release guard fails so stale 1.x metadata is not published.

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

`pr:check` runs the practical pull request suite:

- Composer metadata validation.
- Composer dependency install for Twig smoke coverage.
- PHP linting.
- ACF Local JSON smoke test.
- Twig attribute helper smoke test.
- Child theme generator smoke test.
- Component locator smoke test.
- Parent theme filter smoke test.
- Whisk dependency installation.
- Whisk Core 4/Vite build.

`release:check` adds static release-readiness checks and the full WordPress fixture smoke path. The fixture installs the parent theme in an isolated WordPress site, generates and activates a child theme from Whisk, adds neutral built asset and block fixtures, renders frontend routes through Timber, fetches those built child assets, and checks ACF/Twig and native `block.json` discovery. It skips gracefully when WP-CLI or database settings are unavailable unless `WP_SMOKE_REQUIRED=1` is set.

## CI

The WordPress Theme Readiness workflow runs on pull requests, manual dispatch, and a weekly schedule.

Pull requests run pragmatic checks:

- Clean root npm installation.
- Composer metadata validation.
- Runtime and full npm audits.
- PHP linting.
- The practical `pr:check` suite, including WP-CLI child theme generation smoke coverage, Whisk dependency installation, and the Whisk Core 4/Vite build.
- Static release readiness checks through `release:check`.

Normal pull requests do not start MySQL or run the full WordPress fixture. Manual and scheduled runs execute the full WordPress fixture smoke test with WP-CLI and MySQL so heavier runtime coverage stays available without slowing every pull request.

Manual dispatch can also run the Whisk Storybook build and accessibility audit for extended frontend confidence.

The semantic-release workflow remains release-gated. It still runs release readiness, the full WordPress fixture, a semantic-release dry run, and the final publish job only through the release workflow.

## License

The 2.x branch uses GPL-2.0-only metadata across npm, Composer, WordPress theme headers, and repository license files.
