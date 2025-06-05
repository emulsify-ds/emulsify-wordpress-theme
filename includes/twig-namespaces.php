<?php
/**
 * Create namespaces for referencing twig files.
 *
 * @package Emulsify
 */

add_filter(
	'timber/loader/loader',
	function ( $loader ) {
		$loader->addPath( __DIR__ . '/../templates', 'emulsify-tpl' );
		$loader->addPath( __DIR__ . '/../components', 'components' );
		return $loader;
	}
);
