<?php
/**
 * Archive template.
 *
 * @package Emulsify
 */

namespace App;

use Timber\Timber;

$templates = array( 'templates/archive.twig', 'templates/index.twig' );

$page_title = 'Archive';
if ( is_day() ) {
	$page_title = 'Archive: ' . get_the_date( 'D M Y' );
} elseif ( is_month() ) {
	$page_title = 'Archive: ' . get_the_date( 'M Y' );
} elseif ( is_year() ) {
	$page_title = 'Archive: ' . get_the_date( 'Y' );
} elseif ( is_tag() ) {
	$page_title = single_tag_title( '', false );
} elseif ( is_category() ) {
	$page_title = single_cat_title( '', false );
} elseif ( is_post_type_archive() ) {
	$page_title = post_type_archive_title( '', false );
	array_unshift( $templates, 'templates/archive-' . get_post_type() . '.twig' );
}

$context = Timber::context(
	array(
		'title' => $page_title,
	)
);

Timber::render( $templates, $context );
