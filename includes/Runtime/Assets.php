<?php
/**
 * Enqueues theme assets.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Runtime;

use Emulsify\Theme\Support\AssetManifest;
use Emulsify\Theme\Support\FileDiscovery;

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
		$this->enqueue_scripts( 'emulsify-global', 'dist/global' );
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
				$this->dependencies( $asset ),
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

			if ( $this->is_module_script( $asset ) && function_exists( 'wp_enqueue_script_module' ) ) {
				wp_enqueue_script_module(
					$handle,
					$asset['uri'],
					$this->dependencies( $asset ),
					$asset['version']
				);
				continue;
			}

			wp_enqueue_script(
				$handle,
				$asset['uri'],
				$this->dependencies( $asset ),
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
		$manifest_assets = $this->manifest_asset_files( $directory, $extensions );
		$assets          = null === $manifest_assets ? $this->scanned_asset_files( $directory, $extensions ) : $manifest_assets;

		/**
		 * Filters built asset files before they are enqueued.
		 *
		 * Child themes and project plugins can add, remove, or reorder records.
		 * Asset records should include path, relative, uri, and version keys.
		 *
		 * @param array  $assets     Built asset records.
		 * @param string $directory  Theme-relative asset directory being scanned.
		 * @param array  $extensions Allowed file extensions for the current enqueue pass.
		 */
		$filtered = apply_filters( 'emulsify_theme_asset_files', $assets, $directory, $extensions );

		return is_array( $filtered ) ? $filtered : $assets;
	}

	/**
	 * Gets manifest-backed assets when a manifest declares the current scope.
	 *
	 * @param string $directory  Theme-relative asset directory.
	 * @param array  $extensions Allowed file extensions.
	 * @return array|null Manifest-backed records, or null for scanner fallback.
	 */
	private function manifest_asset_files( string $directory, array $extensions ): ?array {
		$scope = $this->manifest_scope( $directory );

		if ( null === $scope ) {
			return null;
		}

		return ( new AssetManifest() )->asset_records( $scope, $extensions );
	}

	/**
	 * Maps a scanned asset directory to a manifest scope.
	 *
	 * @param string $directory Theme-relative asset directory.
	 * @return string|null Manifest scope, or null when unsupported.
	 */
	private function manifest_scope( string $directory ): ?string {
		$directory = trim( $directory, '/\\' );

		if ( 'dist/global' === $directory ) {
			return 'global';
		}

		if ( 'dist/components' === $directory ) {
			return 'components';
		}

		return null;
	}

	/**
	 * Finds built assets below a theme-relative directory through filesystem scans.
	 *
	 * @param string $directory  Theme-relative asset directory.
	 * @param array  $extensions Allowed file extensions.
	 * @return array Built asset records.
	 */
	private function scanned_asset_files( string $directory, array $extensions ): array {
		$assets = array();
		$seen   = array();

		foreach ( FileDiscovery::file_records( $this->asset_roots( $directory ), $extensions ) as $file ) {
			$relative = $file['relative'];

			if ( $this->is_reserved_editor_asset( $directory, $relative ) ) {
				// Editor-only assets are handled by Editor\Enhancements so they
				// receive editor dependencies and configuration before enqueue.
				continue;
			}

			if ( isset( $seen[ $relative ] ) ) {
				// Discovery roots are child-first. Once a relative path is seen,
				// later parent files with the same name are intentional fallbacks
				// and should not be enqueued twice.
				continue;
			}

			$seen[ $relative ] = true;
			$assets[]          = array(
				'path'     => $file['path'],
				'priority' => $file['priority'],
				'relative' => $relative,
				'uri'      => $file['root_uri'] . '/' . $relative,
				'version'  => $this->version( $file['path'] ),
			);
		}

		return FileDiscovery::sort_by_priority_and_relative( $assets );
	}

	/**
	 * Gets child theme asset roots first, then parent theme fallbacks.
	 *
	 * @param string $directory Theme-relative asset directory.
	 * @return array Asset root records.
	 */
	private function asset_roots( string $directory ): array {
		$directories = FileDiscovery::theme_roots( $directory, true );

		/**
		 * Filters built asset directories before files are discovered.
		 *
		 * Root records should include absolute path, public uri, and priority
		 * keys. Lower priority values are enqueued first after duplicate relative
		 * paths are resolved child-first.
		 *
		 * @param array  $directories Built asset directory records.
		 * @param string $directory   Theme-relative asset directory being scanned.
		 */
		$filtered = apply_filters( 'emulsify_theme_asset_directories', $directories, $directory );

		if ( is_array( $filtered ) ) {
			$directories = $filtered;
		}

		return FileDiscovery::normalize_roots(
			$directories,
			array(
				'require_uri' => true,
			)
		);
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
	 * Gets dependency handles from an asset record.
	 *
	 * @param array $asset Asset record.
	 * @return array Dependency handles.
	 */
	private function dependencies( array $asset ): array {
		return isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array();
	}

	/**
	 * Checks whether a script should be enqueued as a script module.
	 *
	 * @param array $asset Asset record.
	 * @return bool TRUE when script module enqueueing should be used.
	 */
	private function is_module_script( array $asset ): bool {
		return ! array_key_exists( 'module', $asset ) || null === $asset['module'] ? true : (bool) $asset['module'];
	}

	/**
	 * Checks whether a built asset is reserved for editor-only loading.
	 *
	 * @param string $directory Theme-relative asset directory being scanned.
	 * @param string $relative  Asset path relative to the built directory.
	 * @return bool TRUE when the editor service should own the asset.
	 */
	private function is_reserved_editor_asset( string $directory, string $relative ): bool {
		return 'dist/global' === trim( $directory, '/\\' )
			&& 0 === strpos( ltrim( $relative, '/\\' ), 'editor/' );
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
