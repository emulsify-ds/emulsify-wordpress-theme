<?php
/**
 * The template for displaying all pages.
 *
 * @package Emulsify
 */

namespace App;

use Timber\Timber;

$context = Timber::context();

Timber::render( '@templates/page.twig', $context );
