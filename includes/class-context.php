<?php
/**
 * Adds global Timber context values.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme;

/**
 * Timber context integration.
 */
final class Context {

	/**
	 * Registers context filters.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'timber/context', array( $this, 'add' ) );
	}

	/**
	 * Adds project-wide context values.
	 *
	 * @param array $context Timber context.
	 * @return array Updated Timber context.
	 */
	public function add( array $context ): array {
		if ( empty( $context['site'] ) && class_exists( '\Timber\Site' ) ) {
			// Timber usually supplies "site"; keep this fallback for smoke tests
			// and custom contexts that invoke the filter before Timber populates it.
			$context['site'] = new \Timber\Site();
		}

		$context['theme']      = $this->theme();
		$context['menu']       = $this->menu();
		$context['post']       = $context['post'] ?? $this->post();
		$context['wp']         = $this->wordpress_helpers();
		$context['body_class'] = $context['body_class'] ?? implode( ' ', get_body_class() );

		/**
		 * Filters global Timber context values added by the parent theme.
		 *
		 * Child themes and project plugins can add project-wide values here
		 * without replacing the parent Context service.
		 *
		 * @param array $context Timber context values.
		 */
		$filtered = apply_filters( 'emulsify_theme_context', $context );

		return is_array( $filtered ) ? $filtered : $context;
	}

	/**
	 * Gets normalized theme metadata for Twig.
	 *
	 * @return array Theme metadata.
	 */
	private function theme(): array {
		$theme  = wp_get_theme();
		$parent = $theme->parent();

		return array(
			'name'                   => $theme->get( 'Name' ),
			'version'                => $theme->get( 'Version' ),
			'text_domain'            => $theme->get( 'TextDomain' ),
			'link'                   => get_stylesheet_directory_uri(),
			'path'                   => get_stylesheet_directory(),
			'template_directory_uri' => get_template_directory_uri(),
			'template_directory'     => get_template_directory(),
			'stylesheet_directory'   => get_stylesheet_directory(),
			'parent'                 => $parent ? $parent->get( 'Name' ) : null,
		);
	}

	/**
	 * Gets the primary Timber menu when one exists.
	 *
	 * @return mixed Timber menu or null.
	 */
	private function menu() {
		if ( ! class_exists( '\Timber\Timber' ) || ! method_exists( '\Timber\Timber', 'get_menu' ) ) {
			return null;
		}

		try {
			if ( has_nav_menu( 'primary' ) ) {
				// Prefer the registered primary location. Falling back to Timber's
				// default menu keeps minimal installs from failing with no menu set.
				return \Timber\Timber::get_menu( 'primary' );
			}

			return \Timber\Timber::get_menu();
		} catch ( \Throwable $throwable ) {
			return null;
		}
	}

	/**
	 * Gets the current Timber post for singular requests.
	 *
	 * @return mixed Timber post or null.
	 */
	private function post() {
		if ( ! is_singular() || ! class_exists( '\Timber\Timber' ) || ! method_exists( '\Timber\Timber', 'get_post' ) ) {
			return null;
		}

		try {
			return \Timber\Timber::get_post();
		} catch ( \Throwable $throwable ) {
			return null;
		}
	}

	/**
	 * Provides common WordPress values to Twig templates.
	 *
	 * @return array WordPress helper values.
	 */
	private function wordpress_helpers(): array {
		return array(
			'ajax_url'                 => admin_url( 'admin-ajax.php' ),
			'body_class'               => implode( ' ', get_body_class() ),
			'home_url'                 => home_url( '/' ),
			'is_404'                   => is_404(),
			'is_archive'               => is_archive(),
			'is_front_page'            => is_front_page(),
			'is_home'                  => is_home(),
			'is_page'                  => is_page(),
			'is_search'                => is_search(),
			'is_single'                => is_single(),
			'is_singular'              => is_singular(),
			'is_user_logged_in'        => is_user_logged_in(),
			'login_url'                => wp_login_url(),
			'logout_url'               => wp_logout_url(),
			'rest_url'                 => function_exists( 'rest_url' ) ? rest_url() : '',
			'search_query'             => get_search_query(),
			'site_url'                 => site_url( '/' ),
			'stylesheet_directory_uri' => get_stylesheet_directory_uri(),
			'template_directory_uri'   => get_template_directory_uri(),
			'theme_json'               => file_exists( get_theme_file_path( 'theme.json' ) ),
			'user'                     => is_user_logged_in() ? wp_get_current_user() : null,
		);
	}
}
