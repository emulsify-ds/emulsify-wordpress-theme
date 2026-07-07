# Editor policy

The parent theme includes an `Editor\Policy` coordinator for project-specific block editor governance. It is intentionally no-op by default, so activating the parent theme does not change allowed blocks, visible patterns, user-created pattern behavior, `wp_block` capabilities, or registered block supports unless a child theme or project plugin configures the policy with filters.

## Configure policy options

Use `emulsify_theme_editor_policy_options` from a child theme or project plugin:

```php
add_filter(
	'emulsify_theme_editor_policy_options',
	function ( array $options ): array {
		$options['allowed_block_types'] = array(
			'default'      => array(
				'core/heading',
				'core/paragraph',
				'core/image',
			),
			'by_post_type' => array(
				'landing_page' => array(
					'acf/project-hero',
					'acf/project-card-grid',
				),
			),
		);

		$options['auto_allow_pattern_blocks']            = true;
		$options['pattern_namespaces']                   = array( 'project' );
		$options['disable_user_patterns_for_non_admins'] = true;
		$options['restrict_wp_block_creation']           = true;
		$options['wp_block_create_capability']           = 'manage_options';
		$options['block_support_overrides']              = array(
			'*' => array(
				'supports' => array(
					'styles' => false,
				),
			),
		);

		return $options;
	}
);
```

`allowed_block_types` may be a single list for every post type, or an array with `default` and `by_post_type` keys. Post-type entries are merged with the default list. If WordPress or another plugin has already returned an allowed-block array, Emulsify merges with it by default; set `merge_allowed_block_types` to `false` to use only the configured list.

When `auto_allow_pattern_blocks` is enabled, block names found in JSON files under `patterns/` are added to the configured allow list. Comment shorthand such as `<!-- wp:paragraph -->` is normalized to `core/paragraph`.

## Fine-tune allowed blocks

Use `emulsify_theme_allowed_block_types` when the final list needs request-aware logic:

```php
add_filter(
	'emulsify_theme_allowed_block_types',
	function ( ?array $blocks, string $post_type ): ?array {
		if ( 'event' === $post_type ) {
			$blocks[] = 'core/embed';
		}

		return $blocks;
	},
	10,
	2
);
```

Return `null` to keep WordPress default behavior for that editor context.

## Limit visible patterns

Use `emulsify_theme_pattern_namespaces` to keep only project-owned pattern namespaces in the inserter:

```php
add_filter(
	'emulsify_theme_pattern_namespaces',
	function ( array $namespaces ): array {
		$namespaces[] = 'project';

		return $namespaces;
	}
);
```

If no namespaces are configured, Emulsify leaves the pattern list untouched.

## Override block supports and styles

Use `emulsify_theme_block_support_overrides` or the `block_support_overrides` option to change supports/styles for all blocks or a specific block. Overrides run for both `block_type_metadata_settings` and `register_block_type_args`.

```php
add_filter(
	'emulsify_theme_block_support_overrides',
	function ( array $overrides, string $block_name, array $settings, string $source ): array {
		$overrides['*']['supports']['styles'] = false;

		if ( 'core/button' === $block_name && 'register_block_type_args' === $source ) {
			$overrides['core/button']['styles'] = array();
		}

		return $overrides;
	},
	10,
	4
);
```

Keep role and capability grants in the child theme or a project plugin. The parent theme can point `wp_block` creation at a capability, but it does not mutate site roles.
