<?php
/**
 * Search results template.
 *
 * @package Emulsify
 */

use Timber\Timber;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$templates = array( '@templates/search.twig', '@templates/archive.twig', '@templates/index.twig' );

$context = Timber::context(
	array(
		/* translators: %s is the search query. */
		'title' => sprintf( __( 'Search results for %s', 'emulsify' ), get_search_query() ),
	)
);

Timber::render( $templates, $context );
