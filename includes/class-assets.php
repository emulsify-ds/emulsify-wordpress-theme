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
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_styles' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_styles' ) );
	}

	/**
	 * Enqueues global frontend styles.
	 *
	 * @return void
	 */
	public function frontend_styles(): void {
		$this->enqueue_global_styles( 'emulsify' );
	}

	/**
	 * Enqueues global block editor styles.
	 *
	 * @return void
	 */
	public function editor_styles(): void {
		$this->enqueue_global_styles( 'emulsify-editor' );
	}

	/**
	 * Enqueues all CSS files from dist/global.
	 *
	 * @param string $context Handle prefix.
	 * @return void
	 */
	private function enqueue_global_styles( string $context ): void {
		$base_path = get_theme_file_path( 'dist/global' );
		$base_uri  = get_theme_file_uri( 'dist/global' );

		if ( ! is_dir( $base_path ) || ! is_readable( $base_path ) ) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $base_path, \RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || 'css' !== $file->getExtension() ) {
				continue;
			}

			$relative_path = ltrim( str_replace( $base_path, '', $file->getPathname() ), '/\\' );
			$relative_path = str_replace( '\\', '/', $relative_path );
			$handle        = $context . '-' . sanitize_key( str_replace( '/', '-', basename( $file->getBasename(), '.css' ) ) );

			wp_enqueue_style(
				$handle,
				trailingslashit( $base_uri ) . $relative_path,
				array(),
				filemtime( $file->getPathname() )
			);
		}
	}
}
