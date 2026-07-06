<?php
/**
 * Registers WordPress theme setup features.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Runtime;

/**
 * Theme setup hooks.
 */
final class Setup {

	/**
	 * Registers setup hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'after_setup_theme', array( $this, 'theme_supports' ) );
		add_action( 'after_setup_theme', array( $this, 'menus' ) );
	}

	/**
	 * Registers WordPress theme support.
	 *
	 * @return void
	 */
	public function theme_supports(): void {
		$options = array(
			'image_sizes'    => array(
				'emulsify-wide' => array(
					'width'  => 1600,
					'height' => 900,
					'crop'   => true,
				),
				'emulsify-card' => array(
					'width'  => 768,
					'height' => 432,
					'crop'   => true,
				),
			),
			'textdomain'     => array(
				'domain' => 'emulsify',
				'path'   => get_template_directory() . '/languages',
			),
			'theme_supports' => array(
				'automatic-feed-links' => true,
				'title-tag'            => true,
				'post-thumbnails'      => true,
				'custom-logo'          => array(
					'flex-height' => true,
					'flex-width'  => true,
				),
				'html5'                => array(
					'caption',
					'comment-form',
					'comment-list',
					'gallery',
					'navigation-widgets',
					'script',
					'search-form',
					'style',
				),
				'align-wide'           => true,
				'responsive-embeds'    => true,
				'wp-block-styles'      => true,
				'editor-styles'        => true,
				'appearance-tools'     => true,
			),
		);

		/**
		 * Filters parent theme setup options before they are registered.
		 *
		 * Options include textdomain, image_sizes, and theme_supports keys.
		 * Set a theme support value to false to skip registering that support.
		 *
		 * @param array $options Parent theme setup options.
		 */
		$filtered = apply_filters( 'emulsify_theme_setup_options', $options );

		if ( is_array( $filtered ) ) {
			$options = $filtered;
		}

		if ( ! empty( $options['textdomain']['domain'] ) && ! empty( $options['textdomain']['path'] ) ) {
			// The parent text domain stays "emulsify"; generated child themes can
			// load their own text domain from child functions.php when needed.
			load_theme_textdomain( (string) $options['textdomain']['domain'], (string) $options['textdomain']['path'] );
		}

		foreach ( $options['theme_supports'] ?? array() as $feature => $support_options ) {
			if ( false === $support_options ) {
				// A false value gives child themes a simple way to opt out of one
				// parent support without replacing the full setup service.
				continue;
			}

			if ( true === $support_options ) {
				add_theme_support( (string) $feature );
				continue;
			}

			add_theme_support( (string) $feature, $support_options );
		}

		foreach ( $options['image_sizes'] ?? array() as $name => $image_size ) {
			if ( ! is_array( $image_size ) ) {
				continue;
			}

			add_image_size(
				(string) $name,
				isset( $image_size['width'] ) ? (int) $image_size['width'] : 0,
				isset( $image_size['height'] ) ? (int) $image_size['height'] : 0,
				$image_size['crop'] ?? false
			);
		}
	}

	/**
	 * Registers navigation menu locations.
	 *
	 * @return void
	 */
	public function menus(): void {
		register_nav_menus(
			array(
				'primary' => __( 'Primary Navigation', 'emulsify' ),
				'footer'  => __( 'Footer Navigation', 'emulsify' ),
			)
		);
	}
}
