<?php
/**
 * Enqueues theme assets.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme;

/**
 * Global asset registration.
 */
final class Assets {

	/**
	 * Registers asset hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'enqueue_block_assets', array( $this, 'styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
	}

	/**
	 * Enqueues theme styles for the frontend and block editor preview.
	 *
	 * @return void
	 */
	public function styles(): void {
		$this->enqueue_styles( 'emulsify-global', 'dist/global' );
		$this->enqueue_styles( 'emulsify-component', 'dist/components' );
	}

	/**
	 * Enqueues frontend component scripts.
	 *
	 * @return void
	 */
	public function frontend_scripts(): void {
		$this->enqueue_scripts( 'emulsify-component', 'dist/components' );
	}

	/**
	 * Enqueues CSS files from a built asset directory.
	 *
	 * @param string $prefix    Handle prefix.
	 * @param string $directory Theme-relative asset directory.
	 * @return void
	 */
	private function enqueue_styles( string $prefix, string $directory ): void {
		foreach ( $this->asset_files( $directory, array( 'css' ) ) as $asset ) {
			$handle = $this->handle( $prefix, $asset['relative'] );

			wp_enqueue_style(
				$handle,
				$asset['uri'],
				array(),
				$asset['version']
			);
		}
	}

	/**
	 * Enqueues JavaScript files from a built asset directory.
	 *
	 * @param string $prefix    Handle prefix.
	 * @param string $directory Theme-relative asset directory.
	 * @return void
	 */
	private function enqueue_scripts( string $prefix, string $directory ): void {
		foreach ( $this->asset_files( $directory, array( 'js' ) ) as $asset ) {
			$handle = $this->handle( $prefix, $asset['relative'] );

			if ( function_exists( 'wp_enqueue_script_module' ) ) {
				wp_enqueue_script_module(
					$handle,
					$asset['uri'],
					array(),
					$asset['version']
				);
				continue;
			}

			wp_enqueue_script(
				$handle,
				$asset['uri'],
				array(),
				$asset['version'],
				array(
					'in_footer' => true,
				)
			);
		}
	}

	/**
	 * Finds built assets below a theme-relative directory.
	 *
	 * @param string $directory  Theme-relative asset directory.
	 * @param array  $extensions Allowed file extensions.
	 * @return array Built asset records.
	 */
	private function asset_files( string $directory, array $extensions ): array {
		$assets = array();
		$seen   = array();

		foreach ( $this->asset_roots( $directory ) as $root ) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $root['path'], \RecursiveDirectoryIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() || ! in_array( strtolower( $file->getExtension() ), $extensions, true ) ) {
					continue;
				}

				$relative = $this->relative_path( $root['path'], $file->getPathname() );

				if ( isset( $seen[ $relative ] ) ) {
					continue;
				}

				$seen[ $relative ] = true;
				$assets[]          = array(
					'path'     => $file->getPathname(),
					'priority' => $root['priority'],
					'relative' => $relative,
					'uri'      => $root['uri'] . '/' . $relative,
					'version'  => $this->version( $file->getPathname() ),
				);
			}
		}

		usort(
			$assets,
			static function ( array $left, array $right ): int {
				$priority = $left['priority'] <=> $right['priority'];

				return 0 === $priority ? strcmp( $left['relative'], $right['relative'] ) : $priority;
			}
		);

		return $assets;
	}

	/**
	 * Gets child theme asset roots first, then parent theme fallbacks.
	 *
	 * @param string $directory Theme-relative asset directory.
	 * @return array Asset root records.
	 */
	private function asset_roots( string $directory ): array {
		$roots      = array();
		$seen_paths = array();
		$candidates = array(
			array(
				'base_path' => get_stylesheet_directory(),
				'base_uri'  => get_stylesheet_directory_uri(),
			),
			array(
				'base_path' => get_template_directory(),
				'base_uri'  => get_template_directory_uri(),
			),
		);

		foreach ( $candidates as $priority => $candidate ) {
			$path = rtrim( $candidate['base_path'], '/\\' ) . '/' . ltrim( $directory, '/\\' );
			$uri  = rtrim( $candidate['base_uri'], '/' ) . '/' . trim( $directory, '/' );
			$key  = realpath( $path );

			if ( false === $key || isset( $seen_paths[ $key ] ) || ! is_dir( $path ) || ! is_readable( $path ) ) {
				continue;
			}

			$seen_paths[ $key ] = true;
			$roots[]           = array(
				'path'     => rtrim( $path, '/\\' ),
				'priority' => $priority,
				'uri'      => $uri,
			);
		}

		return $roots;
	}

	/**
	 * Builds a WordPress-safe asset handle.
	 *
	 * @param string $prefix   Handle prefix.
	 * @param string $relative Asset path relative to its built directory.
	 * @return string Asset handle.
	 */
	private function handle( string $prefix, string $relative ): string {
		$name = preg_replace( '/\.(css|js)$/', '', $relative );
		$name = preg_replace( '/[^A-Za-z0-9_-]+/', '-', (string) $name );

		return sanitize_key( $prefix . '-' . trim( (string) $name, '-' ) );
	}

	/**
	 * Builds a POSIX relative path.
	 *
	 * @param string $base_path Base directory.
	 * @param string $path      Absolute file path.
	 * @return string Relative path.
	 */
	private function relative_path( string $base_path, string $path ): string {
		$relative = ltrim( str_replace( rtrim( $base_path, '/\\' ), '', $path ), '/\\' );

		return str_replace( '\\', '/', $relative );
	}

	/**
	 * Gets a filemtime-based asset version.
	 *
	 * @param string $path Absolute file path.
	 * @return string|null Asset version.
	 */
	private function version( string $path ): ?string {
		$modified = filemtime( $path );

		return false === $modified ? null : (string) $modified;
	}
}
