<?php
/**
 * Configures optional block editor governance.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Editor;

/**
 * Parent-theme block editor policy hooks.
 */
final class Policy {

	/**
	 * Registers block editor policy hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'allowed_block_types_all', array( $this, 'allowed_block_types' ), 20, 2 );
		add_filter( 'block_editor_settings_all', array( $this, 'block_editor_settings' ), 20, 2 );
		add_filter( 'register_post_type_args', array( $this, 'post_type_args' ), 10, 2 );
		add_filter( 'block_type_metadata_settings', array( $this, 'block_type_metadata_settings' ), 20, 2 );
		add_filter( 'register_block_type_args', array( $this, 'register_block_type_args' ), 20, 2 );
	}

	/**
	 * Filters allowed block types for the current editor context.
	 *
	 * @param array|bool $allowed_block_types Existing WordPress allowed block types value.
	 * @param mixed      $context             Block editor context.
	 * @return array|bool Filtered allowed block types.
	 */
	public function allowed_block_types( $allowed_block_types, $context ) {
		$options   = $this->options( $context );
		$post_type = $this->post_type( $context );
		$blocks    = $this->configured_allowed_block_types( $options['allowed_block_types'] ?? null, $post_type );

		/**
		 * Filters the resolved allowed block type list before WordPress receives it.
		 *
		 * Return null to preserve the incoming WordPress value. Return an array
		 * of block names to enable an allow list for the current editor context.
		 *
		 * @param array|null $blocks              Resolved configured block names, or null for no policy.
		 * @param string     $post_type           Current post type when available.
		 * @param mixed      $context             Block editor context.
		 * @param array|bool $allowed_block_types Incoming WordPress allowed block types value.
		 * @param array      $options             Editor policy options.
		 */
		$filtered = apply_filters( 'emulsify_theme_allowed_block_types', $blocks, $post_type, $context, $allowed_block_types, $options );

		if ( null === $filtered || ! is_array( $filtered ) ) {
			// Null is the "no opinion" value. Returning the incoming WordPress value
			// preserves default editor behavior unless a child theme configures a policy.
			return $allowed_block_types;
		}

		$blocks = $this->normalize_block_names( $filtered );

		if ( ! empty( $options['auto_allow_pattern_blocks'] ) ) {
			// Pattern JSON can reference supporting blocks that are easy to forget
			// in a manual allow list. This opt-in merge prevents configured patterns
			// from becoming impossible to insert.
			$blocks = array_merge( $blocks, $this->pattern_block_names( $options ) );
		}

		if ( is_array( $allowed_block_types ) && ! empty( $options['merge_allowed_block_types'] ) ) {
			$blocks = array_merge( $allowed_block_types, $blocks );
		}

		return array_values( array_unique( $this->normalize_block_names( $blocks ) ) );
	}

	/**
	 * Filters block editor settings for optional pattern governance.
	 *
	 * @param array $settings Block editor settings.
	 * @param mixed $context  Block editor context.
	 * @return array Filtered settings.
	 */
	public function block_editor_settings( array $settings, $context = null ): array {
		$options    = $this->options( $context );
		$namespaces = $this->pattern_namespaces( $options, $settings, $context );

		if ( ! empty( $namespaces ) ) {
			$settings['blockPatterns'] = $this->filter_block_patterns( $settings['blockPatterns'] ?? null, $namespaces );
		}

		$disable_user_patterns = ! empty( $options['disable_user_patterns_for_non_admins'] );

		if ( $disable_user_patterns && ! $this->current_user_can( (string) $options['admin_capability'] ) ) {
			// WordPress still registers project/theme patterns; this only hides the
			// user-created pattern UI for users below the configured capability.
			$settings['enableUserPatterns'] = false;
		}

		return $settings;
	}

	/**
	 * Optionally changes the creation capability for user-created patterns.
	 *
	 * @param array  $args      Post type registration arguments.
	 * @param string $post_type Post type slug.
	 * @return array Filtered post type arguments.
	 */
	public function post_type_args( array $args, string $post_type ): array {
		if ( 'wp_block' !== $post_type ) {
			return $args;
		}

		$options = $this->options();

		if ( empty( $options['restrict_wp_block_creation'] ) ) {
			return $args;
		}

		$capability = $this->capability( $options['wp_block_create_capability'] ?? '' );

		if ( '' === $capability ) {
			return $args;
		}

		$args['map_meta_cap']                 = true;
		$args['capabilities']                 = isset( $args['capabilities'] ) && is_array( $args['capabilities'] ) ? $args['capabilities'] : array();
		$args['capabilities']['create_posts'] = $capability;

		return $args;
	}

