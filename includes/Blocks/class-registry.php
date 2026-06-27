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
		$components = new Component_Locator();

		( new Acf_Blocks( $components ) )->register();
		( new Native_Blocks( $components ) )->register();
	}
}
