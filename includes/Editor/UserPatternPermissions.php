<?php
/**
 * Applies optional user-created pattern restrictions.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Editor;

/**
 * User-created pattern UI and capability governance.
 */
final class UserPatternPermissions {

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
	 * Filters block editor settings for optional user pattern UI governance.
	 *
	 * @param array $settings Block editor settings.
	 * @param mixed $context  Block editor context.
	 * @param array $options  Editor policy options.
	 * @return array Filtered settings.
	 */
	public function filter_editor_settings( array $settings, $context, array $options ): array {
		unset( $context );

		$disable_user_patterns = ! empty( $options['disable_user_patterns_for_non_admins'] );

		if ( $disable_user_patterns && ! $this->current_user_can( (string) $options['admin_capability'] ) ) {
			// WordPress still registers project/theme patterns; this only hides the
			// user-created pattern UI for users below the configured capability.
			$settings['enableUserPatterns'] = false;
		}

		return $settings;
	}

	/**
	 * Optionally changes the creation capability for user-created patterns.
	 *
	 * @param array  $args      Post type registration arguments.
	 * @param string $post_type Post type slug.
	 * @return array Filtered post type arguments.
	 */
	public function post_type_args( array $args, string $post_type ): array {
		if ( 'wp_block' !== $post_type ) {
			return $args;
		}

		$options = $this->options->get();

		if ( empty( $options['restrict_wp_block_creation'] ) ) {
			return $args;
		}

		$capability = isset( $options['wp_block_create_capability'] ) ? (string) $options['wp_block_create_capability'] : '';

		if ( '' === $capability ) {
			return $args;
		}

		$args['map_meta_cap']                 = true;
		$args['capabilities']                 = isset( $args['capabilities'] ) && is_array( $args['capabilities'] ) ? $args['capabilities'] : array();
		$args['capabilities']['create_posts'] = $capability;

		return $args;
	}

	/**
	 * Safely checks the current user's capabilities.
	 *
	 * @param string $capability Capability name.
	 * @return bool TRUE when the current user has the capability.
	 */
	private function current_user_can( string $capability ): bool {
		return '' !== $capability && function_exists( 'current_user_can' ) && current_user_can( $capability );
	}
}
