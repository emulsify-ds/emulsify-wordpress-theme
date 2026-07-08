<?php
/**
 * Coordinates optional block editor governance.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Editor;

/**
 * Parent-theme block editor policy hooks.
 */
final class Policy {

	/**
	 * Shared policy option resolver.
	 *
	 * @var PolicyOptions
	 */
	private $options;

	/**
	 * Allowed block type policy.
	 *
	 * @var AllowedBlockTypes
	 */
	private $allowed_blocks;

	/**
	 * Pattern visibility policy.
	 *
	 * @var PatternGovernance
	 */
	private $patterns;

	/**
	 * User-created pattern permissions policy.
	 *
	 * @var UserPatternPermissions
	 */
	private $user_patterns;

	/**
	 * Block support/style override policy.
	 *
	 * @var BlockSupportOverrides
	 */
	private $support_overrides;

	/**
	 * Constructor.
	 *
	 * @param PolicyOptions|null          $options           Policy option resolver.
	 * @param AllowedBlockTypes|null      $allowed_blocks    Allowed block policy.
	 * @param PatternGovernance|null      $patterns          Pattern visibility policy.
	 * @param UserPatternPermissions|null $user_patterns     User pattern policy.
	 * @param BlockSupportOverrides|null  $support_overrides Block support override policy.
	 */
	public function __construct(
		?PolicyOptions $options = null,
		?AllowedBlockTypes $allowed_blocks = null,
		?PatternGovernance $patterns = null,
		?UserPatternPermissions $user_patterns = null,
		?BlockSupportOverrides $support_overrides = null
	) {
		$this->options           = $options ?? new PolicyOptions();
		$this->patterns          = $patterns ?? new PatternGovernance();
		$this->allowed_blocks    = $allowed_blocks ?? new AllowedBlockTypes( $this->options, $this->patterns );
		$this->user_patterns     = $user_patterns ?? new UserPatternPermissions( $this->options );
		$this->support_overrides = $support_overrides ?? new BlockSupportOverrides( $this->options );
	}

	/**
	 * Registers block editor policy hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'allowed_block_types_all', array( $this, 'allowed_block_types' ), 20, 2 );
		add_filter( 'block_editor_settings_all', array( $this, 'block_editor_settings' ), 20, 2 );
		add_filter( 'register_post_type_args', array( $this, 'post_type_args' ), 10, 2 );
		add_filter( 'block_type_metadata_settings', array( $this, 'block_type_metadata_settings' ), 20, 2 );
		add_filter( 'register_block_type_args', array( $this, 'register_block_type_args' ), 20, 2 );
	}

	/**
	 * Filters allowed block types for the current editor context.
	 *
	 * @param array|bool $allowed_block_types Existing WordPress allowed block types value.
	 * @param mixed      $context             Block editor context.
	 * @return array|bool Filtered allowed block types.
	 */
	public function allowed_block_types( $allowed_block_types, $context ) {
		return $this->allowed_blocks->filter( $allowed_block_types, $context );
	}

	/**
	 * Filters block editor settings for optional pattern governance.
	 *
	 * @param array $settings Block editor settings.
	 * @param mixed $context  Block editor context.
	 * @return array Filtered settings.
	 */
	public function block_editor_settings( array $settings, $context = null ): array {
		$options  = $this->options->get( $context );
		$settings = $this->patterns->filter_editor_settings( $settings, $context, $options );

		return $this->user_patterns->filter_editor_settings( $settings, $context, $options );
	}

	/**
	 * Optionally changes the creation capability for user-created patterns.
	 *
	 * @param array  $args      Post type registration arguments.
	 * @param string $post_type Post type slug.
	 * @return array Filtered post type arguments.
	 */
	public function post_type_args( array $args, string $post_type ): array {
		return $this->user_patterns->post_type_args( $args, $post_type );
	}

	/**
	 * Applies configured support/style overrides to metadata-derived settings.
	 *
	 * @param array $settings Block type metadata settings.
	 * @param array $metadata Block metadata.
	 * @return array Filtered metadata settings.
	 */
	public function block_type_metadata_settings( array $settings, array $metadata ): array {
		return $this->support_overrides->block_type_metadata_settings( $settings, $metadata );
	}

	/**
	 * Applies configured support/style overrides to final block registration args.
	 *
	 * @param array  $args       Block type registration arguments.
	 * @param string $block_type Block type name.
	 * @return array Filtered block type registration arguments.
	 */
	public function register_block_type_args( array $args, string $block_type ): array {
		return $this->support_overrides->register_block_type_args( $args, $block_type );
	}
}
