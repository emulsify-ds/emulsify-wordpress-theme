<?php
/**
 * The Template for displaying all single posts.
 *
 * @package Emulsify
 */

namespace App;

use Timber\Timber;

$context   = Timber::context();
$wp_post   = $context['post'];
$templates = array( 'templates/single-' . $wp_post->post_type . '.twig', 'templates/single.twig' );

if ( post_password_required( $wp_post->ID ) ) {
	$templates = 'templates/single-password.twig';
}

Timber::render( $templates, $context );
