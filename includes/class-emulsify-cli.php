<?php
/**
 * Class-emulsify-cli.php.
 *
 * WP-CLI commands for generating an Emulsify sub-theme.
 *
 * @package Emulsify
 */

// Abort if WP-CLI is not present.
if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Provides the `emulsify` WP-CLI command for sub-theme generation.
 */
class Emulsify_CLI {

	/**
	 * Called when you run: wp emulsify <args>
	 *
	 * @param array $args       Positional arguments passed to the command.
	 * @param array $assoc_args Named (associative) arguments passed to the command.
	 */
	public function __invoke( $args, $assoc_args ) {
		return $this->generate( $args, $assoc_args );
	}

	/**
	 * Generates a new Emulsify sub-theme.
	 *
	 * @param array $args       Positional arguments passed to the command.
	 * @param array $assoc_args Named (associative) arguments passed to the command.
	 */
	public function generate( $args, $assoc_args ) {
		// 1) Validate & sanitize the human label.
		$label = ( isset( $args[0] ) && is_string( $args[0] ) && trim( $args[0] ) !== '' )
		? sanitize_text_field( $args[0] )
		: 'New Theme';

		// 2) Build a machine-friendly slug.
		$machine_name = self::convert_label_to_machine_name( $label );

		WP_CLI::log( sprintf( 'Cooking up a sub-theme: "%s" from Emulsify', $label, $machine_name ) );

		// 3) Point at *only* the `whisk` folder inside the Emulsify theme.
		$parent = ( isset( $assoc_args['parent'] ) && is_string( $assoc_args['parent'] ) )
		? sanitize_key( $assoc_args['parent'] )
		: 'emulsify';
		// Adjust this path if your whisk theme lives somewhere else inside emulsify.
		$sub_dir = 'whisk';
		$src     = get_theme_root() . "/{$parent}/{$sub_dir}";

		if ( ! is_dir( $src ) ) {
			WP_CLI::error( sprintf( 'Source directory not found: %s', $src ) );
		}

		// 4) Copy it into a new folder named after your slug.
		$dst = get_theme_root() . "/{$machine_name}";
		if ( ! $this->copy_theme( $src, $dst ) ) {
			WP_CLI::error( sprintf( 'Failed generating sub-theme "%s"', $src, $dst ) );
		}

		// 5) Rename all occurrences of “whisk” inside file/folder names and file contents.
		$old_slug = basename( $sub_dir ); // “whisk”
		$this->rename_instances( $dst, $old_slug, $machine_name );

		WP_CLI::success( sprintf( "Ding! Sub-theme '%s' created at '%s'.", $label, $dst ) );
	}

	/**
	 * Copies the parent theme into the sub-theme directory.
	 *
	 * @param string $src Source directory path.
	 * @param string $dst Destination directory path.
	 * @return bool True on success, false on failure.
	 */
	private function copy_theme( string $src, string $dst ): bool {
		// Ensure destination exists.
		if ( ! wp_mkdir_p( $dst ) ) {
			return false;
		}

		// Load WP Filesystem API if not already.
		if ( ! function_exists( 'copy_dir' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		// Initialize the filesystem, so $wp_filesystem is not null.
		WP_Filesystem();

		global $wp_filesystem;
		// Now safely call copy_dir().
		$copied = copy_dir( $src, $dst );
		if ( is_wp_error( $copied ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Renames files, directories, and file contents from old slug to new slug.
	 *
	 * @param string $dir      Directory to process recursively.
	 * @param string $old_slug Original theme slug.
	 * @param string $new_slug New theme slug.
	 */
	private function rename_instances( string $dir, string $old_slug, string $new_slug ): void {
		// Bail if there’s nothing here.
		if ( ! is_dir( $dir ) ) {
			return;
		}

		// Initialize fs if you haven’t already...
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Safely build iterator.
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
		} catch ( \UnexpectedValueException $e ) {
			// Directory unreadable or missing – just skip.
			return;
		}

		foreach ( $iterator as $item ) {
			$path = $item->getPathname();

			// Rename directories and files that contain the slug.
			if ( false !== strpos( $path, $old_slug ) ) {
					$new_path = str_replace( $old_slug, $new_slug, $path );
				if ( ! $wp_filesystem->move( $path, $new_path ) ) {
						WP_CLI::warning( sprintf( 'Could not move %s to %s.', $path, $new_path ) );
						continue;
				}
					$path = $new_path;
			}

			// If this is a file, replace slug inside its contents.
			if ( $item->isFile() ) {
					$contents = $wp_filesystem->get_contents( $path );
					$updated  = str_replace( $old_slug, $new_slug, $contents );
				if ( $updated !== $contents ) {
						$wp_filesystem->put_contents( $path, $updated, FS_CHMOD_FILE );
				}
			}
		}
	}

	/**
	 * Converts a human-readable theme name into a machine-friendly slug.
	 *
	 * @param string $label The human-readable theme name.
	 * @return string The machine-friendly version.
	 */
	private static function convert_label_to_machine_name( string $label ): string {
		// Lowercase and replace non-alphanumerics with underscores.
		$slug = preg_replace( '/[^a-z0-9]+/ui', '_', strtolower( $label ) );
		// Collapse consecutive underscores.
		$slug = preg_replace( '/_{2,}/', '_', $slug );
		// Trim leading or trailing underscores.
		$slug = preg_replace( '/^_+|_+$/', '', $slug );
		return $slug;
	}
}

// Register the `emulsify` WP-CLI command.
WP_CLI::add_command( 'emulsify', 'Emulsify_CLI' );
