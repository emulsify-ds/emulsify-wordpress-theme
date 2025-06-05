<?php
/**
 * Register ACF-based Gutenberg blocks with Timber support.
 *
 * @package Emulsify
 */

/**
 * Dynamically registers custom ACF blocks using `.component.json` metadata
 * and renders them using Timber/Twig templates.
 *
 * Scans the `dist/components/` directory in the active theme for valid blocks.
 *
 * @return void
 */
function register_emulsify_blocks() {
	// Define the path to the components directory within the active (child) theme.
	$components_dir = get_stylesheet_directory() . '/dist/components';

	// Exit early if the components directory does not exist.
	if ( ! is_dir( $components_dir ) ) {
		return;
	}

	// Iterate through each item (folder) inside the components directory.
	foreach ( new DirectoryIterator( $components_dir ) as $item ) {
		// Skip non-directories and dot folders like "." or "..".
		if ( $item->isDir() && ! $item->isDot() ) {
			$slug      = $item->getFilename();
			$json_path = $item->getPathname() . '/' . $slug . '.component.json';
			$twig_path = 'dist/components/' . $slug . '/' . $slug . '.twig';

			// Skip if either the JSON config or Twig template file is missing.
			if ( ! file_exists( $json_path ) || ! file_exists( get_stylesheet_directory() . '/' . $twig_path ) ) {
				continue;
			}

			// Load and decode the component's JSON configuration file.
			$config = json_decode( file_get_contents( $json_path ), true );

			// Skip invalid or incomplete JSON configs.
			if ( ! is_array( $config ) || empty( $config['name'] ) ) {
				continue;
			}

			// Define base/default arguments for the block registration.
			$block_args = array_merge(
				array(
					'name'            => 'emulsify/' . strtolower( ucwords( str_replace( '-', ' ', $slug ) ) ),
					// Prefix the block name for uniqueness within Gutenberg.
					'title'           => strtolower( ucwords( str_replace( '-', ' ', $slug ) ) ),
					'mode'            => 'preview',
					// Timber-compatible render callback.
					'render_callback' => 'render_twig_block',
					// Custom key for locating the Twig template.
					'twig_template'   => $twig_path,
				),
				$config
			);

			// Register the block with ACF.
			acf_register_block_type( $block_args );
		}
	}
}
add_action( 'acf/init', 'register_emulsify_blocks' );

/**
 * Timber render callback for ACF Gutenberg blocks.
 *
 * Passes block data and ACF fields into the specified Twig template for rendering.
 *
 * @param array  $block      The block settings and attributes.
 * @param string $content    The block's inner content (empty for dynamic blocks).
 * @param bool   $is_preview True during editor preview render.
 * @param int    $post_id    The ID of the current post being edited or viewed.
 *
 * @return void
 */
function render_twig_block( $block, $content = '', $is_preview = false, $post_id = 0 ) {
	// Get standard Timber context.
	$context           = Timber::context();
	$context['block']  = $block;
	$context['fields'] = get_fields();

	// Add preview mode flag to context.
	if ( $is_preview ) {
		$context['is_preview'] = true;
	}

	// Try to get the template path from either block data or custom property.
	if ( ! empty( $block['data']['twig_template'] ) ) {
		Timber::render( $block['data']['twig_template'], $context );
	} elseif ( ! empty( $block['twig_template'] ) ) {
		Timber::render( $block['twig_template'], $context );
	} else {
		// Fallback message if no template is defined.
		echo '<p><strong>Block rendering error:</strong> No template defined.</p>';
	}
}
