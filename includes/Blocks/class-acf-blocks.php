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
	 * Duplicate ACF block registrations skipped by this registrar.
	 *
	 * @var array
	 */
	private $skipped_duplicates = array();

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

		$seen_names = array();

		foreach ( $this->components->acf_components() as $component ) {
			$metadata = $this->metadata( $component['metadata_path'] );

			if ( null === $metadata ) {
				continue;
			}

			/**
			 * Filters ACF/Twig component metadata before block arguments are built.
			 *
			 * Metadata is read from the component *.component.json file. Defaults
			 * such as name, title, and render_callback are merged after this filter.
			 *
			 * @param array $metadata  Component metadata.
			 * @param array $component Component discovery record.
			 */
			$filtered_metadata = apply_filters( 'emulsify_theme_acf_block_metadata', $metadata, $component );

			if ( is_array( $filtered_metadata ) ) {
				$metadata = $filtered_metadata;
			}

			$args = $this->block_args( $component, $metadata );

			/**
			 * Filters final ACF block registration arguments.
			 *
			 * Duplicate block names are checked after this filter so child themes
			 * and project plugins can intentionally alter the final ACF block name.
			 *
			 * @param array $args      ACF block registration arguments.
			 * @param array $component Component discovery record.
			 * @param array $metadata  Filtered component metadata.
			 */
			$filtered_args = apply_filters( 'emulsify_theme_acf_block_args', $args, $component, $metadata );

			if ( is_array( $filtered_args ) ) {
				$args = $filtered_args;
			}

			$args['name'] = $this->normalize_block_name(
				$args['name'] ?? '',
				isset( $component['slug'] ) ? (string) $component['slug'] : 'block'
			);
			$name         = $this->block_name( $args );

			if ( '' !== $name && isset( $seen_names[ $name ] ) ) {
				$this->record_skipped_duplicate(
					'acf_block_name',
					$name,
					$seen_names[ $name ],
					$this->component_record( $component, $args ),
					'Duplicate ACF block name after metadata defaults were merged.'
				);
				continue;
			}

			if ( '' !== $name && $this->acf_block_registered( $name ) ) {
				$this->record_skipped_duplicate(
					'acf_registered_block_name',
					$name,
					array(
						'name'   => $name,
						'source' => 'existing',
					),
					$this->component_record( $component, $args ),
					'ACF block name is already registered.'
				);
				continue;
			}

			if ( '' !== $name ) {
				$seen_names[ $name ] = $this->component_record( $component, $args );
			}

			acf_register_block_type( $args );
		}

		$this->debug_skipped_duplicates(
			array_merge(
				$this->components->skipped_duplicates( 'acf' ),
				$this->skipped_duplicates
			)
		);
	}

	/**
	 * Gets duplicate ACF block records skipped by this registrar.
	 *
	 * @return array Skipped duplicate records.
	 */
	public function skipped_duplicates(): array {
		return $this->skipped_duplicates;
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
			'name'  => 'emulsify-' . $component['slug'],
			'title' => ucwords( str_replace( '-', ' ', $component['slug'] ) ),
			'mode'  => 'preview',
		);

		$args                    = array_merge( $defaults, $metadata );
		$args['name']            = $this->normalize_block_name( $args['name'] ?? '', $component['slug'] );
		$args['render_callback'] = array( $this, 'render_block' );
		$args['twig_template']   = $component['template'];
		$args['data']            = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
		$args['data']['twig_template'] = $component['template'];

		return $args;
	}

	/**
	 * Normalizes an ACF PHP block name to an ACF-safe un-namespaced slug.
	 *
	 * ACF's PHP registration API expects names like "testimonial"; WordPress
	 * exposes the final editor block as "acf/testimonial".
	 *
	 * @param mixed  $name     Candidate ACF block name.
	 * @param string $fallback Component slug fallback.
	 * @return string ACF-safe block name.
	 */
	private function normalize_block_name( $name, string $fallback ): string {
		$candidate = is_scalar( $name ) ? (string) $name : '';

		if ( '' === trim( $candidate ) ) {
			$candidate = 'emulsify-' . $fallback;
		}

		$normalized = strtolower( trim( $candidate ) );
		$normalized = preg_replace( '/[^a-z0-9-]+/', '-', $normalized );
		$normalized = trim( (string) $normalized, '-' );

		if ( '' === $normalized ) {
			$normalized = 'emulsify-' . $fallback;
			$normalized = preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $normalized ) );
			$normalized = trim( (string) $normalized, '-' );
		}

		if ( '' === $normalized || ! preg_match( '/^[a-z]/', $normalized ) ) {
			$normalized = 'emulsify-' . $normalized;
		}

		return $normalized;
	}

	/**
	 * Gets a final ACF block name from registration arguments.
	 *
	 * @param array $args ACF block registration arguments.
	 * @return string Block name, or an empty string when unavailable.
	 */
	private function block_name( array $args ): string {
		return ! empty( $args['name'] ) && is_string( $args['name'] ) ? trim( $args['name'] ) : '';
	}

	/**
	 * Checks whether ACF already knows about a block name.
	 *
	 * @param string $name ACF block name.
	 * @return bool TRUE when the block is already registered.
	 */
	private function acf_block_registered( string $name ): bool {
		return function_exists( 'acf_get_block_type' ) && is_array( acf_get_block_type( $name ) );
	}

	/**
	 * Builds a debug record for an ACF component registration.
	 *
	 * @param array $component Component record.
	 * @param array $args      ACF block registration arguments.
	 * @return array Debug record.
	 */
	private function component_record( array $component, array $args ): array {
		return array(
			'name'          => $this->block_name( $args ),
			'relative'      => isset( $component['relative'] ) ? $component['relative'] : '',
			'source'        => isset( $component['source'] ) ? $component['source'] : '',
			'metadata_path' => isset( $component['metadata_path'] ) ? $component['metadata_path'] : '',
			'template'      => isset( $component['template'] ) ? $component['template'] : '',
		);
	}

	/**
	 * Records a skipped duplicate ACF block.
	 *
	 * @param string $type    Duplicate type.
	 * @param string $name    Duplicate block name.
	 * @param array  $kept    Higher-priority record.
	 * @param array  $skipped Lower-priority skipped record.
	 * @param string $reason  Human-readable reason.
	 * @return void
	 */
	private function record_skipped_duplicate( string $type, string $name, array $kept, array $skipped, string $reason ): void {
		$this->skipped_duplicates[] = array(
			'type'    => $type,
			'name'    => $name,
			'reason'  => $reason,
			'kept'    => $kept,
			'skipped' => $skipped,
		);
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

				echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Emulsify skipped duplicate ACF block definitions.', 'emulsify' ) . '</strong></p><ul>';

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
