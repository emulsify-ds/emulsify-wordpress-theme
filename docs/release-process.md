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
- Twig attribute helper smoke test.
- Child theme generator smoke test.
- Component locator smoke test.
- Parent theme filter smoke test.
- Whisk dependency installation.
- Whisk Core 4/Vite build.

`release:check` adds static release-readiness checks and the full WordPress fixture smoke path. The fixture requires WP-CLI and a database. If `wp` is not available locally, the fixture is skipped with a clear message.

## CI

Pull requests run the practical validation suite without MySQL or WP-CLI. The full WordPress fixture smoke test remains release-only because it needs a heavier WordPress runtime.

## License

The 2.x branch uses GPL-2.0-only metadata across npm, Composer, WordPress theme headers, and repository license files.
