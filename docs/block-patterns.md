# Block patterns

The parent theme registers JSON block patterns from active child and parent theme `patterns` directories. Discovery is child-first and scans direct `patterns/*.json` files only, so a child theme can override a parent pattern file by using the same JSON filename. `patterns/categories.json` and `patterns/_categories.json` are reserved for optional category metadata and are not registered as block patterns.

If no pattern directory exists, or if WordPress pattern registration functions are unavailable, the service exits without changing editor behavior.

## JSON shape

Each pattern JSON file should include:

```json
{
  "name": "project/example-pattern",
  "title": "Example Pattern",
  "description": "Short editor-facing summary.",
  "categories": ["starter"],
  "keywords": ["example", "starter"],
  "postTypes": ["page"],
  "viewportWidth": 1200,
  "content": "<!-- wp:paragraph --><p>Pattern content.</p><!-- /wp:paragraph -->"
}
```

Required fields:

- `name`: Pattern name in `namespace/slug` form.
- `title`: Editor-facing pattern title.
- `content`: Serialized block markup.

Optional fields:

- `description`: Editor-facing summary.
- `categories`: Pattern category slugs. The parent registers discovered categories when WordPress supports `register_block_pattern_category()`.
- `keywords`: Inserter search terms.
- `postTypes`: Post type slugs where the pattern should appear.
- `viewportWidth`: Preview width used by the inserter.

Invalid JSON files, missing required fields, duplicate filenames, and duplicate pattern names are skipped safely. Diagnostics are logged only when `WP_DEBUG` is enabled.

## Category metadata

Categories discovered from pattern JSON receive a readable fallback label from the slug. Projects can refine labels and descriptions with `patterns/categories.json` or `patterns/_categories.json`:

```json
{
	"featured": {
		"label": "Featured",
		"description": "Featured content layouts."
	}
}
```

Child theme category metadata extends parent metadata and overrides matching fields. Category metadata files are optional; only categories referenced by registered patterns are registered.

## Filters

Use `emulsify_theme_pattern_directories` to add or replace scanned directories:

```php
add_filter(
	'emulsify_theme_pattern_directories',
	function ( array $directories ): array {
		$directories[] = array(
			'path'   => get_stylesheet_directory() . '/project-patterns',
			'source' => 'project',
		);

		return $directories;
	}
);
```

Use `emulsify_theme_pattern_data` to alter decoded JSON before validation:

```php
add_filter(
	'emulsify_theme_pattern_data',
	function ( array $data, array $file ): array {
		if ( 'landing.json' === $file['relative'] ) {
			$data['title'] = 'Landing Page';
		}

		return $data;
	},
	10,
	2
);
```

Use `emulsify_theme_pattern_categories` to alter discovered category registration args:

```php
add_filter(
	'emulsify_theme_pattern_categories',
	function ( array $categories ): array {
		$categories['starter']['label'] = 'Starter Layouts';

		return $categories;
	}
);
```

Use `emulsify_theme_pattern_args` to alter final registration args before `register_block_pattern()` runs:

```php
add_filter(
	'emulsify_theme_pattern_args',
	function ( array $args ): array {
		$args['keywords'][] = 'project';

		return $args;
	}
);
```

Generated child themes include an empty `patterns` placeholder, but no content patterns by default. If a project adds JSON patterns under `whisk/patterns`, the generator rewrites pattern names from the `whisk/` namespace to the generated child theme machine name.

See [Component recipes](component-recipes.md) for a short pattern example and guidance on when a pattern is a better fit than a custom block.