	/**
	 * Applies configured support/style overrides to metadata-derived settings.
	 *
	 * @param array $settings Block type metadata settings.
	 * @param array $metadata Block metadata.
	 * @return array Filtered metadata settings.
	 */
	public function block_type_metadata_settings( array $settings, array $metadata ): array {
		$block_name = isset( $metadata['name'] ) && is_scalar( $metadata['name'] ) ? (string) $metadata['name'] : '';

		return $this->apply_block_support_overrides( $settings, $block_name, 'block_type_metadata_settings' );
	}

	/**
	 * Applies configured support/style overrides to final block registration args.
	 *
	 * @param array  $args       Block type registration arguments.
	 * @param string $block_type Block type name.
	 * @return array Filtered block type registration arguments.
	 */
	public function register_block_type_args( array $args, string $block_type ): array {
		return $this->apply_block_support_overrides( $args, $block_type, 'register_block_type_args' );
	}

	/**
	 * Gets editor policy options.
	 *
	 * @param mixed $context Optional editor context.
	 * @return array Editor policy options.
	 */
	private function options( $context = null ): array {
		$options = array(
			'allowed_block_types'                 => null,
			'merge_allowed_block_types'           => true,
			'auto_allow_pattern_blocks'           => false,
			'pattern_directories'                 => null,
			'pattern_namespaces'                  => array(),
			'disable_user_patterns_for_non_admins' => false,
			'admin_capability'                    => 'manage_options',
			'restrict_wp_block_creation'          => false,
			'wp_block_create_capability'          => 'manage_options',
			'block_support_overrides'             => array(),
		);

		/**
		 * Filters block editor policy options.
		 *
		 * Defaults preserve the parent theme's current behavior. Child themes can
		 * opt into individual policies by returning changed option values.
		 *
		 * @param array $options Editor policy options.
		 * @param mixed $context Optional editor context.
		 */
		$filtered = apply_filters( 'emulsify_theme_editor_policy_options', $options, $context );

		if ( is_array( $filtered ) ) {
			$options = array_merge( $options, $filtered );
		}

		$options['admin_capability']           = $this->capability( $options['admin_capability'] ?? 'manage_options' );
		$options['wp_block_create_capability'] = $this->capability( $options['wp_block_create_capability'] ?? 'manage_options' );

		return $options;
	}

	/**
	 * Resolves configured allowed blocks for a post type.
	 *
	 * @param mixed  $config    Allowed block type configuration.
	 * @param string $post_type Post type when available.
	 * @return array|null Block names, or null when no policy is configured.
	 */
	private function configured_allowed_block_types( $config, string $post_type ): ?array {
		if ( ! is_array( $config ) ) {
			return null;
		}

		if ( array_is_list( $config ) ) {
			return $config;
		}

		$blocks     = array();
		$configured = false;

		foreach ( array( 'default', '*' ) as $key ) {
			if ( array_key_exists( $key, $config ) && is_array( $config[ $key ] ) ) {
				$blocks     = array_merge( $blocks, $config[ $key ] );
				$configured = true;
			}
		}

		foreach ( array( 'by_post_type', 'post_types' ) as $group_key ) {
			if ( '' === $post_type || ! isset( $config[ $group_key ] ) || ! is_array( $config[ $group_key ] ) ) {
				continue;
			}

			$post_type_blocks = array_key_exists( $post_type, $config[ $group_key ] ) ? $config[ $group_key ][ $post_type ] : null;

			if ( is_array( $post_type_blocks ) ) {
				$blocks     = array_merge( $blocks, $post_type_blocks );
				$configured = true;
			}
		}

		if ( '' !== $post_type && array_key_exists( $post_type, $config ) && is_array( $config[ $post_type ] ) ) {
			$blocks     = array_merge( $blocks, $config[ $post_type ] );
			$configured = true;
		}

		return $configured ? $blocks : null;
	}

	/**
	 * Gets the post type from a block editor context.
	 *
	 * @param mixed $context Block editor context.
	 * @return string Post type or an empty string.
	 */
	private function post_type( $context ): string {
		if ( is_object( $context ) && isset( $context->post ) ) {
			if ( function_exists( 'get_post_type' ) ) {
				$post_type = get_post_type( $context->post );

				return is_string( $post_type ) ? $post_type : '';
			}

			if ( is_object( $context->post ) && isset( $context->post->post_type ) && is_scalar( $context->post->post_type ) ) {
				return (string) $context->post->post_type;
			}
		}

		if ( is_object( $context ) && isset( $context->post_type ) && is_scalar( $context->post_type ) ) {
			return (string) $context->post_type;
		}

		return '';
	}

