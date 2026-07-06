<?php
/**
 * Configures optional ACF Local JSON paths.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Acf;

/**
 * ACF Local JSON integration.
 */
final class LocalJson {

	/**
	 * Registers ACF Local JSON hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->acf_available() ) {
			// ACF is optional. Register no hooks until an ACF API marker exists so
			// the parent theme can run cleanly on sites without the plugin.
			return;
		}

		add_filter( 'acf/settings/save_json', array( $this, 'save_path' ) );
		add_filter( 'acf/settings/load_json', array( $this, 'load_paths' ) );
	}

	/**
	 * Filters the ACF JSON save path.
	 *
	 * @param string $path Incoming ACF save path.
	 * @return string Filtered save path.
	 */
	public function save_path( string $path ): string {
		if ( ! $this->enabled() ) {
			return $path;
		}

		$save_path = $this->configured_save_path();

		return '' === $save_path ? $path : $save_path;
	}

	/**
	 * Filters ACF JSON load paths.
	 *
	 * @param array $paths Incoming ACF load paths.
	 * @return array Filtered load paths.
	 */
	public function load_paths( array $paths ): array {
		if ( ! $this->enabled() ) {
			return $paths;
		}

		$save_path = $this->configured_save_path();

		if ( '' === $save_path ) {
			// If the configured directory is missing, leave ACF's default Local JSON
			// behavior untouched instead of creating paths implicitly.
			return $paths;
		}

		$load_paths = $paths;

		/**
		 * Filters whether ACF's default load path should be removed.
		 *
		 * Defaults to false so the parent theme does not remove ACF's built-in
		 * `acf-json` path unless a project explicitly opts into that behavior.
		 *
		 * @param bool   $remove_default Whether to remove the first incoming load path.
		 * @param string $save_path      Configured Emulsify ACF JSON save path.
		 * @param array  $paths          Incoming ACF load paths.
		 */
		$remove_default = (bool) apply_filters( 'emulsify_theme_acf_json_remove_default_load_path', false, $save_path, $paths );

		if ( $remove_default && isset( $load_paths[0] ) ) {
			unset( $load_paths[0] );
		}

		$load_paths[] = $save_path;
		$load_paths   = $this->normalize_paths( $load_paths );

		/**
		 * Filters final ACF JSON load paths.
		 *
		 * @param array  $load_paths Filtered ACF JSON load paths.
		 * @param string $save_path  Configured Emulsify ACF JSON save path.
		 * @param array  $paths      Incoming ACF load paths.
		 */
		$filtered = apply_filters( 'emulsify_theme_acf_json_load_paths', $load_paths, $save_path, $paths );

		return is_array( $filtered ) ? $this->normalize_paths( $filtered ) : $load_paths;
	}

	/**
	 * Checks whether the Local JSON integration is enabled.
	 *
	 * @return bool TRUE when enabled.
	 */
	private function enabled(): bool {
		$default_path = $this->default_path();
		$enabled      = '' !== $default_path && is_dir( $default_path );

		/**
		 * Filters whether Emulsify should configure ACF Local JSON paths.
		 *
		 * Defaults to true only when the active child theme has a config/acf-json
		 * directory.
		 *
		 * @param bool   $enabled      Whether ACF Local JSON path support is enabled.
		 * @param string $default_path Default active child theme ACF JSON path.
		 */
		return (bool) apply_filters( 'emulsify_theme_acf_json_enabled', $enabled, $default_path );
	}

	/**
	 * Gets the configured ACF JSON save path.
	 *
	 * @return string Existing save path, or an empty string.
	 */
	private function configured_save_path(): string {
		$default_path = $this->default_path();
		$save_path    = $default_path;

		/**
		 * Filters the ACF JSON save path.
		 *
		 * @param string $save_path    ACF JSON save path.
		 * @param string $default_path Default active child theme ACF JSON path.
		 */
		$filtered = apply_filters( 'emulsify_theme_acf_json_save_path', $save_path, $default_path );

		if ( is_scalar( $filtered ) ) {
			$save_path = (string) $filtered;
		}

		$save_path = rtrim( $save_path, '/\\' );

		return '' !== $save_path && is_dir( $save_path ) ? $save_path : '';
	}

	/**
	 * Gets the active child theme ACF JSON path.
	 *
	 * @return string Default path, or an empty string when unavailable.
	 */
	private function default_path(): string {
		if ( ! function_exists( 'get_stylesheet_directory' ) ) {
			return '';
		}

		return rtrim( get_stylesheet_directory(), '/\\' ) . '/config/acf-json';
	}

	/**
	 * Normalizes and deduplicates filesystem paths.
	 *
	 * @param array $paths Path candidates.
	 * @return array Normalized paths.
	 */
	private function normalize_paths( array $paths ): array {
		$normalized = array();
		$seen       = array();

		foreach ( $paths as $path ) {
			if ( ! is_scalar( $path ) ) {
				continue;
			}

			$path = rtrim( (string) $path, '/\\' );

			if ( '' === $path ) {
				continue;
			}

			$key = realpath( $path ) ?: $path;

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$normalized[] = $path;
		}

		return $normalized;
	}

	/**
	 * Checks whether ACF is available.
	 *
	 * @return bool TRUE when an ACF API marker exists.
	 */
	private function acf_available(): bool {
		return function_exists( 'acf' )
			|| function_exists( 'acf_get_setting' )
			|| function_exists( 'acf_register_block_type' )
			|| class_exists( '\ACF' );
	}
}
