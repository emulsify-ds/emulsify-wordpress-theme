<?php
/**
 * The Template for displaying all single posts.
 *
 * @package Emulsify
 */

use Timber\Timber;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$context   = Timber::context();
$wp_post   = $context['post'] ?? null;
$post_type = $wp_post->post_type ?? get_post_type();
$templates = array( '@templates/single.twig' );

if ( $post_type ) {
	// Child themes can add single-{post_type}.twig to override one post type
	// without duplicating the generic single.twig fallback.
	array_unshift( $templates, '@templates/single-' . $post_type . '.twig' );
}

if ( $wp_post && post_password_required( $wp_post->ID ) ) {
	$templates = '@templates/single-password.twig';
}

Timber::render( $templates, $context );
