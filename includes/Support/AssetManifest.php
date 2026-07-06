<?php
/**
 * Optional built asset manifest reader.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Support;

/**
 * Reads child-first asset manifests and normalizes asset records.
 */
final class AssetManifest {

	/**
	 * Default theme-relative manifest path.
	 *
	 * @var string
	 */
	private const DEFAULT_PATH = 'dist/emulsify-assets.json';

	/**
	 * Gets manifest-backed asset records for a runtime scope.
	 *
	 * A null return means no usable manifest data was found for the scope and
	 * callers should keep their existing filesystem scanner fallback.
	 *
	 * @param string $scope      Asset scope: global, editor, or components.
	 * @param array  $extensions Allowed file extensions.
	 * @return array|null Asset records, or null when scanner fallback should run.
	 */
	public function asset_records( string $scope, array $extensions ): ?array {
		$manifest = $this->manifest();

		if ( null === $manifest ) {
			return null;
		}

		$assets = isset( $manifest['data']['assets'] ) && is_array( $manifest['data']['assets'] )
			? $manifest['data']['assets']
			: $manifest['data'];
		$records = $this->scope_records( $scope, $extensions, $assets, $manifest );

		if ( null === $records ) {
			return null;
		}

		return FileDiscovery::sort_by_priority_and_relative( $records );
	}

	/**
	 * Gets records for a manifest asset scope.
	 *
	 * @param string $scope      Asset scope.
	 * @param array  $extensions Allowed file extensions.
	 * @param array  $assets     Manifest asset data.
	 * @param array  $manifest   Active manifest record.
	 * @return array|null Asset records, or null when the scope is undeclared.
	 */
	private function scope_records( string $scope, array $extensions, array $assets, array $manifest ): ?array {
		if ( 'components' === $scope ) {
			$declared = false;
			$records  = array();

			if ( array_key_exists( 'components', $assets ) ) {
				$declared = true;
				$records  = array_merge( $records, $this->section_records( $assets['components'], $extensions, $manifest ) );
			}

			if ( array_key_exists( 'blocks', $assets ) && is_array( $assets['blocks'] ) ) {
				$declared = true;

				foreach ( $assets['blocks'] as $block_name => $section ) {
					$records = array_merge(
						$records,
						$this->section_records(
							$section,
							$extensions,
							$manifest,
							is_scalar( $block_name ) ? (string) $block_name : ''
						)
					);
				}
			}

			return $declared ? $records : null;
		}

		if ( ! array_key_exists( $scope, $assets ) ) {
			return null;
		}

		return $this->section_records( $assets[ $scope ], $extensions, $manifest );
	}

	/**
	 * Gets normalized records from a manifest section.
	 *
	 * @param mixed  $section    Manifest section.
	 * @param array  $extensions Allowed file extensions.
	 * @param array  $manifest   Active manifest record.
	 * @param string $block_name Optional block name for block-specific entries.
	 * @return array Asset records.
	 */
	private function section_records( $section, array $extensions, array $manifest, string $block_name = '' ): array {
		if ( ! is_array( $section ) ) {
			return array();
		}

		$records = array();

		foreach ( $extensions as $extension ) {
			if ( ! is_scalar( $extension ) ) {
				continue;
			}

			$key = strtolower( ltrim( trim( (string) $extension ), '.' ) );

			if ( '' === $key || empty( $section[ $key ] ) || ! is_array( $section[ $key ] ) ) {
				continue;
			}

			foreach ( $section[ $key ] as $entry ) {
				$record = $this->asset_record( $entry, $key, $manifest, $block_name );

				if ( null !== $record ) {
					$records[] = $record;
				}
			}
		}

		return $records;
	}

