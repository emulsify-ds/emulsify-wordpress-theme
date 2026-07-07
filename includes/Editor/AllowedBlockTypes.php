<?php
/**
 * Applies optional allowed block type policy.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Editor;

/**
 * Allowed block type governance.
 */
final class AllowedBlockTypes {

	/**
	 * Shared policy option resolver.
	 *
	 * @var PolicyOptions
	 */
	private $options;

	/**
	 * Pattern governance helper.
	 *
	 * @var PatternGovernance
	 */
	private $patterns;

	/**
	 * Constructor.
	 *
	 * @param PolicyOptions     $options  Policy option resolver.
	 * @param PatternGovernance $patterns Pattern governance helper.
	 */
	public function __construct( PolicyOptions $options, PatternGovernance $patterns ) {
		$this->options  = $options;
		$this->patterns = $patterns;
	}

	/**
	 * Filters allowed block types for the current editor context.
	 *
	 * @param array|bool $allowed_block_types Existing WordPress allowed block types value.
	 * @param mixed      $context             Block editor context.
	 * @return array|bool Filtered allowed block types.
	 */
	public function filter( $allowed_block_types, $context ) {
		$options   = $this->options->get( $context );
		$post_type = $this->post_type( $context );
		$blocks    = $this->configured_allowed_block_types( $options['allowed_block_types'] ?? null, $post_type );

		/**
		 * Filters the resolved allowed block type list before WordPress receives it.
		 *
		 * Return null to preserve the incoming WordPress value. Return an array
		 * of block names to enable an allow list for the current editor context.
		 *
		 * @param array|null $blocks              Resolved configured block names, or null for no policy.
		 * @param string     $post_type           Current post type when available.
		 * @param mixed      $context             Block editor context.
		 * @param array|bool $allowed_block_types Incoming WordPress allowed block types value.
		 * @param array      $options             Editor policy options.
		 */
		$filtered = apply_filters( 'emulsify_theme_allowed_block_types', $blocks, $post_type, $context, $allowed_block_types, $options );

		if ( null === $filtered || ! is_array( $filtered ) ) {
			// Null is the "no opinion" value. Returning the incoming WordPress value
			// preserves default editor behavior unless a child theme configures a policy.
			return $allowed_block_types;
		}

		$blocks = BlockNames::normalize( $filtered );

		if ( ! empty( $options['auto_allow_pattern_blocks'] ) ) {
			// Pattern JSON can reference supporting blocks that are easy to forget
			// in a manual allow list. This opt-in merge prevents configured patterns
			// from becoming impossible to insert.
			$blocks = array_merge( $blocks, $this->patterns->pattern_block_names( $options ) );
		}

		if ( is_array( $allowed_block_types ) && ! empty( $options['merge_allowed_block_types'] ) ) {
			$blocks = array_merge( $allowed_block_types, $blocks );
		}

		return array_values( array_unique( BlockNames::normalize( $blocks ) ) );
	}

	/**
	 * Resolves configured allowed blocks for a post type.
	 *
	 * @param mixed  $config    Allowed block type configuration.
	 * @param string $post_type Post type when available.
	 * @return array|null Block names, or null when no policy is configured.
	 */
	private function configured_allowed_block_types( $config, string $post_type ): ?array {
		if ( ! is_array( $config ) ) {
			return null;
		}

		if ( array_is_list( $config ) ) {
			return $config;
		}

		$blocks     = array();
		$configured = false;

		foreach ( array( 'default', '*' ) as $key ) {
			if ( array_key_exists( $key, $config ) && is_array( $config[ $key ] ) ) {
				$blocks     = array_merge( $blocks, $config[ $key ] );
				$configured = true;
			}
		}

		foreach ( array( 'by_post_type', 'post_types' ) as $group_key ) {
			if ( '' === $post_type || ! isset( $config[ $group_key ] ) || ! is_array( $config[ $group_key ] ) ) {
				continue;
			}

			$post_type_blocks = array_key_exists( $post_type, $config[ $group_key ] ) ? $config[ $group_key ][ $post_type ] : null;

			if ( is_array( $post_type_blocks ) ) {
				$blocks     = array_merge( $blocks, $post_type_blocks );
				$configured = true;
			}
		}

		if ( '' !== $post_type && array_key_exists( $post_type, $config ) && is_array( $config[ $post_type ] ) ) {
			$blocks     = array_merge( $blocks, $config[ $post_type ] );
			$configured = true;
		}

		return $configured ? $blocks : null;
	}

	/**
	 * Gets the post type from a block editor context.
	 *
	 * @param mixed $context Block editor context.
	 * @return string Post type or an empty string.
	 */
	private function post_type( $context ): string {
		if ( is_object( $context ) && isset( $context->post ) ) {
			if ( function_exists( 'get_post_type' ) ) {
				$post_type = get_post_type( $context->post );

				return is_string( $post_type ) ? $post_type : '';
			}

			if ( is_object( $context->post ) && isset( $context->post->post_type ) && is_scalar( $context->post->post_type ) ) {
				return (string) $context->post->post_type;
			}
		}

		if ( is_object( $context ) && isset( $context->post_type ) && is_scalar( $context->post_type ) ) {
			return (string) $context->post_type;
		}

		return '';
	}
}
