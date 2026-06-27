<?php
/**
 * Registers native Gutenberg blocks.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Blocks;

/**
 * Native WordPress Block API integration.
 */
final class Native_Blocks {

	/**
	 * Component locator.
	 *
	 * @var Component_Locator
	 */
	private $components;

	/**
	 * Constructor.
	 *
	 * @param Component_Locator|null $components Component locator.
	 */
	public function __construct( ?Component_Locator $components = null ) {
		$this->components = $components ?? new Component_Locator();
	}

	/**
	 * Registers native block hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers component folders that contain block.json.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		foreach ( $this->components->native_block_directories() as $component ) {
			register_block_type( $component['path'] );
		}
	}
}
