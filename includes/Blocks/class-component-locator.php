<?php
/**
 * Locates built component block artifacts.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Blocks;

/**
 * Finds component metadata in child and parent theme build output.
 */
final class Component_Locator {

	/**
	 * Theme-relative component build directory.
	 *
	 * @var string
	 */
	private const COMPONENTS_DIRECTORY = 'dist/components';

	/**
	 * Gets components that can be registered as ACF/Twig blocks.
	 *
	 * @return array Component records.
	 */
	public function acf_components(): array {
		$components = array();
		$seen       = array();

		foreach ( $this->component_roots() as $root ) {
			foreach ( $this->recursive_files( $root['path'] ) as $path ) {
				if ( ! preg_match( '/\.component\.json$/', basename( $path ) ) ) {
					continue;
				}

				$directory = dirname( $path );
				$relative  = $this->relative_path( $root['path'], $directory );
				$key       = '' === $relative ? '.' : $relative;

				if ( isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ] = true;
				$template     = $this->twig_template( $root['path'], $directory, $path );

				if ( '' === $template ) {
					continue;
				}

				$components[] = array(
					'metadata_path' => $path,
					'relative'      => $relative,
					'slug'          => $this->slug( $relative, $path ),
					'template'      => $template,
				);
			}
		}

		return $components;
	}

	/**
	 * Gets component directories that contain native block metadata.
	 *
	 * @return array Component directory records.
	 */
	public function native_block_directories(): array {
		$directories = array();
		$seen        = array();

		foreach ( $this->component_roots() as $root ) {
			foreach ( $this->recursive_files( $root['path'] ) as $path ) {
				if ( 'block.json' !== basename( $path ) ) {
					continue;
				}

				$directory = dirname( $path );
				$relative  = $this->relative_path( $root['path'], $directory );
				$key       = '' === $relative ? '.' : $relative;

				if ( isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ] = true;
				$directories[] = array(
					'path'     => $directory,
					'relative' => $relative,
				);
			}
		}

		return $directories;
	}

	/**
	 * Gets child theme component roots first, then parent theme fallbacks.
	 *
	 * @return array Component root records.
	 */
	private function component_roots(): array {
		$roots      = array();
		$seen_paths = array();
		$candidates = array(
			get_stylesheet_directory(),
			get_template_directory(),
		);

		foreach ( $candidates as $base_path ) {
			$path = rtrim( $base_path, '/\\' ) . '/' . self::COMPONENTS_DIRECTORY;
			$key  = realpath( $path );

			if ( false === $key || isset( $seen_paths[ $key ] ) || ! is_dir( $path ) || ! is_readable( $path ) ) {
				continue;
			}

			$seen_paths[ $key ] = true;
			$roots[]           = array(
				'path' => rtrim( $path, '/\\' ),
			);
		}

		return $roots;
	}

	/**
	 * Gets all files below a directory in deterministic order.
	 *
	 * @param string $directory Absolute directory path.
	 * @return array Absolute file paths.
	 */
	private function recursive_files( string $directory ): array {
		$files    = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$files[] = $file->getPathname();
			}
		}

		sort( $files );

		return $files;
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
	 * Builds a POSIX path relative to the component root.
	 *
	 * @param string $base_path Base directory.
	 * @param string $path      Absolute path.
	 * @return string Relative path.
	 */
	private function relative_path( string $base_path, string $path ): string {
		$relative = ltrim( str_replace( rtrim( $base_path, '/\\' ), '', $path ), '/\\' );

		return str_replace( '\\', '/', $relative );
	}

	/**
	 * Builds a theme-relative path for Timber rendering.
	 *
	 * @param string $root_path Absolute component root path.
	 * @param string $path      Absolute template path.
	 * @return string Theme-relative template path.
	 */
	private function theme_relative_path( string $root_path, string $path ): string {
		$relative = $this->relative_path( $root_path, $path );

		return self::COMPONENTS_DIRECTORY . '/' . $relative;
	}
}
