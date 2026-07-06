<?php
/**
 * Theme bootstrap.
 *
 * @package Emulsify
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

require_once __DIR__ . '/includes/Bootstrap.php';

// Keep functions.php as the smallest possible WordPress entry point. Runtime
// behavior belongs in Bootstrap-managed services under includes/.
\Emulsify\Theme\Bootstrap::init( __DIR__ );
