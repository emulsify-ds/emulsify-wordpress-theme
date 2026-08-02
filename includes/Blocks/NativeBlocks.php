<?php
/**
 * Registers native Gutenberg blocks.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Blocks;

use Emulsify\Theme\Support\Diagnostics;

/**
 * Native WordPress Block API integration.
 */
final class NativeBlocks {

	/**
	 * Component locator.
	 *
	 * @var ComponentLocator
	 */
	private $components;

	/**
	 * Constructor.
	 *
	 * @param ComponentLocator|null $components Component locator.
	 */
	public function __construct( ?ComponentLocator $components = null ) {
		$this->components = $components ?? new ComponentLocator();
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
			// Older or incomplete WordPress contexts may not expose the Block API.
			// Smoke tests can still load the class without registering anything.
			return;
		}

		$directories = $this->components->native_block_directories();

		/**
		 * Filters native block directories before they are registered.
		 *
		 * Directory records should include path, relative, metadata_path, source,
		 * and name keys when known. Duplicate native block names are checked after
		 * this filter runs.
		 *
		 * @param array             $directories Native block directory records.
		 * @param ComponentLocator $components  Component locator instance.
		 */
		$filtered = apply_filters( 'emulsify_theme_native_block_directories', $directories, $this->components );

		if ( is_array( $filtered ) ) {
			$directories = $filtered;
		}

		$seen_names = array();
		$skipped    = array();

		foreach ( $directories as $component ) {
			if ( ! is_array( $component ) || empty( $component['path'] ) ) {
				continue;
			}

			$name = ! empty( $component['name'] ) && is_scalar( $component['name'] ) ? trim( (string) $component['name'] ) : '';

			if ( '' !== $name && isset( $seen_names[ $name ] ) ) {
				// Filters may merge or rename block directories. Keep the first
				// discovered name and surface later duplicates only in debug/admin contexts.
				$skipped[] = Diagnostics::duplicate_record(
					'native_filtered_block_name',
					$name,
					$seen_names[ $name ],
					$component,
					'Duplicate filtered native block name.'
				);
				continue;
			}

			if ( '' !== $name && $this->native_block_registered( $name ) ) {
				$skipped[] = Diagnostics::duplicate_record(
					'native_registered_block_name',
					$name,
					array(
						'name'   => $name,
						'source' => 'existing',
					),
					$component,
					'Native block name is already registered.'
				);
				continue;
			}

			if ( '' !== $name ) {
				$seen_names[ $name ] = $component;
			}

			register_block_type( (string) $component['path'] );
		}

		Diagnostics::report_duplicates(
			array_merge(
				$this->components->skipped_duplicates( 'native' ),
				$skipped
			),
			function_exists( '__' ) ? __( 'Emulsify skipped duplicate native block definitions.', 'emulsify' ) : 'Emulsify skipped duplicate native block definitions.'
		);
	}

	/**
	 * Checks whether WordPress already knows about a native block name.
	 *
	 * @param string $name Native block name.
	 * @return bool TRUE when the block is already registered.
	 */
	private function native_block_registered( string $name ): bool {
		return class_exists( '\WP_Block_Type_Registry' )
			&& \WP_Block_Type_Registry::get_instance()->is_registered( $name );
	}
}