	/**
	 * Normalizes a manifest asset entry.
	 *
	 * @param mixed  $entry      Manifest asset entry.
	 * @param string $extension  Expected file extension.
	 * @param array  $manifest   Active manifest record.
	 * @param string $block_name Optional block name for block-specific entries.
	 * @return array|null Asset record, or null when invalid.
	 */
	private function asset_record( $entry, string $extension, array $manifest, string $block_name = '' ): ?array {
		$data     = is_array( $entry ) ? $entry : array( 'path' => $entry );
		$relative = $this->entry_path( $data );

		if ( '' === $relative || strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) ) !== $extension ) {
			return null;
		}

		$path = $manifest['base_path'] . '/' . $relative;

		if ( ! is_readable( $path ) ) {
			return null;
		}

		$record = array(
			'path'          => $path,
			'priority'      => $manifest['priority'],
			'relative'      => $this->entry_relative( $data, $relative ),
			'uri'           => $manifest['base_uri'] . '/' . $relative,
			'version'       => $this->entry_version( $data, $path ),
			'dependencies'  => $this->entry_dependencies( $data ),
			'module'        => $this->entry_module( $data ),
			'source'        => $manifest['source'],
			'manifest_path' => $manifest['path'],
		);

		if ( '' !== $block_name ) {
			$record['block'] = $block_name;
		}

		return $record;
	}

	/**
	 * Gets the first readable, valid child-first manifest.
	 *
	 * @return array|null Active manifest record, or null when unavailable.
	 */
	private function manifest(): ?array {
		foreach ( $this->manifest_candidates() as $candidate ) {
			if ( ! is_readable( $candidate['path'] ) ) {
				continue;
			}

			$contents = file_get_contents( $candidate['path'] );

			if ( ! is_string( $contents ) ) {
				return null;
			}

			$data = json_decode( $contents, true );

			if ( ! is_array( $data ) || JSON_ERROR_NONE !== json_last_error() ) {
				return null;
			}

			/**
			 * Filters parsed asset manifest data before records are created.
			 *
			 * Return a non-array value to ignore the manifest and fall back to
			 * the recursive scanner for the current request.
			 *
			 * @param array $data      Parsed manifest data.
			 * @param array $candidate Manifest file candidate.
			 */
			$filtered = apply_filters( 'emulsify_theme_asset_manifest_data', $data, $candidate );

			if ( ! is_array( $filtered ) ) {
				return null;
			}

			$candidate['data'] = $filtered;

			return $candidate;
		}

		return null;
	}

	/**
	 * Gets child-first manifest candidates.
	 *
	 * @return array Manifest file candidates.
	 */
	private function manifest_candidates(): array {
		$relative_path = self::DEFAULT_PATH;

		/**
		 * Filters the theme-relative asset manifest path.
		 *
		 * Return an empty value to disable manifest loading and always use the
		 * recursive scanner fallback.
		 *
		 * @param string $relative_path Theme-relative manifest path.
		 */
		$filtered = apply_filters( 'emulsify_theme_asset_manifest_path', $relative_path );

		if ( ! is_scalar( $filtered ) ) {
			return array();
		}

		$relative_path = $this->normalize_relative_path( (string) $filtered );

		if ( '' === $relative_path ) {
			return array();
		}

		$candidates = array();

		if ( function_exists( 'get_stylesheet_directory' ) && function_exists( 'get_stylesheet_directory_uri' ) ) {
			$candidates[] = $this->candidate(
				get_stylesheet_directory(),
				get_stylesheet_directory_uri(),
				$relative_path,
				0,
				'child'
			);
		}

		if ( function_exists( 'get_template_directory' ) && function_exists( 'get_template_directory_uri' ) ) {
			$candidates[] = $this->candidate(
				get_template_directory(),
				get_template_directory_uri(),
				$relative_path,
				1,
				'parent'
			);
		}

		return $candidates;
	}

	/**
	 * Builds a manifest candidate record.
	 *
	 * @param string $theme_path    Theme root path.
	 * @param string $theme_uri     Theme root URI.
	 * @param string $relative_path Theme-relative manifest path.
	 * @param int    $priority      Root priority.
	 * @param string $source        Root source.
	 * @return array Manifest candidate record.
	 */
	private function candidate( string $theme_path, string $theme_uri, string $relative_path, int $priority, string $source ): array {
		$path = rtrim( $theme_path, '/\\' ) . '/' . $relative_path;
		$uri  = rtrim( $theme_uri, '/' ) . '/' . $relative_path;

		return array(
			'path'      => $path,
			'base_path' => dirname( $path ),
			'base_uri'  => rtrim( str_replace( '\\', '/', dirname( $uri ) ), '/' ),
			'priority'  => $priority,
			'source'    => $source,
		);
	}

	/**
	 * Gets an asset entry path.
	 *
	 * @param array $entry Manifest asset entry.
	 * @return string Entry path relative to the manifest directory.
	 */
	private function entry_path( array $entry ): string {
		foreach ( array( 'path', 'file', 'src', 'href' ) as $key ) {
			if ( isset( $entry[ $key ] ) && is_scalar( $entry[ $key ] ) ) {
				return $this->normalize_relative_path( (string) $entry[ $key ] );
			}
		}

		return '';
	}

	/**
	 * Gets the record-relative path used for handle generation.
	 *
	 * @param array  $entry    Manifest asset entry.
	 * @param string $fallback Fallback relative path.
	 * @return string Relative path.
	 */
	private function entry_relative( array $entry, string $fallback ): string {
		if ( isset( $entry['relative'] ) && is_scalar( $entry['relative'] ) ) {
			$relative = $this->normalize_relative_path( (string) $entry['relative'] );

			if ( '' !== $relative ) {
				return $relative;
			}
		}

		return $fallback;
	}

	/**
	 * Gets the asset version from explicit metadata or filemtime.
	 *
	 * @param array  $entry Manifest asset entry.
	 * @param string $path  Absolute asset path.
	 * @return string|null Asset version.
	 */
	private function entry_version( array $entry, string $path ): ?string {
		foreach ( array( 'version', 'hash' ) as $key ) {
			if ( isset( $entry[ $key ] ) && is_scalar( $entry[ $key ] ) && '' !== trim( (string) $entry[ $key ] ) ) {
				return (string) $entry[ $key ];
			}
		}

		$modified = filemtime( $path );

		return false === $modified ? null : (string) $modified;
	}

	/**
	 * Gets normalized dependency handles.
	 *
	 * @param array $entry Manifest asset entry.
	 * @return array Dependency handles.
	 */
	private function entry_dependencies( array $entry ): array {
		$dependencies = $entry['dependencies'] ?? ( $entry['deps'] ?? array() );

		if ( ! is_array( $dependencies ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $dependency ) {
						return is_scalar( $dependency ) ? trim( (string) $dependency ) : null;
					},
					$dependencies
				),
				static function ( $dependency ): bool {
					return is_string( $dependency ) && '' !== $dependency;
				}
			)
		);
	}

	/**
	 * Gets an optional script module flag.
	 *
	 * @param array $entry Manifest asset entry.
	 * @return bool|null Module flag, or null to use service default.
	 */
	private function entry_module( array $entry ): ?bool {
		if ( array_key_exists( 'module', $entry ) ) {
			return ! empty( $entry['module'] );
		}

		if ( array_key_exists( 'type', $entry ) && is_scalar( $entry['type'] ) ) {
			return 'module' === strtolower( trim( (string) $entry['type'] ) );
		}

		return null;
	}

	/**
	 * Normalizes safe relative paths.
	 *
	 * @param string $path Relative path.
	 * @return string Normalized relative path, or empty string when invalid.
	 */
	private function normalize_relative_path( string $path ): string {
		$path = trim( str_replace( '\\', '/', $path ) );

		if (
			'' === $path
			|| false !== strpos( $path, "\0" )
			|| 0 === strpos( $path, '/' )
			|| preg_match( '#(^|/)\.\.(/|$)#', $path )
		) {
			return '';
		}

		while ( 0 === strpos( $path, './' ) ) {
			$path = substr( $path, 2 );
		}

		return $path;
	}
}
