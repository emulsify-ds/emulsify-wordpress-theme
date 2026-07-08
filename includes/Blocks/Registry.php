<?php
/**
 * Coordinates block integrations.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Blocks;

use Emulsify\Theme\Support\AssetManifest;

/**
 * Registers optional block integrations.
 */
final class Registry {

	/**
	 * Asset manifest reader.
	 *
	 * @var AssetManifest
	 */
	private $manifest;

	/**
	 * Constructor.
	 *
	 * @param AssetManifest|null $manifest Asset manifest reader.
	 */
	public function __construct( ?AssetManifest $manifest = null ) {
		$this->manifest = $manifest ?? new AssetManifest();
	}

	/**
	 * Registers block integrations.
	 *
	 * @return void
	 */
	public function register(): void {
		$components = new ComponentLocator();

		// Share one locator so ACF/Twig and native block registration use the same
		// request-local child-first component index and duplicate diagnostics.
		( new AcfBlocks( $components, $this->manifest ) )->register();
		( new NativeBlocks( $components ) )->register();
	}
}
