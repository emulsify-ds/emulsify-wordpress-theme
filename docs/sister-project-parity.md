# Sister-project parity contract

Emulsify WordPress is the WordPress sister project to Emulsify Drupal. The projects should feel familiar to teams moving between CMS platforms while preserving the runtime conventions each CMS expects.

This contract defines the shared Emulsify model first, then the intentional WordPress differences.

## Shared Emulsify contract

Emulsify WordPress and Emulsify Drupal share the same project boundary:

- The parent theme owns reusable CMS runtime behavior.
- The generated child theme owns project implementation.
- Whisk is the starter used to generate real project child themes.
- Emulsify Core 4 provides the component workflow.
- Vite builds frontend assets.
- Storybook presents component examples.
- Twig is the component template language.
- Node 24 is the expected JavaScript runtime for generated child theme work.

The parent runtime should stay reusable and predictable. It provides CMS integration, fallback rendering, component discovery, asset loading, and extension points. Project-specific styling, components, stories, data fixtures, templates, and behavior belong in the generated child theme or in project plugins when the behavior is not theme-specific.

## Generated source and assets

Generated child themes are expected to own component source and built output:

- `src/components` is the primary component source directory.
- `src/tokens.scss`, `src/foundation.scss`, and `src/layout.scss` are the Core 4 global style entry points.
- Storybook stories and data fixtures live with the component source.
- Built global assets are emitted under `dist/global`.
- Built component assets and block metadata are emitted under `dist/components`.

WordPress should load built child theme output before parent fallback output. A generated child theme can override parent components or templates intentionally, while the parent theme keeps minimal fallbacks available for routes and runtime services.

## Intentional WordPress differences

Emulsify WordPress is not a Drupal runtime port. It keeps parity at the Emulsify project-model layer and uses WordPress-native integration where WordPress needs it.

### Timber runtime

Frontend rendering uses Timber. The parent theme owns Timber bootstrapping, global context, Twig namespace registration, route fallbacks, and Twig helper functions. Child themes supply project Twig templates and component source.

For new project component includes, prefer the generated child theme machine name from `project.emulsify.json`, such as `{% include "whisk:button" %}`. The legacy `@components/button/button.twig` namespace remains supported for existing projects, shared templates, and migration work.

### Parent and child theme headers

WordPress theme identity lives in `style.css` headers. The parent theme is installed as `emulsify`, and generated child themes declare `Template: emulsify` so WordPress uses the parent runtime.

The generator updates the child theme `Theme Name`, `Text Domain`, `Template`, package metadata, and Emulsify project metadata for each generated project.

### theme.json surface

`theme.json` is the WordPress site and editor configuration surface. It carries editor settings, presets, and styles that WordPress and the Site Editor understand. This is separate from component source and should be treated as WordPress configuration, not as a replacement for Core 4 Sass and component assets.

### ACF/Twig block registration

ACF/Twig block registration is an optional WordPress integration. Built component folders can include `*.component.json` metadata and a Twig template. When ACF is available, the parent theme registers those components with `acf_register_block_type()` and renders them through Timber.

### Native block.json registration

Native Gutenberg blocks use WordPress `block.json` metadata. Projects add `block.json` intentionally when they need native editor APIs such as attributes, supports, editor scripts, view scripts, transforms, or dynamic rendering. The parent theme registers discovered built block folders with `register_block_type()`.

### WP-CLI child theme generation

WordPress project generation is handled by the parent theme's WP-CLI command:

```sh
wp emulsify "Acme Site" --machine-name=acme-site
```

The command copies Whisk to a sibling child theme and performs targeted metadata updates. It avoids broad recursive string replacement so generated projects keep predictable WordPress headers, package metadata, and Emulsify project metadata.

### Emulsify platform metadata

`project.emulsify.json` uses `"platform": "wordpress"` so Emulsify Core and Emulsify CLI tooling can load the WordPress platform adapter. WordPress runtime behavior still lives in this parent theme for the 2.x release line.

## Parity guardrails

Use this contract when evaluating future changes:

- Keep reusable CMS runtime behavior in the parent theme.
- Keep project implementation in the generated child theme.
- Keep Whisk small enough to work as a starter.
- Keep generated child theme source aligned with Emulsify Core 4 conventions.
- Keep WordPress-specific behavior explicit rather than hiding it behind Drupal naming or assumptions.
- Keep `project.emulsify.json` on `"platform": "wordpress"` so generated child themes advertise the WordPress platform adapter.
