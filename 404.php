<?php
/**
 * Page 404 template.
 *
 * @package Emulsify
 */

use Timber\Timber;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WordPress loads this file directly from the template hierarchy; keep route
// controllers in the global namespace and put reusable logic in includes/.
$context = Timber::context();
Timber::render( '@templates/404.twig', $context );
