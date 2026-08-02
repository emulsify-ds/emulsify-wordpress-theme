# ACF Local JSON

The parent theme supports ACF Local JSON as an optional child-theme convention. When ACF is active and the active child theme contains `config/acf-json`, Emulsify:

- uses `config/acf-json` as the ACF JSON save path;
- adds `config/acf-json` to ACF JSON load paths;
- keeps ACF's default load path unless a project explicitly removes it.

If ACF is not active, or the child theme does not contain `config/acf-json`, the service exits without changing ACF settings.

## Project Workflow

Generated child themes include `config/acf-json/.gitkeep` so the directory exists before field groups are created.

Project teams should commit ACF JSON files in this directory whenever field groups, option pages, post types, or taxonomies are managed through ACF. Treat these files as configuration:

- Create or edit field groups in the WordPress admin.
- Let ACF write JSON to `config/acf-json`.
- Review the changed JSON files in Git before committing.
- Commit the JSON with the code that depends on those fields.
- Pull the latest code and use ACF's sync UI when another environment needs updates.

Do not place project field-group JSON in the parent theme. The parent owns the convention; the child theme owns project configuration.

## Filters

Disable the integration:

```php
add_filter( 'emulsify_theme_acf_json_enabled', '__return_false' );
```

Change the save path:

```php
add_filter(
	'emulsify_theme_acf_json_save_path',
	function ( string $save_path, string $default_path ): string {
		return get_stylesheet_directory() . '/config/acf-json';
	},
	10,
	2
);
```

Remove ACF's default load path only when a project wants one source of truth:

```php
add_filter( 'emulsify_theme_acf_json_remove_default_load_path', '__return_true' );
```

Alter final load paths:

```php
add_filter(
	'emulsify_theme_acf_json_load_paths',
	function ( array $paths, string $save_path, array $incoming_paths ): array {
		$paths[] = WP_CONTENT_DIR . '/shared-acf-json';

		return $paths;
	},
	10,
	3
);
```
