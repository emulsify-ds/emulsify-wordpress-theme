<?php
/**
 * Registers optional ACF blocks rendered by Timber.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme;

/**
 * ACF block integration.
 */
final class Blocks {

	/**
	 * Registers optional block hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'acf/init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers compiled component blocks with ACF when available.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		if ( ! function_exists( 'acf_register_block_type' ) || ! class_exists( '\Timber\Timber' ) ) {
			return;
		}

		$components_dir = get_stylesheet_directory() . '/dist/components';

		if ( ! is_dir( $components_dir ) || ! is_readable( $components_dir ) ) {
			return;
		}

		foreach ( new \DirectoryIterator( $components_dir ) as $item ) {
			if ( ! $item->isDir() || $item->isDot() ) {
				continue;
			}

			$slug      = $item->getFilename();
			$json_path = $item->getPathname() . '/' . $slug . '.component.json';
			$twig_path = 'dist/components/' . $slug . '/' . $slug . '.twig';

			if ( ! is_readable( $json_path ) || ! is_readable( get_stylesheet_directory() . '/' . $twig_path ) ) {
				continue;
			}

			$config_contents = file_get_contents( $json_path );

			if ( ! is_string( $config_contents ) ) {
				continue;
			}

			$config = json_decode( $config_contents, true );

			if ( ! is_array( $config ) || JSON_ERROR_NONE !== json_last_error() ) {
				continue;
			}

			$block_args = array_merge(
				array(
					'name'            => 'emulsify/' . sanitize_title( $slug ),
					'title'           => ucwords( str_replace( '-', ' ', $slug ) ),
					'mode'            => 'preview',
					'render_callback' => array( $this, 'render_block' ),
					'twig_template'   => $twig_path,
				),
				$config
			);

			acf_register_block_type( $block_args );
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

		$context               = \Timber\Timber::context();
		$context['block']      = $block;
		$context['content']    = is_scalar( $content ) ? (string) $content : '';
		$context['fields']     = $this->fields( $post_id );
		$context['is_preview'] = (bool) $is_preview;
		$template              = $this->template( $block );

		if ( '' === $template ) {
			$this->render_error( __( 'Block rendering error: no Twig template defined.', 'emulsify' ) );
			return;
		}

		try {
			\Timber\Timber::render( $template, $context );
		} catch ( \Throwable $throwable ) {
			$this->render_error( $throwable->getMessage() );
		}
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
		if ( current_user_can( 'edit_posts' ) ) {
			printf(
				'<p><strong>%s</strong> %s</p>',
				esc_html__( 'Emulsify block error:', 'emulsify' ),
				esc_html( $message )
			);
		}
	}
}
