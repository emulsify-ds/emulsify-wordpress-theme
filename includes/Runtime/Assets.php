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
	 * Memoized component metadata asset paths.
	 *
	 * @var array
	 */
	private $component_asset_paths = array();

	/**
	 * Memoized manifest-scoped component asset paths.
	 *
	 * @var array|null
	 */
	private $manifest_component_asset_paths;

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

			if ( $this->is_scoped_component_asset( $directory, $file ) ) {
				// Component metadata can opt assets into block-scoped loading. Leave
				// those files to the block registration callback instead of loading
				// them globally from every request's component scanner pass.
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
	 * Checks whether a component asset is declared as block-scoped metadata.
	 *
	 * @param string $directory Theme-relative asset directory being scanned.
	 * @param array  $file      File discovery record.
	 * @return bool TRUE when the asset should not be globally loaded.
	 */
	private function is_scoped_component_asset( string $directory, array $file ): bool {
		if ( 'dist/components' !== trim( $directory, '/\\' ) || empty( $file['path'] ) ) {
			return false;
		}

		$root_relative = isset( $file['relative'] ) && is_scalar( $file['relative'] )
			? $this->normalize_relative_asset_path( (string) $file['relative'] )
			: '';

		if ( '' !== $root_relative && isset( $this->manifest_component_asset_paths()[ $root_relative ] ) ) {
			return true;
		}

		$component_dir = dirname( (string) $file['path'] );
		$relative      = FileDiscovery::relative_path( $component_dir, (string) $file['path'] );
		$asset_paths   = $this->component_asset_paths( $component_dir );

		return isset( $asset_paths[ $relative ] );
	}

	/**
	 * Gets manifest-scoped component asset paths.
	 *
	 * @return array Scoped paths keyed by dist/components-relative path.
	 */
	private function manifest_component_asset_paths(): array {
		if ( is_array( $this->manifest_component_asset_paths ) ) {
			return $this->manifest_component_asset_paths;
		}

		$this->manifest_component_asset_paths = ( new AssetManifest() )->scoped_component_asset_paths();

		return $this->manifest_component_asset_paths;
	}

	/**
	 * Gets declared component metadata asset paths for a component directory.
	 *
	 * @param string $component_dir Absolute component directory path.
	 * @return array Declared asset paths keyed by relative path.
	 */
	private function component_asset_paths( string $component_dir ): array {
		if ( isset( $this->component_asset_paths[ $component_dir ] ) ) {
			return $this->component_asset_paths[ $component_dir ];
		}

		$paths          = array();
		$metadata_files = glob( rtrim( $component_dir, '/\\' ) . '/*.component.json' );

		if ( ! is_array( $metadata_files ) ) {
			$this->component_asset_paths[ $component_dir ] = $paths;
			return $paths;
		}

		foreach ( $metadata_files as $metadata_file ) {
			$contents = is_readable( $metadata_file ) ? file_get_contents( $metadata_file ) : false;

			if ( ! is_string( $contents ) ) {
				continue;
			}

			$metadata = json_decode( $contents, true );

			if ( ! is_array( $metadata ) || JSON_ERROR_NONE !== json_last_error() || empty( $metadata['assets'] ) || ! is_array( $metadata['assets'] ) ) {
				continue;
			}

			foreach ( $this->metadata_asset_paths( $metadata['assets'] ) as $path ) {
				$paths[ $path ] = true;
			}
		}

		$this->component_asset_paths[ $component_dir ] = $paths;

		return $paths;
	}

	/**
	 * Collects asset paths from component metadata asset sections.
	 *
	 * @param array $assets Component metadata assets.
	 * @return array Relative asset paths.
	 */
	private function metadata_asset_paths( array $assets ): array {
		$paths = array();

		foreach ( array( 'frontend', 'editor' ) as $context ) {
			if ( isset( $assets[ $context ] ) && is_array( $assets[ $context ] ) ) {
				$paths = array_merge( $paths, $this->metadata_asset_paths( $assets[ $context ] ) );
			}
		}

		foreach ( array( 'css', 'js' ) as $type ) {
			if ( empty( $assets[ $type ] ) || ! is_array( $assets[ $type ] ) ) {
				continue;
			}

			foreach ( $assets[ $type ] as $entry ) {
				$path = $this->metadata_asset_path( $entry );

				if ( '' !== $path ) {
					$paths[] = $path;
				}
			}
		}

		return array_values( array_unique( $paths ) );
	}

	/**
	 * Gets one component metadata asset path.
	 *
	 * @param mixed $entry Metadata asset entry.
	 * @return string Relative asset path, or empty string when invalid.
	 */
	private function metadata_asset_path( $entry ): string {
		$entry = is_array( $entry ) ? $entry : array( 'path' => $entry );

		foreach ( array( 'path', 'file', 'src', 'href' ) as $key ) {
			if ( isset( $entry[ $key ] ) && is_scalar( $entry[ $key ] ) ) {
				return $this->normalize_relative_asset_path( (string) $entry[ $key ] );
			}
		}

		return '';
	}

	/**
	 * Normalizes a safe relative asset path.
	 *
	 * @param string $path Candidate path.
	 * @return string Safe relative path, or empty string when invalid.
	 */
	private function normalize_relative_asset_path( string $path ): string {
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
