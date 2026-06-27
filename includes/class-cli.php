<?php
/**
 * Registers WP-CLI commands.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme;

/**
 * Provides the `wp emulsify` command.
 */
final class Cli {

	/**
	 * Registers the command when WP-CLI is available.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'emulsify', $this );
	}

	/**
	 * Called when running `wp emulsify <name>`.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$this->generate( $args, $assoc_args );
	}

	/**
	 * Generates a new child theme from whisk.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	private function generate( array $args, array $assoc_args ): void {
		$label        = isset( $args[0] ) && is_string( $args[0] ) && '' !== trim( $args[0] ) ? sanitize_text_field( $args[0] ) : 'New Theme';
		$machine_name = self::convert_label_to_machine_name( $label );
		$parent       = isset( $assoc_args['parent'] ) && is_string( $assoc_args['parent'] ) ? sanitize_key( $assoc_args['parent'] ) : 'emulsify';
		$source       = get_theme_root() . "/{$parent}/whisk";
		$destination  = get_theme_root() . "/{$machine_name}";

		\WP_CLI::log( sprintf( 'Generating child theme "%s" from Emulsify.', $label ) );

		if ( ! is_dir( $source ) ) {
			\WP_CLI::error( sprintf( 'Source directory not found: %s', $source ) );
		}

		if ( ! $this->copy_theme( $source, $destination ) ) {
			\WP_CLI::error( sprintf( 'Failed generating child theme at: %s', $destination ) );
		}

		$this->rename_instances( $destination, 'whisk', $machine_name );

		\WP_CLI::success( sprintf( 'Child theme "%s" created at "%s".', $label, $destination ) );
	}

	/**
	 * Copies the starter theme into the child theme destination.
	 *
	 * @param string $source      Source directory.
	 * @param string $destination Destination directory.
	 * @return bool TRUE on success.
	 */
	private function copy_theme( string $source, string $destination ): bool {
		if ( ! wp_mkdir_p( $destination ) ) {
			return false;
		}

		if ( ! function_exists( 'copy_dir' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();

		$copied = copy_dir( $source, $destination );

		return ! is_wp_error( $copied );
	}

	/**
	 * Renames starter file names and contents to the new theme slug.
	 *
	 * @param string $dir      Directory to process recursively.
	 * @param string $old_slug Original slug.
	 * @param string $new_slug New slug.
	 * @return void
	 */
	private function rename_instances( string $dir, string $old_slug, string $new_slug ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST
			);
		} catch ( \UnexpectedValueException $exception ) {
			return;
		}

		foreach ( $iterator as $item ) {
			$path = $item->getPathname();

			if ( false !== strpos( $path, $old_slug ) ) {
				$new_path = str_replace( $old_slug, $new_slug, $path );

				if ( ! $wp_filesystem->move( $path, $new_path ) ) {
					\WP_CLI::warning( sprintf( 'Could not move %s to %s.', $path, $new_path ) );
					continue;
				}

				$path = $new_path;
			}

			if ( ! $item->isFile() ) {
				continue;
			}

			$contents = $wp_filesystem->get_contents( $path );

			if ( ! is_string( $contents ) ) {
				continue;
			}

			$updated = str_replace( $old_slug, $new_slug, $contents );

			if ( $updated !== $contents ) {
				$wp_filesystem->put_contents( $path, $updated, FS_CHMOD_FILE );
			}
		}
	}

	/**
	 * Converts a human theme name into a filesystem-safe slug.
	 *
	 * @param string $label Theme label.
	 * @return string Theme slug.
	 */
	private static function convert_label_to_machine_name( string $label ): string {
		$slug = preg_replace( '/[^a-z0-9]+/ui', '_', strtolower( $label ) );
		$slug = preg_replace( '/_{2,}/', '_', (string) $slug );
		$slug = preg_replace( '/^_+|_+$/', '', (string) $slug );

		return '' !== $slug ? $slug : 'new_theme';
	}
}
