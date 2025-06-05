<?php
/**
 * Create namespaces for referencing twig files.
 *
 * @package Emulsify
 */

add_filter(
	'timber/loader/loader',
	function ( $loader ) {
		$loader->addPath( __DIR__ . '/../src/components', 'components' );
		$loader->addPath( __DIR__ . '/../src/foundation', 'foundation' );
		$loader->addPath( __DIR__ . '/../src/layout', 'layout' );
		$loader->addPath( __DIR__ . '/../templates', 'templates' );
		return $loader;
	}
);
