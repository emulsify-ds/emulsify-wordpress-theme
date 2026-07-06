# Native Gutenberg blocks

Native Gutenberg blocks use the WordPress Block API. They are separate from Twig components and ACF/Twig blocks.

## Gutenberg and block paths

Add a `block.json` file to a component folder and build the child theme so the block folder is available under `dist/components`. The parent theme registers matching folders with `register_block_type()`.

The starter does not include an active native block example. Native blocks usually need project-specific editor scripts, attributes, supports, and block behavior, so projects should add them intentionally.

See [Component recipes](component-recipes.md) for a short `block.json` example and guidance on when to choose native blocks.

## Block assets

Native blocks should use standard `block.json` asset fields such as `style`, `script`, `viewScript`, `editorStyle`, `editorScript`, and `viewScriptModule`. The parent theme passes the block directory to `register_block_type()` and does not manually enqueue assets that WordPress can register from `block.json`.

Use `dist/emulsify-assets.json` or ACF/Twig component metadata only for ACF/Twig blocks. Native block asset loading should stay in native block metadata so WordPress can load assets only when the block is present.

## When to use native blocks

Use native blocks when the project needs block editor APIs such as:

- Attributes.
- Supports.
- Transforms.
- Editor scripts.
- View scripts.
- Server-side render callbacks.
- Block-specific assets.

Keep native block metadata separate from ACF component metadata so the two registration systems remain predictable.

## Discovery priority

Built child theme components are discovered before built parent theme components. When a child and parent native block use the same relative path or the same `block.json` `name`, the first discovered definition wins and lower-priority duplicates are skipped.

## Filter native block directories

Project code can alter the directories before registration:

```php
add_filter(
  'emulsify_theme_native_block_directories',
  function ( array $directories ): array {
    $directories[] = array(
      'path'          => get_stylesheet_directory() . '/custom-blocks/hero',
      'relative'      => 'hero',
      'source'        => 'project',
      'metadata_path' => get_stylesheet_directory() . '/custom-blocks/hero/block.json',
      'name'          => 'project/hero',
    );

    return $directories;
  }
);
```

Duplicate native block names are still checked after this filter runs.
