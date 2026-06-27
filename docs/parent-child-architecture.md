# Parent and child theme architecture

Emulsify WordPress is split into a reusable parent theme and generated project child themes. The goal is to keep parent runtime behavior stable while allowing each project to own its implementation details.

## Parent theme responsibilities

The parent theme owns reusable runtime behavior:

- WordPress bootstrap code.
- Theme setup, menus, editor support, and image sizes.
- Timber integration and global context.
- Twig namespace registration and helper functions.
- Asset loading for built child and parent files.
- Optional ACF/Twig block registration.
- Optional native Gutenberg block registration.
- Minimal Timber fallback templates.

Most parent runtime code lives in `includes/`. The root `functions.php` should stay thin.

## Child theme responsibilities

Generated child themes own:

- Project components.
- Project Sass and JavaScript.
- Storybook stories and data fixtures.
- Built Vite output under `dist/`.
- Intentional template overrides.
- Project-specific filters or hooks.

The bundled Whisk starter is an example child theme. Real projects can generate a renamed child theme with WP-CLI.

## Template fallback model

The parent `templates/` directory contains complete minimal fallbacks. The generated child theme only ships a small example override at `whisk/templates/page.twig`.

Timber resolves the `@templates` namespace child-first:

1. Child theme `templates`.
2. Parent theme `templates`.

The parent-only `@emulsify-tpl` namespace points at parent templates. Use it when a child override needs to extend the parent fallback it is replacing.

## Component discovery priority

Built child theme components are discovered before built parent theme components. When a child and parent component use the same relative path under `dist/components`, the child component wins for both ACF/Twig `*.component.json` metadata and native `block.json` metadata.

Discovery is memoized for the current PHP request. ACF/Twig and native block registration share one filesystem scan without adding persistent cache invalidation problems.

## Runtime filters

Child themes and project plugins can extend parent behavior with focused WordPress filters:

- `emulsify_theme_asset_directories`
- `emulsify_theme_asset_files`
- `emulsify_theme_twig_namespaces`
- `emulsify_theme_context`
- `emulsify_theme_component_roots`
- `emulsify_theme_acf_block_metadata`
- `emulsify_theme_acf_block_args`
- `emulsify_theme_native_block_directories`
- `emulsify_theme_setup_options`

Use these filters for project-specific behavior before editing a parent class. Keep broad application logic in a project plugin when it is not theme-specific.

## Emulsify project metadata

The generated child theme includes `project.emulsify.json` with `"platform": "none"`. That is intentional. WordPress runtime support currently lives in this theme, while Emulsify Core does not yet provide a native WordPress adapter.
