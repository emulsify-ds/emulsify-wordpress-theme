<?php
/**
 * Locates built component block artifacts.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Blocks;

use Emulsify\Theme\Support\FileDiscovery;

/**
 * Finds component metadata in child and parent theme build output.
 *
 * Discovery is memoized on this object for the lifetime of the current PHP
 * request. The registry shares one locator between ACF/Twig and native block
 * registration, so both paths reuse the same deterministic child-first file
 * index without introducing persistent cache invalidation concerns.
 */
final class ComponentLocator {

	/**
	 * Theme-relative component build directory.
	 *
	 * @var string
	 */
	private const COMPONENTS_DIRECTORY = 'dist/components';

	/**
	 * Memoized component root records.
	 *
	 * @var array|null
	 */
	private $component_roots;

	/**
	 * Memoized component file records.
	 *
	 * @var array|null
	 */
	private $component_files;

	/**
	 * Memoized ACF/Twig component records.
	 *
	 * @var array|null
	 */
	private $acf_components;

	/**
	 * Memoized native block directory records.
	 *
	 * @var array|null
	 */
	private $native_block_directories;

	/**
	 * Duplicate component records skipped during discovery.
	 *
	 * @var array
	 */
	private $skipped_duplicates = array();

	/**
	 * Gets components that can be registered as ACF/Twig blocks.
	 *
	 * @return array Component records.
	 */
	public function acf_components(): array {
		if ( is_array( $this->acf_components ) ) {
			return $this->acf_components;
		}

		$components = array();
		$seen_paths = array();
		$seen_slugs = array();

		foreach ( $this->component_files() as $file ) {
			$path = $file['path'];

			if ( ! preg_match( '/\.component\.json$/', basename( $path ) ) ) {
				continue;
			}

			$directory = dirname( $path );
			$relative  = FileDiscovery::relative_path( $file['root_path'], $directory );
			$key       = '' === $relative ? '.' : $relative;
			$record    = $this->component_record( $file, $directory, $relative, $path );

			if ( isset( $seen_paths[ $key ] ) ) {
				$this->record_skipped_duplicate(
					'acf_component_path',
					$key,
					$seen_paths[ $key ],
					$record,
					'Duplicate ACF/Twig component path.'
				);
				continue;
			}

			$seen_paths[ $key ] = $record;
			$template           = $this->twig_template( $file['root_path'], $directory, $path );

			if ( '' === $template ) {
				// A metadata file without a matching Twig template is not a renderable
				// ACF/Twig component; native block.json registration is handled separately.
				continue;
			}

			$slug = $this->slug( $relative, $path );

			if ( isset( $seen_slugs[ $slug ] ) ) {
				$this->record_skipped_duplicate(
					'acf_component_slug',
					$slug,
					$seen_slugs[ $slug ],
					$record,
					'Duplicate ACF/Twig component slug.'
				);
				continue;
			}

			$seen_slugs[ $slug ] = $record;

			$components[] = array(
				'path'          => $directory,
				'metadata_path' => $path,
				'relative'      => $relative,
				'root_path'     => $file['root_path'],
				'root_uri'      => $file['root_uri'] ?? '',
				'slug'          => $slug,
				'source'        => $file['root_source'],
				'template'      => $template,
			);
		}

		$this->acf_components = $components;

		return $this->acf_components;
	}

	/**
	 * Gets component directories that contain native block metadata.
	 *
	 * @return array Component directory records.
	 */
	public function native_block_directories(): array {
		if ( is_array( $this->native_block_directories ) ) {
			return $this->native_block_directories;
		}

		$directories = array();
		$seen_names  = array();
		$seen_paths  = array();

		foreach ( $this->component_files() as $file ) {
			$path = $file['path'];

			if ( 'block.json' !== basename( $path ) ) {
				continue;
			}

			$directory = dirname( $path );
			$relative  = FileDiscovery::relative_path( $file['root_path'], $directory );
			$key       = '' === $relative ? '.' : $relative;
			$name      = $this->native_block_name( $path );
			$record    = $this->component_record( $file, $directory, $relative, $path );

			if ( '' !== $name ) {
				$record['name'] = $name;
			}

			if ( isset( $seen_paths[ $key ] ) ) {
				$this->record_skipped_duplicate(
					'native_component_path',
					$key,
					$seen_paths[ $key ],
					$record,
					'Duplicate native block component path.'
				);
				continue;
			}

			if ( '' !== $name && isset( $seen_names[ $name ] ) ) {
				$this->record_skipped_duplicate(
					'native_block_name',
					$name,
					$seen_names[ $name ],
					$record,
					'Duplicate native block name.'
				);
				continue;
			}

			$seen_paths[ $key ] = $record;

			if ( '' !== $name ) {
				$seen_names[ $name ] = $record;
			}

			$directories[] = array(
				'path'          => $directory,
				'relative'      => $relative,
				'root_path'     => $file['root_path'],
				'root_uri'      => $file['root_uri'] ?? '',
				'source'        => $file['root_source'],
				'metadata_path' => $path,
				'name'          => $name,
			);
		}

		$this->native_block_directories = $directories;

		return $this->native_block_directories;
	}

	/**
	 * Gets duplicate records skipped during discovery.
	 *
	 * @param string|null $type_prefix Optional duplicate type prefix filter.
	 * @return array Skipped duplicate records.
	 */
	public function skipped_duplicates( ?string $type_prefix = null ): array {
		if ( null === $type_prefix ) {
			return $this->skipped_duplicates;
		}

		return array_values(
			array_filter(
				$this->skipped_duplicates,
				static function ( array $duplicate ) use ( $type_prefix ): bool {
					return isset( $duplicate['type'] ) && 0 === strpos( $duplicate['type'], $type_prefix );
				}
			)
		);
	}

