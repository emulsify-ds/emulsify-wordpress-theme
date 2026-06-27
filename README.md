![Emulsify Design System](https://github.com/emulsify-ds/.github/blob/6bd435be881bd820bddfa05d88905efe29176a0a/assets/images/header.png)

# Emulsify WordPress

Emulsify WordPress 2.0 is a Timber-first WordPress parent theme for teams building component-driven sites with Emulsify Core 4, Vite, Storybook, and Twig.

The parent theme provides the stable WordPress runtime: theme setup, Timber bootstrapping, Twig namespaces and helpers, template fallbacks, asset loading, and optional block registration. Generated child themes provide the project layer: components, templates, source Sass and JavaScript, compiled assets, and site-specific overrides.

## Requirements

- WordPress 6.7 or newer.
- PHP 8.3 or newer.
- Composer 2.
- Node.js 24. The root release tooling expects `>=24.10`; generated child themes expect `>=24`.
- Timber 2, preferably installed with Composer.
- WP-CLI when running the full WordPress fixture smoke test locally or in CI.

## Bedrock And Timber

In a Bedrock project, install the parent theme at `web/app/themes/emulsify` and the generated child theme at `web/app/themes/whisk` or another project-specific child theme name. In a standard WordPress install, use `wp-content/themes/emulsify` and `wp-content/themes/whisk`.

Timber is required for frontend template rendering. This repository declares `timber/timber` in the parent theme `composer.json`, so a standalone theme install can run Composer inside the parent theme:

```sh
cd web/app/themes/emulsify
composer install
```

For Bedrock applications, it is also valid to require Timber from the application-level Composer project as long as WordPress loads that Composer autoloader before the theme renders. If Timber is missing, the theme shows an actionable admin notice and stops frontend rendering with a clear runtime error instead of failing later in Twig.

Activate the child theme, not the parent theme, for normal site work. The child theme header includes `Template: emulsify`, which tells WordPress to use Emulsify as the parent runtime.

## Parent And Child Themes

The `emulsify` parent theme owns reusable runtime behavior:

- `functions.php` stays thin and starts the namespaced classes in `includes/`.
- `includes/` registers WordPress theme supports, menus, images, editor support, `theme.json` support, Timber context, Twig helpers, assets, and block integrations.
- `templates/` provides minimal Timber template fallbacks for home, page, single, archive, search, author, password-protected content, comments, pagination, and 404 routes.
- `theme.json` provides editor settings and presets. Built CSS remains the responsibility of the child theme build.

The generated `whisk` child theme owns project implementation:

- `whisk/src/components` is the primary component source directory.
- `whisk/src/tokens.scss`, `whisk/src/foundation.scss`, and `whisk/src/layout.scss` are the Core 4 global style entry points.
- `whisk/templates` overrides parent Timber templates when a project needs custom markup.
- `whisk/dist/global` and `whisk/dist/components` contain built assets and block metadata after running the Vite build.
- `whisk/project.emulsify.json` uses `"platform": "none"` while WordPress-specific runtime support lives in this theme.

Root-level `components` directories are only a compatibility path for older projects. New work should start in `src/components`.

## Installing Dependencies

The root package is for release and quality checks around the parent theme:

```sh
npm ci --ignore-scripts
composer install
```

The generated child theme has its own frontend dependency tree:

```sh
cd whisk
npm install
```

Commit dependency lockfiles according to the consuming project policy. This repository keeps the generated starter light and validates it by installing dependencies during release checks.

## Core 4, Vite, And Storybook Commands

Run parent theme checks from the repository root:

| Command | Purpose |
| --- | --- |
| `npm run lint:php` | Lint all PHP files with `php -l`. |
| `npm run release:check` | Run metadata, release-readiness, helper, starter, and WordPress fixture checks. |
| `npm run publish-test -- --no-ci` | Run a local semantic-release dry run with debug output. |

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

The parent asset loader enqueues child theme files first, then parent fallbacks. Built global CSS is loaded from `dist/global`; component CSS and JavaScript are loaded from `dist/components`. Asset versions use `filemtime()` so browsers receive updated files after each build. The same styles are available in block editor previews through the WordPress block asset enqueue hook.

## Component Authoring With Twig

Author project components under `whisk/src/components`. Keep components small and presentation-focused so they can be reused from Timber templates, Storybook, ACF blocks, or native blocks.

A typical component folder can include Twig, Sass, JavaScript, Storybook stories, data fixtures, and optional block metadata:

```text
whisk/src/components/button/
  button.twig
  button.scss
  button.js
  button.stories.js
```

Timber templates can include components through the registered `@components` namespace:

```twig
{% include '@components/button/button.twig' with {
  text: post.title
} only %}
```

The theme registers Core-style Twig helpers for class and attribute handling:

```twig
<button {{ bem('button', ['primary']) }}>
  {{ text }}
</button>

<div {{ add_attributes({ class: ['foo'], 'data-component': 'example' }) }}>
  {{ content }}
</div>
```

Use `@templates` for child and parent template includes. The child theme template path is registered before the parent path so project templates override parent fallbacks naturally.

## Gutenberg And Block Paths

Timber components, ACF blocks, and native Gutenberg blocks are separate paths.

### Timber Components

Twig components are reusable rendering pieces. They do not become editor blocks automatically. Use them from Timber templates, Storybook, ACF render callbacks, or server-rendered native blocks when that is the right fit.

### Optional ACF/Twig Blocks

ACF is optional. When ACF is active, the theme looks in built component output under `dist/components` for folders that contain both a `*.component.json` metadata file and a matching Twig template. Matching templates can use the metadata filename or the component directory name.

The metadata is passed to `acf_register_block_type()`, and rendering happens through Timber with block data, ACF fields, preview state, and the normal global Timber context. If ACF is absent, this path is skipped without affecting the base theme.

### Native Gutenberg Blocks

Native blocks use the WordPress Block API. Add a `block.json` file to a component folder and build the child theme so the block folder is available under `dist/components`. The parent theme registers those folders with `register_block_type()`.

Use the native path for block editor APIs such as attributes, supports, transforms, editor scripts, view scripts, render callbacks, and block-specific assets. Keep native block metadata separate from ACF component metadata so the two registration systems remain predictable.

## Release And Versioning

This repository uses semantic-release with Conventional Commits. Releases are prepared from `main`, and Git tags use non-prefixed SemVer such as `2.0.0`.

Use commit messages that describe the public change:

- `fix: correct timber attribute helpers` creates a patch release.
- `feat: add native block registration` creates a minor release.
- `feat!: change generated child theme structure` or a `BREAKING CHANGE:` footer creates a major release.

Before publishing, run:

```sh
npm run release:check
npm run publish-test -- --no-ci
```

The release readiness check validates required files, release metadata coherence, Core 4/Vite starter expectations, Twig helper smoke coverage, absence of stale workflow references, and the WordPress fixture smoke path. CI runs the fixture smoke check with WP-CLI and a database service so parent and generated child theme rendering are tested together.

## Contributing

Read the [Code of Conduct](https://github.com/emulsify-ds/emulsify-wordpress-theme/blob/main/CODE_OF_CONDUCT.md) before contributing.

Use the issue and pull request templates in this repository. File bugs and feature requests at [emulsify-ds/emulsify-wordpress-theme](https://github.com/emulsify-ds/emulsify-wordpress-theme/issues).

## Author

Emulsify&reg; is a product of [Four Kitchens &mdash; We make BIG websites](https://fourkitchens.com).

### Contributors

<table>
<tr>
    <td align="center" style="word-wrap: break-word; width: 150.0; height: 150.0">
        <a href="https://github.com/callinmullaney">
            <img src="https://avatars.githubusercontent.com/u/369018?v=4" width="100;"  style="border-radius:50%;align-items:center;justify-content:center;overflow:hidden;padding-top:10px" alt="Callin Mullaney"/>
            <br />
            <sub style="font-size:14px"><b>Callin Mullaney</b></sub>
        </a>
    </td>
    <td align="center" style="word-wrap: break-word; width: 150.0; height: 150.0">
        <a href="https://github.com/mikeethedude">
            <img src="https://avatars.githubusercontent.com/u/15275301?v=4" width="100;"  style="border-radius:50%;align-items:center;justify-content:center;overflow:hidden;padding-top:10px" alt="Mike Goulding"/>
            <br />
            <sub style="font-size:14px"><b>Mike Goulding</b></sub>
        </a>
    </td>
</tr>
</table>
