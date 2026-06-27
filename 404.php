<?php
/**
 * Page 404 template.
 *
 * @package Emulsify
 */

namespace App;

use Timber\Timber;

$context = Timber::context();
Timber::render( '@templates/404.twig', $context );
