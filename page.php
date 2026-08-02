<?php
/**
 * The template for displaying all pages.
 *
 * @package Emulsify
 */

use Timber\Timber;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Page rendering is intentionally thin: route data comes from Timber context,
// while markup and extension points live in Twig.
$context = Timber::context();

Timber::render( '@templates/page.twig', $context );
