<?php
/**
 * Normalizes block editor policy options.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Editor;

/**
 * Shared editor policy options.
 */
final class PolicyOptions {

	/**
	 * Gets editor policy options.
	 *
	 * @param mixed $context Optional editor context.
	 * @return array Editor policy options.
	 */
	public function get( $context = null ): array {
		$options = array(
			'allowed_block_types'                  => null,
			'merge_allowed_block_types'            => true,
			'auto_allow_pattern_blocks'            => false,
			'pattern_directories'                  => null,
			'pattern_namespaces'                   => array(),
			'disable_user_patterns_for_non_admins' => false,
			'admin_capability'                     => 'manage_options',
			'restrict_wp_block_creation'           => false,
			'wp_block_create_capability'           => 'manage_options',
			'block_support_overrides'              => array(),
		);

		/**
		 * Filters block editor policy options.
		 *
		 * Defaults preserve the parent theme's current behavior. Child themes can
		 * opt into individual policies by returning changed option values.
		 *
		 * @param array $options Editor policy options.
		 * @param mixed $context Optional editor context.
		 */
		$filtered = apply_filters( 'emulsify_theme_editor_policy_options', $options, $context );

		if ( is_array( $filtered ) ) {
			$options = array_merge( $options, $filtered );
		}

		$options['admin_capability']           = $this->capability( $options['admin_capability'] ?? 'manage_options' );
		$options['wp_block_create_capability'] = $this->capability( $options['wp_block_create_capability'] ?? 'manage_options' );

		return $options;
	}

	/**
	 * Normalizes a capability string.
	 *
	 * @param mixed $capability Capability candidate.
	 * @return string Capability or an empty string.
	 */
	private function capability( $capability ): string {
		if ( ! is_scalar( $capability ) ) {
			return '';
		}

		$normalized = preg_replace( '/[^a-zA-Z0-9_]+/', '', (string) $capability );

		return is_string( $normalized ) ? trim( $normalized ) : '';
	}
}
