# Asset loading

The parent theme loads built assets from the active child theme first, then parent fallback assets. This keeps project builds in the child theme while allowing the parent to provide reusable runtime behavior.

## Resolution order

Asset loading is manifest-first and scanner-backed. For each supported scope, the parent checks a valid child `dist/emulsify-assets.json` before a parent manifest. When no manifest exists, the manifest is invalid, or the manifest does not declare the requested scope, the recursive scanner remains the fallback.

Manifest data is memoized for the life of the service instance during a PHP request. Scanner fallback records are also memoized per theme-relative directory, so the CSS and JavaScript enqueue passes do not walk the same directory tree twice. Filters still receive the same extension-specific asset records they received before memoization.

## Scanner behavior

When no usable manifest is present, Emulsify WordPress scans these built asset directories:

- `dist/global`
- `dist/components`

Global frontend CSS and JavaScript are loaded from `dist/global`. Component CSS and JavaScript are loaded from `dist/components`.

`dist/global/editor` is reserved for block editor enhancement assets. Those files are loaded by the editor enhancement service on `enqueue_block_editor_assets` and are skipped by the generic global loader so editor-only files are not sent to normal frontend visitors.

Scanner asset versions use `filemtime()` so browsers receive updated files after a rebuild. If the child and parent themes both contain a built asset with the same relative path, the child asset wins. Assets are sorted by root priority and relative path before they are enqueued.

For ACF/Twig components, files declared in `*.component.json` `assets` metadata or keyed manifest block/component entries are treated as block-scoped assets and skipped by the global component scanner. Component files without scoped metadata still load through the global `dist/components` scanner.

## Manifest behavior

Projects can optionally ship `dist/emulsify-assets.json` to avoid recursive discovery during normal requests. The manifest is child-first: a valid child manifest is used before a parent manifest. If no manifest exists, if the manifest is invalid JSON, or if a scope is not declared, the current scanner path remains the fallback for that scope.

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

Block-specific entries are accepted under `blocks` and are used by ACF/Twig block registration when a matching block is rendered. Broad `components.css` and `components.js` entries still load globally. Keyed `components` or `blocks` entries are not auto-loaded on pages that do not render those blocks. If a manifest only declares keyed block/component entries, undeclared component files still use the scanner fallback.

## Performance

The scanner is simple and requires no build integration. A manifest is useful for larger projects because build tooling can write the exact asset list, hashes, and dependencies once, and PHP can usually avoid walking `dist/global` and `dist/components` during normal requests.

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

ACF/Twig block-scoped asset records pass through `emulsify_theme_acf_block_asset_records` before the block registration callback is attached.