	/**
	 * Gets all component files from child and parent roots.
	 *
	 * The file index is built once per locator instance. The shared registry
	 * locator means ACF/Twig metadata discovery and native block discovery reuse
	 * the same recursive filesystem scan during a request.
	 *
	 * @return array Component file records.
	 */
	private function component_files(): array {
		if ( is_array( $this->component_files ) ) {
			return $this->component_files;
		}

		$this->component_files = FileDiscovery::file_records( $this->component_roots() );

		return $this->component_files;
	}

	/**
	 * Gets child theme component roots first, then parent theme fallbacks.
	 *
	 * Matching relative component directories in the child theme win because
	 * child roots are indexed first and later parent records with the same
	 * relative path are skipped by the discovery methods.
	 *
	 * @return array Component root records.
	 */
	private function component_roots(): array {
		if ( is_array( $this->component_roots ) ) {
			return $this->component_roots;
		}

		$candidates = FileDiscovery::theme_roots( self::COMPONENTS_DIRECTORY, true );

		/**
		 * Filters built component discovery roots before scanning.
		 *
		 * Root records should include an absolute path to a dist/components
		 * directory and an optional source label. The default order is child
		 * theme first, then parent theme fallback.
		 *
		 * @param array $candidates Component root records.
		 */
		$filtered = apply_filters( 'emulsify_theme_component_roots', $candidates );

		if ( is_array( $filtered ) ) {
			$candidates = $filtered;
		}

		$this->component_roots = FileDiscovery::normalize_roots(
			$candidates,
			array(
				'default_source' => 'filtered',
			)
		);

		return $this->component_roots;
	}

	/**
	 * Builds a debug record for a component artifact.
	 *
	 * @param array  $file          Component file record.
	 * @param string $directory     Absolute component directory path.
	 * @param string $relative      Component-relative directory path.
	 * @param string $metadata_path Absolute metadata path.
	 * @return array Component debug record.
	 */
	private function component_record( array $file, string $directory, string $relative, string $metadata_path ): array {
		return array(
			'path'          => $directory,
			'relative'      => $relative,
			'root_path'     => $file['root_path'],
			'root_uri'      => $file['root_uri'] ?? '',
			'source'        => $file['root_source'],
			'metadata_path' => $metadata_path,
		);
	}

	/**
	 * Records a skipped duplicate component artifact.
	 *
	 * @param string $type    Duplicate type.
	 * @param string $name    Duplicate key or block name.
	 * @param array  $kept    Higher-priority record.
	 * @param array  $skipped Lower-priority skipped record.
	 * @param string $reason  Human-readable reason.
	 * @return void
	 */
	private function record_skipped_duplicate( string $type, string $name, array $kept, array $skipped, string $reason ): void {
		$this->skipped_duplicates[] = array(
			'type'    => $type,
			'name'    => $name,
			'reason'  => $reason,
			'kept'    => $kept,
			'skipped' => $skipped,
		);
	}

	/**
	 * Gets the declared block name from a native block.json file.
	 *
	 * @param string $path Absolute block.json path.
	 * @return string Native block name, or an empty string when unavailable.
	 */
	private function native_block_name( string $path ): string {
		$contents = file_get_contents( $path );

		if ( ! is_string( $contents ) ) {
			return '';
		}

		$metadata = json_decode( $contents, true );

		if ( ! is_array( $metadata ) || JSON_ERROR_NONE !== json_last_error() || empty( $metadata['name'] ) || ! is_string( $metadata['name'] ) ) {
			return '';
		}

		return trim( $metadata['name'] );
	}

	/**
	 * Finds the Twig template for a component metadata file.
	 *
	 * @param string $root_path     Absolute component root path.
	 * @param string $directory     Absolute component directory path.
	 * @param string $metadata_path Absolute component metadata path.
	 * @return string Theme-relative Twig template path.
	 */
	private function twig_template( string $root_path, string $directory, string $metadata_path ): string {
		$metadata_slug = preg_replace( '/\.component\.json$/', '', basename( $metadata_path ) );
		$candidates    = array(
			$directory . '/' . $metadata_slug . '.twig',
			$directory . '/' . basename( $directory ) . '.twig',
		);

		foreach ( array_unique( $candidates ) as $candidate ) {
			if ( is_readable( $candidate ) ) {
				return $this->theme_relative_path( $root_path, $candidate );
			}
		}

		return '';
	}

	/**
	 * Builds a component slug from its path.
	 *
	 * @param string $relative      Component-relative directory path.
	 * @param string $metadata_path Absolute component metadata path.
	 * @return string Component slug.
	 */
	private function slug( string $relative, string $metadata_path ): string {
		$slug = '' === $relative ? preg_replace( '/\.component\.json$/', '', basename( $metadata_path ) ) : $relative;

		return sanitize_title( str_replace( '/', '-', (string) $slug ) );
	}

	/**
	 * Builds a theme-relative path for Timber rendering.
	 *
	 * @param string $root_path Absolute component root path.
	 * @param string $path      Absolute template path.
	 * @return string Theme-relative template path.
	 */
	private function theme_relative_path( string $root_path, string $path ): string {
		$relative = FileDiscovery::relative_path( $root_path, $path );

		return self::COMPONENTS_DIRECTORY . '/' . $relative;
	}
}
