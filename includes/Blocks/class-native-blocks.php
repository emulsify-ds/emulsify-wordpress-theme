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
		 * @param Component_Locator $components  Component locator instance.
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
				$skipped[] = array(
					'type'    => 'native_filtered_block_name',
					'name'    => $name,
					'reason'  => 'Duplicate filtered native block name.',
					'kept'    => $seen_names[ $name ],
					'skipped' => $component,
				);
				continue;
			}

			if ( '' !== $name && $this->native_block_registered( $name ) ) {
				$skipped[] = array(
					'type'    => 'native_registered_block_name',
					'name'    => $name,
					'reason'  => 'Native block name is already registered.',
					'kept'    => array(
						'name'   => $name,
						'source' => 'existing',
					),
					'skipped' => $component,
				);
				continue;
			}

			if ( '' !== $name ) {
				$seen_names[ $name ] = $component;
			}

			register_block_type( (string) $component['path'] );
		}

		$this->debug_skipped_duplicates(
			array_merge(
				$this->components->skipped_duplicates( 'native' ),
				$skipped
			)
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

	/**
	 * Logs duplicate block records and optionally exposes admin notices.
	 *
	 * @param array $duplicates Duplicate records.
	 * @return void
	 */
	private function debug_skipped_duplicates( array $duplicates ): void {
		if ( empty( $duplicates ) || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$messages = array();

		foreach ( $duplicates as $duplicate ) {
			$messages[] = $this->duplicate_message( $duplicate );
			error_log( '[Emulsify] ' . end( $messages ) );
		}

		$this->admin_notice( $messages );
	}

	/**
	 * Adds an admin-only notice for skipped duplicate blocks.
	 *
	 * @param array $messages Notice messages.
	 * @return void
	 */
	private function admin_notice( array $messages ): void {
		if ( empty( $messages ) || ! function_exists( 'add_action' ) || ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}

		add_action(
			'admin_notices',
			static function () use ( $messages ): void {
				if ( function_exists( 'current_user_can' ) && ! current_user_can( 'edit_theme_options' ) ) {
					return;
				}

				echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Emulsify skipped duplicate native block definitions.', 'emulsify' ) . '</strong></p><ul>';

				foreach ( $messages as $message ) {
					echo '<li>' . esc_html( $message ) . '</li>';
				}

				echo '</ul></div>';
			}
		);
	}

	/**
	 * Formats a duplicate debug message.
	 *
	 * @param array $duplicate Duplicate record.
	 * @return string Debug message.
	 */
	private function duplicate_message( array $duplicate ): string {
		$kept    = isset( $duplicate['kept']['metadata_path'] ) ? $duplicate['kept']['metadata_path'] : ( $duplicate['kept']['name'] ?? 'unknown' );
		$skipped = isset( $duplicate['skipped']['metadata_path'] ) ? $duplicate['skipped']['metadata_path'] : ( $duplicate['skipped']['name'] ?? 'unknown' );

		return sprintf(
			'%s "%s" skipped %s in favor of %s.',
			isset( $duplicate['reason'] ) ? $duplicate['reason'] : 'Duplicate block definition.',
			isset( $duplicate['name'] ) ? $duplicate['name'] : 'unknown',
			$skipped,
			$kept
		);
	}
}
