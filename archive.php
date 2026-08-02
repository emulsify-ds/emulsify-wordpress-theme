<?php
/**
 * Archive template.
 *
 * @package Emulsify
 */

use Timber\Timber;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Keep WordPress route controllers in the global namespace. Services that need
// namespacing live under includes/ and feed data into these Timber templates.
$templates = array( '@templates/archive.twig', '@templates/index.twig' );

$page_title = __( 'Archive', 'emulsify' );
if ( is_day() ) {
	/* translators: %s is the archive date. */
	$page_title = sprintf( __( 'Archive: %s', 'emulsify' ), get_the_date( 'D M Y' ) );
} elseif ( is_month() ) {
	/* translators: %s is the archive month and year. */
	$page_title = sprintf( __( 'Archive: %s', 'emulsify' ), get_the_date( 'M Y' ) );
} elseif ( is_year() ) {
	/* translators: %s is the archive year. */
	$page_title = sprintf( __( 'Archive: %s', 'emulsify' ), get_the_date( 'Y' ) );
} elseif ( is_tag() ) {
	$page_title = single_tag_title( '', false );
} elseif ( is_category() ) {
	$page_title = single_cat_title( '', false );
} elseif ( is_post_type_archive() ) {
	$page_title = post_type_archive_title( '', false );
	// Allow child themes to provide archive-{post_type}.twig before the generic
	// archive fallback without adding more PHP template files.
	array_unshift( $templates, '@templates/archive-' . get_post_type() . '.twig' );
}

$context = Timber::context(
	array(
		'title' => $page_title,
	)
);

Timber::render( $templates, $context );
