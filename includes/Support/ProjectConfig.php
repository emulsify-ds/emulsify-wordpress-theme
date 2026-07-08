<?php
/**
 * Reads Emulsify project configuration from the active child theme.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Support;

/**
 * Provides project.emulsify.json helpers.
 */
final class ProjectConfig {

	/**
	 * Reads active child theme Emulsify project metadata.
	 *
	 * @return array Project config, or an empty array when unavailable.
	 */
	public static function read(): array {
		$path = get_stylesheet_directory() . '/project.emulsify.json';

		if ( ! is_readable( $path ) ) {
			return array();
		}

		$contents = file_get_contents( $path );

		if ( false === $contents ) {
			return array();
		}

		$data = json_decode( $contents, true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Reads the active child theme project machine name.
	 *
	 * @return string Project machine name, or empty string when unavailable.
	 */
	public static function machine_name(): string {
		$data = self::read();

		if ( ! is_array( $data ) || empty( $data['project']['machineName'] ) ) {
			return '';
		}

		$machine_name = trim( (string) $data['project']['machineName'] );

		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $machine_name ) ) {
			return '';
		}

		return $machine_name;
	}

	/**
	 * Gets configured project structure implementation records.
	 *
	 * @return array Structure implementation records.
	 */
	public static function structure_implementations(): array {
		$config = self::read();

		return ! empty( $config['variant']['structureImplementations'] ) && is_array( $config['variant']['structureImplementations'] )
			? $config['variant']['structureImplementations']
			: array();
	}

	/**
	 * Resolves a project config path to a safe theme-relative path.
	 *
	 * @param string $theme_dir Theme root.
	 * @param string $path      Project-configured path.
	 * @return string Resolved path, or empty string when invalid.
	 */
	public static function resolve_theme_relative_path( string $theme_dir, string $path ): string {
		$path = trim( str_replace( '\\', '/', $path ) );

		if (
			'' === $path
			|| false !== strpos( $path, "\0" )
			|| 0 === strpos( $path, '/' )
			|| preg_match( '#(^|/)\.\.(/|$)#', $path )
		) {
			// project.emulsify.json paths are child-theme relative. Reject
			// absolute paths and traversal so configuration cannot expose
			// arbitrary server files as Twig namespaces.
			return '';
		}

		return rtrim( $theme_dir, '/\\' ) . '/' . ltrim( $path, '/' );
	}
}
