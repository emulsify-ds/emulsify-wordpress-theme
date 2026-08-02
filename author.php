<?php
/**
 * Author template.
 *
 * @package Emulsify
 */

use Timber\Timber;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Author archives use the normal archive Twig fallback after adding an
// author-specific title to the shared Timber context.
$context = Timber::context();

if ( isset( $context['author'] ) ) {
	/* translators: %s is the author's display name. */
	$context['title'] = sprintf( __( 'Archive of %s', 'emulsify' ), $context['author']->name() );
}

Timber::render(
	array( '@templates/author.twig', '@templates/archive.twig' ),
	$context
);
