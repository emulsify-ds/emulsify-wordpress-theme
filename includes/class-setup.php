<?php
/**
 * Registers WordPress theme setup features.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme;

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
		load_theme_textdomain( 'emulsify', get_template_directory() . '/languages' );

		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'custom-logo', array( 'flex-height' => true, 'flex-width' => true ) );

		add_image_size( 'emulsify-wide', 1600, 900, true );
		add_image_size( 'emulsify-card', 768, 432, true );

		add_theme_support(
			'html5',
			array(
				'caption',
				'comment-form',
				'comment-list',
				'gallery',
				'navigation-widgets',
				'script',
				'search-form',
				'style',
			)
		);

		add_theme_support( 'align-wide' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'appearance-tools' );
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
