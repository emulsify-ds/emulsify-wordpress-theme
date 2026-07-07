<?php
/**
 * Applies optional block support/style overrides.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Editor;

/**
 * Block support and style override policy.
 */
final class BlockSupportOverrides {

	/**
	 * Shared policy option resolver.
	 *
	 * @var PolicyOptions
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param PolicyOptions $options Policy option resolver.
	 */
	public function __construct( PolicyOptions $options ) {
		$this->options = $options;
	}

	/**
	 * Applies configured support/style overrides to metadata-derived settings.
	 *
	 * @param array $settings Block type metadata settings.
	 * @param array $metadata Block metadata.
	 * @return array Filtered metadata settings.
	 */
	public function block_type_metadata_settings( array $settings, array $metadata ): array {
		$block_name = isset( $metadata['name'] ) && is_scalar( $metadata['name'] ) ? (string) $metadata['name'] : '';

		return $this->apply( $settings, $block_name, 'block_type_metadata_settings' );
	}

	/**
	 * Applies configured support/style overrides to final block registration args.
	 *
	 * @param array  $args       Block type registration arguments.
	 * @param string $block_type Block type name.
	 * @return array Filtered block type registration arguments.
	 */
	public function register_block_type_args( array $args, string $block_type ): array {
		return $this->apply( $args, $block_type, 'register_block_type_args' );
	}

	/**
	 * Applies configured block support/style overrides.
	 *
	 * @param array  $settings   Block type settings or registration arguments.
	 * @param string $block_name Block type name.
	 * @param string $source     Current WordPress filter name.
	 * @return array Filtered block settings.
	 */
	private function apply( array $settings, string $block_name, string $source ): array {
		$options   = $this->options->get();
		$overrides = isset( $options['block_support_overrides'] ) && is_array( $options['block_support_overrides'] )
			? $options['block_support_overrides']
			: array();

		/**
		 * Filters block support/style overrides for a block registration pass.
		 *
		 * Use the "*" key for all blocks and a block name key such as
		 * "core/button" for block-specific changes.
		 *
		 * @param array  $overrides  Configured override map.
		 * @param string $block_name Current block name.
		 * @param array  $settings   Current settings or registration arguments.
		 * @param string $source     Current WordPress filter name.
		 * @param array  $options    Editor policy options.
		 */
		$filtered = apply_filters( 'emulsify_theme_block_support_overrides', $overrides, $block_name, $settings, $source, $options );

		if ( is_array( $filtered ) ) {
			$overrides = $filtered;
		}

		foreach ( array( '*', $block_name ) as $key ) {
			if ( isset( $overrides[ $key ] ) && is_array( $overrides[ $key ] ) ) {
				// Apply global overrides first and block-specific overrides second so
				// a child theme can set broad defaults with targeted exceptions.
				$settings = $this->merge_override( $settings, $overrides[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Recursively applies an override array.
	 *
	 * @param array $base     Base array.
	 * @param array $override Override array.
	 * @return array Merged array.
	 */
	private function merge_override( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if (
				array_key_exists( $key, $base )
				&& is_array( $base[ $key ] )
				&& is_array( $value )
				&& ! array_is_list( $base[ $key ] )
				&& ! array_is_list( $value )
			) {
				$base[ $key ] = $this->merge_override( $base[ $key ], $value );
				continue;
			}

			$base[ $key ] = $value;
		}

		return $base;
	}
}