	/**
	 * Gets configured block pattern namespaces.
	 *
	 * @param array $options  Editor policy options.
	 * @param array $settings Block editor settings.
	 * @param mixed $context  Block editor context.
	 * @return array Pattern namespaces.
	 */
	private function pattern_namespaces( array $options, array $settings, $context ): array {
		$namespaces = isset( $options['pattern_namespaces'] ) && is_array( $options['pattern_namespaces'] )
			? $options['pattern_namespaces']
			: array();

		/**
		 * Filters the block pattern namespaces that should stay visible.
		 *
		 * Return an empty array to preserve the default pattern list.
		 *
		 * @param array $namespaces Pattern namespaces, without trailing slashes.
		 * @param array $settings   Block editor settings.
		 * @param mixed $context    Block editor context.
		 * @param array $options    Editor policy options.
		 */
		$filtered = apply_filters( 'emulsify_theme_pattern_namespaces', $namespaces, $settings, $context, $options );

		if ( is_array( $filtered ) ) {
			$namespaces = $filtered;
		}

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $namespace ): string {
							return is_scalar( $namespace ) ? trim( strtolower( (string) $namespace ), " \t\n\r\0\x0B/" ) : '';
						},
						$namespaces
					)
				)
			)
		);
	}

	/**
	 * Filters visible block pattern records by namespace.
	 *
	 * @param mixed $patterns   Block pattern records.
	 * @param array $namespaces Allowed namespaces.
	 * @return array Filtered block pattern records.
	 */
	private function filter_block_patterns( $patterns, array $namespaces ): array {
		if ( ! is_array( $patterns ) && class_exists( '\WP_Block_Patterns_Registry' ) ) {
			$patterns = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();
		}

		if ( ! is_array( $patterns ) ) {
			return array();
		}

		$keep = array();

		foreach ( $patterns as $key => $pattern ) {
			$name = is_string( $key ) ? $key : '';

			if ( is_array( $pattern ) && isset( $pattern['name'] ) && is_scalar( $pattern['name'] ) ) {
				$name = (string) $pattern['name'];
			}

			if ( $this->matches_namespace( $name, $namespaces ) ) {
				$keep[] = $pattern;
			}
		}

		return $keep;
	}

	/**
	 * Checks whether a pattern name is in an allowed namespace.
	 *
	 * @param string $name       Pattern name.
	 * @param array  $namespaces Allowed namespaces.
	 * @return bool TRUE when the namespace is allowed.
	 */
	private function matches_namespace( string $name, array $namespaces ): bool {
		$name = strtolower( trim( $name ) );

		foreach ( $namespaces as $namespace ) {
			if ( 0 === strpos( $name, $namespace . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets block names referenced by configured pattern JSON files.
	 *
	 * @param array $options Editor policy options.
	 * @return array Block names.
	 */
	private function pattern_block_names( array $options ): array {
		$names = array();

		foreach ( $this->pattern_json_files( $options ) as $file ) {
			$contents = file_get_contents( $file );

			if ( ! is_string( $contents ) || '' === trim( $contents ) ) {
				continue;
			}

			$data = json_decode( $contents, true );

			if ( ! is_array( $data ) || empty( $data['content'] ) || ! is_string( $data['content'] ) ) {
				continue;
			}

			$names = array_merge( $names, $this->block_names_from_content( $data['content'] ) );
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Gets pattern JSON files from configured pattern directories.
	 *
	 * @param array $options Editor policy options.
	 * @return array Absolute file paths.
	 */
	private function pattern_json_files( array $options ): array {
		$directories = isset( $options['pattern_directories'] ) && is_array( $options['pattern_directories'] )
			? $options['pattern_directories']
			: $this->default_pattern_directories();
		$files       = array();
		$seen        = array();

		foreach ( $directories as $directory ) {
			if ( ! is_scalar( $directory ) ) {
				continue;
			}

			$path = rtrim( (string) $directory, '/\\' );

			if ( '' === $path || ! is_dir( $path ) || ! is_readable( $path ) ) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() || 'json' !== strtolower( $file->getExtension() ) ) {
					continue;
				}

				$file_path = $file->getPathname();
				$key       = realpath( $file_path ) ?: $file_path;

				if ( isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ] = true;
				$files[]      = $file_path;
			}
		}

		sort( $files );

		return $files;
	}

	/**
	 * Gets child and parent pattern directories.
	 *
	 * @return array Pattern directories.
	 */
	private function default_pattern_directories(): array {
		$directories = array();

		if ( function_exists( 'get_stylesheet_directory' ) ) {
			$directories[] = rtrim( get_stylesheet_directory(), '/\\' ) . '/patterns';
		}

		if ( function_exists( 'get_template_directory' ) ) {
			$directories[] = rtrim( get_template_directory(), '/\\' ) . '/patterns';
		}

		return array_values( array_unique( $directories ) );
	}

	/**
	 * Extracts block names from block comment markup.
	 *
	 * @param string $content Pattern block content.
	 * @return array Block names.
	 */
	private function block_names_from_content( string $content ): array {
		if ( ! preg_match_all( '/<!--\s*wp:([a-z0-9-]+(?:\/[a-z0-9-]+)?)\b/i', $content, $matches ) ) {
			return array();
		}

		// Pattern content is serialized Gutenberg markup. Pull names from block
		// comments instead of parsing rendered HTML, which would miss dynamic blocks.
		return $this->normalize_block_names( $matches[1] );
	}

	/**
	 * Applies configured block support/style overrides.
	 *
	 * @param array  $settings   Block type settings or registration arguments.
	 * @param string $block_name Block type name.
	 * @param string $source     Current WordPress filter name.
	 * @return array Filtered block settings.
	 */
	private function apply_block_support_overrides( array $settings, string $block_name, string $source ): array {
		$options   = $this->options();
		$overrides = isset( $options['block_support_overrides'] ) && is_array( $options['block_support_overrides'] )
			? $options['block_support_overrides']
			: array();

		/**
		 * Filters block support/style overrides for a block registration pass.
		 *
		 * Use the "*" key for all blocks and a block name key such as
		 * "core/button" for block-specific changes.
		 *
		 * @param array  $overrides  Configured override map.
		 * @param string $block_name Current block name.
		 * @param array  $settings   Current settings or registration arguments.
		 * @param string $source     Current WordPress filter name.
		 * @param array  $options    Editor policy options.
		 */
		$filtered = apply_filters( 'emulsify_theme_block_support_overrides', $overrides, $block_name, $settings, $source, $options );

		if ( is_array( $filtered ) ) {
			$overrides = $filtered;
		}

		foreach ( array( '*', $block_name ) as $key ) {
			if ( isset( $overrides[ $key ] ) && is_array( $overrides[ $key ] ) ) {
				// Apply global overrides first and block-specific overrides second so
				// a child theme can set broad defaults with targeted exceptions.
				$settings = $this->merge_override( $settings, $overrides[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Recursively applies an override array.
	 *
	 * @param array $base     Base array.
	 * @param array $override Override array.
	 * @return array Merged array.
	 */
	private function merge_override( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if (
				array_key_exists( $key, $base )
				&& is_array( $base[ $key ] )
				&& is_array( $value )
				&& ! array_is_list( $base[ $key ] )
				&& ! array_is_list( $value )
			) {
				$base[ $key ] = $this->merge_override( $base[ $key ], $value );
				continue;
			}

			$base[ $key ] = $value;
		}

		return $base;
	}

	/**
	 * Normalizes a list of block names.
	 *
	 * @param array $blocks Block name candidates.
	 * @return array Normalized block names.
	 */
	private function normalize_block_names( array $blocks ): array {
		$names = array();

		foreach ( $blocks as $block ) {
			$name = $this->normalize_block_name( $block );

			if ( '' !== $name ) {
				$names[] = $name;
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Normalizes a block name candidate.
	 *
	 * @param mixed $block Block name candidate.
	 * @return string Block name or an empty string.
	 */
	private function normalize_block_name( $block ): string {
		if ( ! is_scalar( $block ) ) {
			return '';
		}

		$name = strtolower( trim( (string) $block ) );

		if ( '' === $name ) {
			return '';
		}

		if ( false === strpos( $name, '/' ) ) {
			$name = 'core/' . $name;
		}

		return preg_match( '/^[a-z0-9-]+\/[a-z0-9-]+$/', $name ) ? $name : '';
	}

	/**
	 * Normalizes a capability string.
	 *
	 * @param mixed $capability Capability candidate.
	 * @return string Capability or an empty string.
	 */
	private function capability( $capability ): string {
		if ( ! is_scalar( $capability ) ) {
			return '';
		}

		$normalized = preg_replace( '/[^a-zA-Z0-9_]+/', '', (string) $capability );

		return is_string( $normalized ) ? trim( $normalized ) : '';
	}

	/**
	 * Safely checks the current user's capabilities.
	 *
	 * @param string $capability Capability name.
	 * @return bool TRUE when the current user has the capability.
	 */
	private function current_user_can( string $capability ): bool {
		return '' !== $capability && function_exists( 'current_user_can' ) && current_user_can( $capability );
	}
}
