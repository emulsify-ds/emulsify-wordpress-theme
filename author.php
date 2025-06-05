<?php
/**
 * Author template.
 *
 * @package Emulsify
 */

namespace App;

use Timber\Timber;

$context = Timber::context();

if ( isset( $context['author'] ) ) {
	/* translators: %s is the author’s display name. */
	$context['title'] = sprintf( __( 'Archive of %s', 'timber-starter' ), $context['author']->name() );
}

Timber::render(
	array( 'templates/author.twig', 'templates/archive.twig' ),
	$context
);
