<?php
/**
 * Search results template.
 *
 * @package Emulsify
 */

use Timber\Timber;

$templates = array( 'templates/search.twig', 'templates/archive.twig', 'templates/index.twig' );

$context = Timber::context(
	array(
		'title' => 'Search results for ' . get_search_query(),
	)
);

Timber::render( $templates, $context );
