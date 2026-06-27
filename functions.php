<?php
/**
 * Theme bootstrap.
 *
 * @package Emulsify
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

require_once __DIR__ . '/includes/class-bootstrap.php';

\Emulsify\Theme\Bootstrap::init( __DIR__ );
