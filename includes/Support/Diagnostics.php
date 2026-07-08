<?php
/**
 * Shared runtime diagnostic helpers.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Support;

/**
 * Formats and reports duplicate discovery diagnostics.
 */
final class Diagnostics {

	/**
	 * Builds a skipped duplicate record.
	 *
	 * @param string $type    Duplicate type.
	 * @param string $name    Duplicate name.
	 * @param array  $kept    Higher-priority record.
	 * @param array  $skipped Lower-priority skipped record.
	 * @param string $reason  Human-readable reason.
	 * @return array Duplicate record.
	 */
	public static function duplicate_record( string $type, string $name, array $kept, array $skipped, string $reason ): array {
		return array(
			'type'    => $type,
			'name'    => $name,
			'reason'  => $reason,
			'kept'    => $kept,
			'skipped' => $skipped,
		);
	}

	/**
	 * Logs duplicate records and optionally exposes admin notices.
	 *
	 * @param array  $duplicates Duplicate records.
	 * @param string $heading    Admin notice heading.
	 * @return void
	 */
	public static function report_duplicates( array $duplicates, string $heading ): void {
		if ( empty( $duplicates ) || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$messages = array();

		foreach ( $duplicates as $duplicate ) {
			if ( ! is_array( $duplicate ) ) {
				continue;
			}

			$messages[] = self::duplicate_message( $duplicate );
			error_log( '[Emulsify] ' . end( $messages ) );
		}

		self::admin_notice( $messages, $heading );
	}

	/**
	 * Adds an admin-only notice for skipped duplicates.
	 *
	 * @param array  $messages Notice messages.
	 * @param string $heading  Notice heading.
	 * @return void
	 */
	private static function admin_notice( array $messages, string $heading ): void {
		if ( empty( $messages ) || ! function_exists( 'add_action' ) || ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return;
		}

		add_action(
			'admin_notices',
			static function () use ( $messages, $heading ): void {
				if ( function_exists( 'current_user_can' ) && ! current_user_can( 'edit_theme_options' ) ) {
					return;
				}

				echo '<div class="notice notice-warning"><p><strong>' . self::esc_html( $heading ) . '</strong></p><ul>';

				foreach ( $messages as $message ) {
					echo '<li>' . self::esc_html( $message ) . '</li>';
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
	private static function duplicate_message( array $duplicate ): string {
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
	 * Escapes HTML text.
	 *
	 * @param string $value Raw text.
	 * @return string Escaped text.
	 */
	private static function esc_html( string $value ): string {
		return function_exists( 'esc_html' ) ? esc_html( $value ) : htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}
