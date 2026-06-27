![Emulsify Design System](https://github.com/emulsify-ds/.github/blob/6bd435be881bd820bddfa05d88905efe29176a0a/assets/images/header.png)

# Emulsify WordPress

Emulsify WordPress 2.0.0 is a Timber-first WordPress parent theme for teams building component-driven sites with Emulsify Core 4, Vite, Storybook, and Twig.

The parent theme provides the WordPress runtime: theme setup, Timber bootstrapping, Twig namespaces and helpers, template fallbacks, asset loading, and optional block registration. Generated child themes provide the project layer: components, templates, source Sass and JavaScript, compiled assets, and site-specific overrides.

## Requirements

- WordPress 6.7 or newer.
- PHP 8.3 or newer.
- Composer 2.
- Node.js 24. Root release tooling expects `>=24.10`; generated child themes expect `>=24`.
- Timber 2, preferably installed with Composer.
- WP-CLI when generating child themes or running the full WordPress fixture smoke test.

## Quick install

Install the parent theme as `emulsify` and activate a child theme for project work. In Bedrock, that usually means:

```sh
web/app/themes/emulsify
web/app/themes/whisk
```

In a standard WordPress install, use:

```sh
wp-content/themes/emulsify
wp-content/themes/whisk
```

Install parent theme dependencies:

```sh
cd web/app/themes/emulsify
composer install
npm ci --ignore-scripts
```

Install the generated child theme frontend dependencies:

```sh
cd web/app/themes/whisk
npm install
```

Activate the child theme, not the parent theme. The child theme header includes `Template: emulsify`, which tells WordPress to use Emulsify as the parent runtime.

## Parent and child themes

The `emulsify` parent theme owns reusable runtime behavior:

- `includes/` contains the namespaced runtime classes.
- `templates/` provides minimal Timber fallback templates.
- `theme.json` provides editor settings and presets.
- `whisk/` is the generated starter child theme source.

The generated `whisk` child theme owns project implementation:

- `whisk/src/components` is the primary component source directory.
- `whisk/src/tokens.scss`, `whisk/src/foundation.scss`, and `whisk/src/layout.scss` are the Core 4 global style entry points.
- `whisk/templates/page.twig` is a small example override.
- `whisk/dist/global` and `whisk/dist/components` contain built assets after running Vite.
- `whisk/project.emulsify.json` uses `"platform": "wordpress"` so Core and CLI tooling can load the WordPress platform adapter.

## Bedrock and Timber

Timber is required for frontend template rendering. This repository declares `timber/timber` in the parent theme `composer.json`, so a standalone theme install can run Composer inside the parent theme.

For Bedrock applications, it is also valid to require Timber from the application-level Composer project as long as WordPress loads that Composer autoloader before the theme renders. If Timber is missing, the parent theme shows an actionable admin notice and stops frontend rendering with a clear runtime error.

## Basic commands

Run parent theme checks from the repository root:

| Command | Purpose |
| --- | --- |
| `npm run lint:php` | Lint all PHP files with `php -l`. |
| `npm run pr:check` | Run the practical pull request validation suite. |
| `npm run release:check` | Run release-readiness checks. |
| `npm run publish-test -- --no-ci` | Run a local semantic-release dry run. |

## Core 4, Vite, and Storybook commands

Run component development commands from the generated child theme:

| Command | Purpose |
| --- | --- |
| `npm run build` | Build Core 4 assets with Vite. |
| `npm run vite` | Watch and rebuild Vite assets. |
| `npm run storybook` | Start Storybook on port 6006. |
| `npm run develop` | Run the Vite watcher and Storybook together. |
| `npm run storybook-build` | Build assets and export a static Storybook. |
| `npm run lint` | Run JavaScript and Sass linting with the Core 4 config. |
| `npm run audit` | Run the Core migration/static audit. |
| `npm run audit:twig-stories` | Check Twig story compatibility. |
| `npm run a11y` | Build Storybook and run the Core accessibility check. |
| `npm run test` | Run Jest with `--passWithNoTests` for starter projects. |

## Generate a child theme

Generate a project child theme from the bundled Whisk starter with WP-CLI:

```sh
wp emulsify "Acme Site" --dry-run
wp emulsify "Acme Site" --machine-name=acme-site
wp emulsify "Acme Site" --machine-name=acme-site --force
wp emulsify "Acme Site" --machine-name=acme-site --activate
```

The generator copies `emulsify/whisk` to a sibling child theme directory, updates WordPress theme headers, package metadata, Emulsify project metadata, and visible starter labels, then optionally activates the generated child theme.

## Documentation

- [Upgrading from 1.x to 2.x](docs/upgrading-1x-to-2x.md)
- [Sister-project parity contract](docs/sister-project-parity.md)
- [Parent and child theme architecture](docs/parent-child-architecture.md)
- [Timber and Twig authoring](docs/timber-and-twig-authoring.md)
- [Emulsify Core 4 and Vite workflow](docs/core-4-vite-workflow.md)
- [ACF/Twig blocks](docs/acf-twig-blocks.md)
- [Native Gutenberg blocks](docs/native-gutenberg-blocks.md)
- [Asset loading](docs/asset-loading.md)
- [WP-CLI child theme generation](docs/wp-cli-child-theme-generation.md)
- [Release process](docs/release-process.md)

## License

Emulsify WordPress is licensed under GPL-2.0-only. See [LICENSE](LICENSE).

## Contributing

Read the [Code of Conduct](https://github.com/emulsify-ds/emulsify-wordpress-theme/blob/main/CODE_OF_CONDUCT.md) before contributing. File bugs and feature requests at [emulsify-ds/emulsify-wordpress-theme](https://github.com/emulsify-ds/emulsify-wordpress-theme/issues).

## Author

Emulsify&reg; is a product of [Four Kitchens &mdash; We make BIG websites](https://fourkitchens.com).
