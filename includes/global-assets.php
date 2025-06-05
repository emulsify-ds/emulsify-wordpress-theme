<?php
/**
 * Attach global assets (stylesheets) to the frontend and block editor.
 *
 * @package Emulsify
 */

/**
 * Enqueue all global CSS files from the dist/global directory and subdirectories.
 *
 * This function dynamically registers and enqueues every `.css` file found within
 * the theme's `dist/global/` directory or any of its subdirectories.
 *
 * @param string $context Used to define a unique handle prefix (e.g., 'emulsify' or 'emulsify-editor').
 */
function emulsify_enqueue_global_styles( $context = 'emulsify' ) {
	$base_path = get_theme_file_path( 'dist/global' );
	$base_uri  = get_theme_file_uri( 'dist/global' );

	if ( ! file_exists( $base_path ) || ! is_dir( $base_path ) || ! is_readable( $base_path ) ) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $base_path, RecursiveDirectoryIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'css' === $file->getExtension() ) {
			$filename = $file->getBasename();
			$handle   = $context . '-' . basename( $filename, '.css' );

			$relative_path = ltrim( str_replace( $base_path, '', $file->getPathname() ), '/' );
			$relative_path = str_replace( '\\', '/', $relative_path ); // Normalize for Windows.
			$file_uri      = trailingslashit( $base_uri ) . $relative_path;

			wp_enqueue_style(
				$handle,
				$file_uri,
				array(),
				filemtime( $file->getPathname() )
			);
		}
	}
}

/**
 * Enqueue all global styles on the frontend.
 */
function emulsify_enqueue_frontend_styles() {
	emulsify_enqueue_global_styles( 'emulsify' );
}
add_action( 'wp_enqueue_scripts', 'emulsify_enqueue_frontend_styles' );

/**
 * Enqueue all global styles in the block editor.
 */
function emulsify_enqueue_block_editor_styles() {
	emulsify_enqueue_global_styles( 'emulsify-editor' );
}
add_action( 'enqueue_block_assets', 'emulsify_enqueue_block_editor_styles' );
