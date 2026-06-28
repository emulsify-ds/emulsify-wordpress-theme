# Asset loading

The parent theme loads built assets from the active child theme first, then parent fallback assets. This keeps project builds in the child theme while allowing the parent to provide reusable runtime behavior.

## Asset directories

The default built asset directories are:

- `dist/global`
- `dist/components`

Global CSS is loaded from `dist/global`. Component CSS and JavaScript are loaded from `dist/components`.

`dist/global/editor` is reserved for block editor enhancement assets. Those files are loaded by the editor enhancement service on `enqueue_block_editor_assets` and are skipped by the generic global style loader so editor-only CSS is not sent to normal frontend visitors.

## Enqueue behavior

The parent theme enqueues styles for both the frontend and block editor previews through WordPress block asset hooks. Frontend component JavaScript is loaded from built component output.

Asset versions use `filemtime()` so browsers receive updated files after a rebuild.

## Child-first priority

If the child and parent themes both contain a built asset with the same relative path, the child asset wins. Assets are sorted by root priority and relative path before they are enqueued.

## Asset filters

Use `emulsify_theme_asset_directories` to add or adjust roots:

```php
add_filter(
  'emulsify_theme_asset_directories',
  function ( array $directories, string $directory ): array {
    if ( 'dist/global' === $directory ) {
      $directories[] = array(
        'path'     => get_stylesheet_directory() . '/project-dist/global',
        'priority' => 0,
        'source'   => 'project',
        'uri'      => get_stylesheet_directory_uri() . '/project-dist/global',
      );
    }

    return $directories;
  },
  10,
  2
);
```

Use `emulsify_theme_asset_files` when a project needs to add, remove, or reorder individual files before enqueueing. Keep filtered records in the same shape as the parent records: `path`, `priority`, `relative`, `uri`, and `version`.
