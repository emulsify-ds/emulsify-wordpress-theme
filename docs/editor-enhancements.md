# Editor enhancements

The parent theme can enqueue project-owned editor assets from `dist/global/editor` and pass shared configuration to those assets. Whisk does not ship editor source modules because the selected Emulsify component system and project requirements should own editor JavaScript.

Every built editor script receives `window.emulsifyEditorEnhancements` from the `emulsify_theme_editor_enhancements_config` filter. Defaults keep every module disabled:

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

Run the child theme build after adding project editor source:

```sh
npm --prefix web/app/themes/your-child-theme run build
```

The module names below are a stable configuration contract and documentation-only starter guidance. Projects that want these behaviors should implement their own editor source or install a component/editor package that consumes this config.

## Columns equal height

`columnsEqualHeight` is intended for a project editor module that adds an inspector toggle to `core/columns`. A matching implementation should store the configured boolean attribute and add the configured class to saved markup and the editor preview.

Useful options:

- `enabled`: turns the module on.
- `attribute`: stored block attribute. Default: `emulsifyEqualizeHeights`.
- `className`: saved/editor class. Default: `is-equal-height`.
- `label`, `panelTitle`, `helpEnabled`, `helpDisabled`: editor control text.

## File media captions

`fileCaption` is intended for a project editor module that adds an inspector toggle to `core/file`. When the configured attribute exists on a rendered File block, the parent PHP render hook can add the media library caption during rendering.

Useful options:

- `enabled`: turns the module on.
- `attribute`: stored block attribute. Default: `emulsifyShowMediaCaption`.
- `blockClassName`: class added to the parsed File block attributes. Default: `has-media-caption`.
- `captionClassName`: class used on the injected caption paragraph. Default: `wp-block-file__media-caption`.

## Embed variations

`embedVariations` is intended for a project editor module that keeps `core/embed` available while hiding provider-specific embed variations from the inserter.

Useful options:

- `enabled`: turns the module on.
- `allowedVariationNames`: optional variation names to keep visible.

## Singleton top placement

`placement` is intended for a project editor module that enforces one configured top-level block, optionally moving it to the top of the post and removing duplicates. This is useful for project-specific hero, alert, or page-header blocks without hard-coding those names in the parent theme.

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
