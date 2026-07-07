<?php
/**
 * Applies optional block pattern governance.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Editor;

/**
 * Pattern visibility and pattern-derived block helpers.
 */
final class PatternGovernance {

	/**
	 * Filters block editor settings for optional pattern visibility governance.
	 *
	 * @param array $settings Block editor settings.
	 * @param mixed $context  Block editor context.
	 * @param array $options  Editor policy options.
	 * @return array Filtered settings.
	 */
	public function filter_editor_settings( array $settings, $context, array $options ): array {
		$namespaces = $this->pattern_namespaces( $options, $settings, $context );

		if ( ! empty( $namespaces ) ) {
			$settings['blockPatterns'] = $this->filter_block_patterns( $settings['blockPatterns'] ?? null, $namespaces );
		}

		return $settings;
	}

	/**
	 * Gets block names referenced by configured pattern JSON files.
	 *
	 * @param array $options Editor policy options.
	 * @return array Block names.
	 */
	public function pattern_block_names( array $options ): array {
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
		return BlockNames::normalize( $matches[1] );
	}
}
