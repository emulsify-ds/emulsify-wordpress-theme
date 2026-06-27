<?php
/**
 * Create namespaces for referencing twig files.
 *
 * @package Emulsify
 */

add_filter(
	'timber/loader/loader',
	function ( $loader ) {
		$paths = array(
			array( __DIR__ . '/../src/components', 'components' ),
			array( __DIR__ . '/../src/foundation', 'foundation' ),
			array( __DIR__ . '/../src/layout', 'layout' ),
			array( __DIR__ . '/../templates', 'templates' ),
		);

		foreach ( $paths as $path ) {
			if ( is_dir( $path[0] ) && is_readable( $path[0] ) ) {
				$loader->addPath( $path[0], $path[1] );
			}
		}

		return $loader;
	}
);
