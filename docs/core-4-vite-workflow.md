# Emulsify Core 4 and Vite workflow

The generated child theme uses Emulsify Core 4, Vite, Storybook, Twig stories, Sass, and JavaScript. The parent theme does not build project assets directly.

## Project metadata

`whisk/project.emulsify.json` uses `"platform": "wordpress"` so Emulsify Core and Emulsify CLI tooling can load the WordPress platform adapter while this parent theme owns the reusable WordPress runtime.

## Source directories

The generated child theme uses:

- `src/components` for component source.
- `src/tokens.scss` for design tokens.
- `src/foundation.scss` for base styles.
- `src/layout.scss` for layout styles.
- `dist/global` for built global CSS.
- `dist/components` for built component assets and block metadata.

## Core 4, Vite, and Storybook commands

Run these from the generated child theme directory:

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

## Example component

The Whisk starter includes a minimal button component:

```text
whisk/src/components/button/
  button.twig
  button.scss
  button.data.json
  button.stories.js
  button.component.json
```

This is intentionally small. It demonstrates the workflow without trying to be a complete design system.

For new project component includes, prefer the generated child theme machine name from `project.emulsify.json`: `{% include "whisk:button" %}`. The legacy `@components/button/button.twig` namespace remains supported for existing projects, shared templates, and migration work.

## Build output

The parent theme loads built files from the active child theme first, then parent fallbacks. Build the child theme before expecting component styles, JavaScript, ACF/Twig block metadata, or native block metadata to appear in WordPress.
