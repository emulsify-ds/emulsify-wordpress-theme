# Asset loading

The parent theme loads built assets from the active child theme first, then parent fallback assets. This keeps project builds in the child theme while allowing the parent to provide reusable runtime behavior.

## Scanner behavior

When no usable manifest is present, Emulsify WordPress scans these built asset directories:

- `dist/global`
- `dist/components`

Global frontend CSS and JavaScript are loaded from `dist/global`. Component CSS and JavaScript are loaded from `dist/components`.

`dist/global/editor` is reserved for block editor enhancement assets. Those files are loaded by the editor enhancement service on `enqueue_block_editor_assets` and are skipped by the generic global loader so editor-only files are not sent to normal frontend visitors.

Scanner asset versions use `filemtime()` so browsers receive updated files after a rebuild. If the child and parent themes both contain a built asset with the same relative path, the child asset wins. Assets are sorted by root priority and relative path before they are enqueued.

## Manifest behavior

Projects can optionally ship `dist/emulsify-assets.json` to avoid recursive discovery on every request. The manifest is child-first: a valid child manifest is used before a parent manifest. If no manifest exists, if the manifest is invalid JSON, or if a scope is not declared, the current scanner path remains the fallback for that scope.

Manifest entry paths are relative to the manifest directory. For `dist/emulsify-assets.json`, use paths such as `global/app.css` or `components/card.js`.

Example:

```json
{
	"assets": {
		"global": {
			"css": [
				{
					"path": "global/app.css",
					"version": "app-css-123",
					"dependencies": ["wp-block-library"]
				}
			],
			"js": [
				{
					"path": "global/app.js",
					"hash": "app-js-123",
					"module": false,
					"dependencies": ["jquery"]
				}
			]
		},
		"editor": {
			"css": [{ "path": "global/editor/editor.css" }],
			"js": [{ "path": "global/editor/editor.js" }]
		},
		"components": {
			"css": [{ "path": "components/card.css" }],
			"js": [{ "path": "components/card.js", "module": true }]
		},
		"blocks": {
			"emulsify/card": {
				"css": [{ "path": "components/card/block.css" }],
				"js": [{ "path": "components/card/block.js", "module": true }]
			}
		}
	}
}
```

Manifest records may include:

- `path`, or `file`, `src`, or `href`
- `relative` for handle generation when it should differ from `path`
- `version` or `hash`
- `dependencies` or `deps`
- `module` or `"type": "module"` for frontend scripts

Block-specific entries are accepted under `blocks` and are enqueued with component assets today. That keeps the manifest format ready for more precise block-aware loading later without making it a 2.0 requirement.

## Performance

The scanner is simple and requires no build integration. A manifest is useful for larger projects because build tooling can write the exact asset list, hashes, and dependencies once, and PHP can avoid walking `dist/global` and `dist/components` on every request.

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

Use `emulsify_theme_asset_manifest_path` to change the theme-relative manifest path:

```php
add_filter(
  'emulsify_theme_asset_manifest_path',
  function ( string $path ): string {
    return 'dist/custom-assets.json';
  }
);
```

Use `emulsify_theme_asset_manifest_data` to adjust parsed manifest data before records are created:

```php
add_filter(
  'emulsify_theme_asset_manifest_data',
  function ( array $data, array $manifest ): array {
    $data['assets']['global']['css'][0]['version'] = 'project-build';

    return $data;
  },
  10,
  2
);
```

Final frontend asset records still pass through `emulsify_theme_asset_files`. Editor asset records pass through `emulsify_theme_editor_asset_files`.
