# Editor enhancements

The Whisk starter includes optional block editor enhancement modules under `whisk/src/editor`. The parent theme enqueues built editor assets from `dist/global/editor` when a child theme builds them, but every module is disabled by default.

Enable modules from a child theme or project plugin with `emulsify_theme_editor_enhancements_config`:

```php
add_filter(
	'emulsify_theme_editor_enhancements_config',
	function ( array $config ): array {
		$config['columnsEqualHeight']['enabled'] = true;
		$config['fileCaption']['enabled']        = true;
		$config['embedVariations']['enabled']    = true;
		$config['placement']                     = array(
			'enabled'    => true,
			'blocks'     => array( 'acf/project-hero' ),
			'singleton'  => true,
			'requireTop' => true,
		);

		return $config;
	}
);
```

Run the child theme build after changing editor source:

```sh
npm --prefix web/app/themes/your-child-theme run build
```

## Columns equal height

`columnsEqualHeight` adds an inspector toggle to `core/columns`. When enabled, it stores the configured boolean attribute and adds the configured class to saved markup and the editor preview.

Useful options:

- `enabled`: turns the module on.
- `attribute`: stored block attribute. Default: `emulsifyEqualizeHeights`.
- `className`: saved/editor class. Default: `is-equal-height`.
- `label`, `panelTitle`, `helpEnabled`, `helpDisabled`: editor control text.

## File media captions

`fileCaption` adds an inspector toggle to `core/file`. The JavaScript stores a boolean attribute, and the parent PHP render hook appends the media library caption during rendering when the toggle is enabled.

Useful options:

- `enabled`: turns the module on.
- `attribute`: stored block attribute. Default: `emulsifyShowMediaCaption`.
- `blockClassName`: class added to the parsed File block attributes. Default: `has-media-caption`.
- `captionClassName`: class used on the injected caption paragraph. Default: `wp-block-file__media-caption`.

## Embed variations

`embedVariations` keeps `core/embed` available while hiding provider-specific embed variations from the inserter. Existing variations are unregistered after editor boot because WordPress does not expose a public API for mutating variation scope after registration.

Useful options:

- `enabled`: turns the module on.
- `allowedVariationNames`: optional variation names to keep visible.

## Singleton top placement

`placement` enforces one configured top-level block, optionally moving it to the top of the post and removing duplicates. This is useful for project-specific hero, alert, or page-header blocks without hard-coding those names in the parent theme.

Useful options:

- `enabled`: turns the module on.
- `blocks`: block names that represent the singleton block.
- `singleton`: prevents duplicates and sets block support `multiple` to false.
- `requireTop`: moves the first configured block to the top level at index 0.
- `notice`: snackbar text shown when enforcement changes the block list.

## Asset filters

The parent service discovers child assets first, then parent fallbacks. These filters are available for projects with custom build locations:

- `emulsify_theme_editor_enhancements_config`
- `emulsify_theme_editor_asset_directories`
- `emulsify_theme_editor_asset_files`
