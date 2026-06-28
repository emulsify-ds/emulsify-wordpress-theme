# Sister-project parity contract

Emulsify Wordpress is the WordPress sister project to Emulsify Drupal. The projects should feel familiar to teams moving between CMS platforms while preserving the runtime conventions each CMS expects.

This contract defines the shared Emulsify model first, then the intentional WordPress differences.

## Shared Emulsify contract

Emulsify Wordpress and Emulsify Drupal share the same project boundary:

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

Generated child themes are expected to own component source and built output. The selected Emulsify component system defines the project source structure:

- Whisk keeps an empty `src/components` placeholder for compatible systems, but it does not prescribe a component tree.
- Whisk does not ship default `tokens.scss`, `foundation.scss`, or `layout.scss` entrypoints.
- Storybook stories and data fixtures live with the component source chosen by the project.
- Built global assets are emitted under `dist/global`.
- Built component assets and block metadata are emitted under `dist/components`.

WordPress should load built child theme output before parent fallback output. A generated child theme can override parent components or templates intentionally, while the parent theme keeps minimal fallbacks available for routes and runtime services.

## Intentional WordPress differences

Emulsify Wordpress is not a Drupal runtime port. It keeps parity at the Emulsify project-model layer and uses WordPress-native integration where WordPress needs it.

### Timber runtime

Frontend rendering uses Timber. The parent theme owns Timber bootstrapping, global context, Twig namespace registration, route fallbacks, and Twig helper functions. Child themes supply project Twig templates and component source.

For new project component includes, prefer the generated child theme machine name from `project.emulsify.json`, using the general form `{% include "project_machine_name:component_name" %}`. The legacy `@components/component-name/component-name.twig` namespace remains supported for compatible component libraries, existing projects, shared templates, and migration work.

Custom legacy namespaces should use Emulsify Core's existing `variant.structureImplementations` entries in `project.emulsify.json`. Do not add Drupal `.info.yml` files to the WordPress starter for this purpose.

### Parent and child theme headers

WordPress theme identity lives in `style.css` headers. The parent theme is installed as `emulsify`, and generated child themes declare `Template: emulsify` so WordPress uses the parent runtime.

The generator updates the child theme `Theme Name`, `Text Domain`, `Template`, package metadata, and Emulsify project metadata for each generated project.

### theme.json surface

`theme.json` is the WordPress site and editor configuration surface. It carries editor settings, presets, and styles that WordPress and the Site Editor understand. This is separate from component source and should be treated as WordPress configuration, not as a replacement for Core 4 Sass and component assets.

Whisk does not include a child `theme.json` by default. Add one in a generated child theme when the project needs WordPress editor/global-style overrides; do not add an empty file just to mirror the parent.

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

Emulsify CLI uses the standalone `https://github.com/emulsify-ds/emulsify-wordpress-starter` repository for the same child theme layer. That starter is sourced from `whisk/`, runs `.cli/init.js` after CLI dependency installation, and keeps `Template: emulsify` so WordPress loads the parent runtime theme.

### Emulsify platform metadata

`project.emulsify.json` uses `"platform": "wordpress"` so Emulsify Core and Emulsify CLI tooling can load the WordPress platform adapter. WordPress runtime behavior still lives in this parent theme for the 2.x release line.

This file may also hold Emulsify Core metadata such as `variant.structureImplementations`. `theme.json` remains the WordPress site and editor configuration surface, not a component-library or Twig namespace registry.

## Parity guardrails

Use this contract when evaluating future changes:

- Keep reusable CMS runtime behavior in the parent theme.
- Keep project implementation in the generated child theme.
- Keep Whisk small enough to work as a starter.
- Keep generated child theme source aligned with Emulsify Core 4 conventions.
- Keep WordPress-specific behavior explicit rather than hiding it behind Drupal naming or assumptions.
- Keep `project.emulsify.json` on `"platform": "wordpress"` so generated child themes advertise the WordPress platform adapter.
