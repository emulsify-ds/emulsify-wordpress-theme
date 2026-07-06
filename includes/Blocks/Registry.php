<?php
/**
 * Coordinates block integrations.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Blocks;

/**
 * Registers optional block integrations.
 */
final class Registry {

	/**
	 * Registers block integrations.
	 *
	 * @return void
	 */
	public function register(): void {
		$components = new ComponentLocator();

		// Share one locator so ACF/Twig and native block registration use the same
		// request-local child-first component index and duplicate diagnostics.
		( new AcfBlocks( $components ) )->register();
		( new NativeBlocks( $components ) )->register();
	}
}
