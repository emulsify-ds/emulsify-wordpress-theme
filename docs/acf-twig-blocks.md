# ACF/Twig blocks

ACF/Twig blocks are optional. If ACF is not active, this registration path is skipped without affecting the base theme.

## How discovery works

The parent theme scans built component output under `dist/components`. A component can register as an ACF/Twig block when its built folder contains:

- A `*.component.json` metadata file.
- A matching Twig template.

The Twig template can match the metadata filename or the component directory name.

## Example

Whisk does not include an active ACF/Twig block example. A project component system can add metadata like this and build it into `dist/components`:

```text
src/components/example-card/example-card.component.json
```

After the child theme build, that metadata might be available at:

```text
dist/components/example-card/example-card.component.json
dist/components/example-card/example-card.twig
```

This documentation example proves the shape, but it does not ship project field groups or content model assumptions.

## Registration

The parent theme reads the component metadata, merges default block arguments, and calls `acf_register_block_type()`. Rendering happens through Timber with block data, ACF fields, preview state, and the normal global Timber context.

For ACF/Twig component metadata, use an ACF-safe `name` such as `emulsify-hero` rather than a namespaced native block name such as `project/hero`. ACF's PHP registration API receives an un-namespaced slug and WordPress exposes the editor block under the `acf/` namespace. The parent normalizes accidental slashes or other unsupported characters to dashes before duplicate checks and registration.

The final registration arguments can be adjusted with:

```php
add_filter(
  'emulsify_theme_acf_block_args',
  function ( array $args, array $component ): array {
    if ( 'example-card' === $component['relative'] ) {
      $args['category'] = 'design';
    }

    return $args;
  },
  10,
  2
);
```

Component metadata can be adjusted before defaults are merged with `emulsify_theme_acf_block_metadata`.

## Duplicate handling

The child theme is scanned before the parent theme. Duplicate component paths, duplicate component slugs, and duplicate final ACF block `name` values are skipped instead of being registered twice.

When `WP_DEBUG` is enabled, skipped duplicates are logged and shown as admin-only notices to users who can edit themes. Normal frontend visitors do not see duplicate diagnostics.
