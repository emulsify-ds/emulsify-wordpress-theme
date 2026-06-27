<?php
/**
 * Registers optional ACF blocks rendered by Timber.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Blocks;

/**
 * ACF/Twig block integration.
 */
final class Acf_Blocks {

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
	 * Registers optional ACF hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'acf_register_block_type' ) ) {
			return;
		}

		add_action( 'acf/init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers component metadata as ACF blocks.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		if ( ! function_exists( 'acf_register_block_type' ) ) {
			return;
		}

		foreach ( $this->components->acf_components() as $component ) {
			$metadata = $this->metadata( $component['metadata_path'] );

			if ( null === $metadata ) {
				continue;
			}

			acf_register_block_type( $this->block_args( $component, $metadata ) );
		}
	}

	/**
	 * Renders an ACF block with Timber.
	 *
	 * @param array  $block      Block settings and attributes.
	 * @param string $content    Inner block content.
	 * @param bool   $is_preview TRUE during editor preview render.
	 * @param mixed  $post_id    Current post ID.
	 * @return void
	 */
	public function render_block( $block, $content = '', $is_preview = false, $post_id = 0 ): void {
		if ( ! is_array( $block ) ) {
			$this->render_error( __( 'Block rendering requires valid block metadata.', 'emulsify' ) );
			return;
		}

		if ( ! class_exists( '\Timber\Timber' ) ) {
			$this->render_error( __( 'Block rendering requires Timber.', 'emulsify' ) );
			return;
		}

		$template = $this->template( $block );

		if ( '' === $template ) {
			$this->render_error( __( 'Block rendering error: no Twig template defined.', 'emulsify' ) );
			return;
		}

		$context               = \Timber\Timber::context();
		$context['block']      = $block;
		$context['content']    = is_scalar( $content ) ? (string) $content : '';
		$context['fields']     = $this->fields( $post_id );
		$context['is_preview'] = (bool) $is_preview;

		try {
			\Timber\Timber::render( $template, $context );
		} catch ( \Throwable $throwable ) {
			$this->render_error( $throwable->getMessage() );
		}
	}

	/**
	 * Reads component metadata.
	 *
	 * @param string $path Absolute metadata path.
	 * @return array|null Component metadata, or null when invalid.
	 */
	private function metadata( string $path ): ?array {
		$contents = file_get_contents( $path );

		if ( ! is_string( $contents ) ) {
			return null;
		}

		$metadata = json_decode( $contents, true );

		return is_array( $metadata ) && JSON_ERROR_NONE === json_last_error() ? $metadata : null;
	}

	/**
	 * Builds ACF block registration arguments.
	 *
	 * @param array $component Component record.
	 * @param array $metadata  Component metadata.
	 * @return array ACF block arguments.
	 */
	private function block_args( array $component, array $metadata ): array {
		$defaults = array(
			'name'  => 'emulsify/' . $component['slug'],
			'title' => ucwords( str_replace( '-', ' ', $component['slug'] ) ),
			'mode'  => 'preview',
		);

		$args                    = array_merge( $defaults, $metadata );
		$args['render_callback'] = array( $this, 'render_block' );
		$args['twig_template']   = $component['template'];
		$args['data']            = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
		$args['data']['twig_template'] = $component['template'];

		return $args;
	}

	/**
	 * Gets ACF fields when ACF is active.
	 *
	 * @param mixed $post_id Current post ID.
	 * @return array ACF field values.
	 */
	private function fields( $post_id ): array {
		if ( ! function_exists( 'get_fields' ) ) {
			return array();
		}

		$fields = get_fields( $post_id );

		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Gets the block Twig template.
	 *
	 * @param array $block Block settings and attributes.
	 * @return string Template path.
	 */
	private function template( array $block ): string {
		if ( ! empty( $block['data']['twig_template'] ) && is_string( $block['data']['twig_template'] ) ) {
			return $block['data']['twig_template'];
		}

		if ( ! empty( $block['twig_template'] ) && is_string( $block['twig_template'] ) ) {
			return $block['twig_template'];
		}

		return '';
	}

	/**
	 * Renders an editor-only block error.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	private function render_error( string $message ): void {
		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		printf(
			'<p><strong>%s</strong> %s</p>',
			esc_html__( 'Emulsify block error:', 'emulsify' ),
			esc_html( $message )
		);
	}
}
