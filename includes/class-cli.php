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
	 * Bundled starter child theme directory.
	 */
	private const STARTER_SLUG = 'whisk';

	/**
	 * Project metadata source identifier for generated child themes.
	 */
	private const GENERATED_FROM = 'emulsify-wordpress';

	/**
	 * Fallback generated child theme source version.
	 */
	private const GENERATED_FROM_VERSION = '2.0.0';

	/**
	 * Dependency/cache/build paths that should never be copied into a generated
	 * child theme.
	 */
	private const EXCLUDED_COPY_PATHS = array(
		'.git',
		'.coverage',
		'.out',
		'dist',
		'node_modules',
	);

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
	 * Generates an Emulsify child theme from the bundled Whisk starter.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Human-readable child theme name.
	 *
	 * [--machine-name=<slug>]
	 * : Lowercase slug for the generated theme directory, text domain, package
	 * name, and Emulsify machineName. Defaults to a slug generated from <name>.
	 *
	 * [--dry-run]
	 * : Show what would be created or changed without writing files.
	 *
	 * [--force]
	 * : Replace an existing destination directory only when it looks like an
	 * Emulsify-generated child theme.
	 *
	 * [--activate]
	 * : Activate the generated child theme after creation.
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
		$label        = $this->get_theme_label( $args );
		$machine_name = $this->get_machine_name( $label, $assoc_args );
		$parent       = $this->get_parent_slug( $assoc_args );
		$dry_run      = $this->get_flag_value( $assoc_args, 'dry-run' );
		$force        = $this->get_flag_value( $assoc_args, 'force' );
		$activate     = $this->get_flag_value( $assoc_args, 'activate' );
		$source       = $this->join_path( get_theme_root(), $parent, self::STARTER_SLUG );
		$destination  = $this->join_path( get_theme_root(), $machine_name );
		$version      = $this->get_generated_from_version( $source );

		\WP_CLI::log( sprintf( 'Generating child theme "%s" (%s) from Emulsify.', $label, $machine_name ) );
		\WP_CLI::log( sprintf( 'Source: %s', $source ) );
		\WP_CLI::log( sprintf( 'Destination: %s', $destination ) );

		if ( ! is_dir( $source ) ) {
			\WP_CLI::error( sprintf( 'Source directory not found: %s', $source ) );
		}

		if ( $machine_name === $parent ) {
			\WP_CLI::error( sprintf( 'The machine name "%s" would overwrite the parent theme. Choose a different --machine-name.', $machine_name ) );
		}

		if ( file_exists( $destination ) && ! $force ) {
			\WP_CLI::error( sprintf( 'Destination already exists: %s. Use --force to replace it.', $destination ) );
		}

		$metadata_updates = $this->collect_metadata_updates(
			$source,
			array(
				'label'        => $label,
				'machine_name' => $machine_name,
				'parent'       => $parent,
				'version'      => $version,
			)
		);

		if ( $dry_run ) {
			$this->report_dry_run( $source, $destination, $metadata_updates, $force, $activate, $machine_name );
			return;
		}

		if ( file_exists( $destination ) ) {
			$replacement_error = $this->get_destination_replacement_error( $destination, $parent );

			if ( null !== $replacement_error ) {
				\WP_CLI::error(
					sprintf(
						'Refusing to replace existing destination because it does not look like an Emulsify-generated child theme: %s (%s). Remove it manually or choose a different --machine-name.',
						$destination,
						$replacement_error
					)
				);
			}

			\WP_CLI::warning( sprintf( 'Replacing existing destination because --force was provided: %s', $destination ) );

			if ( ! $this->remove_path( $destination ) ) {
				\WP_CLI::error( sprintf( 'Failed removing existing destination: %s', $destination ) );
			}
		}

		if ( ! $this->copy_theme( $source, $destination ) ) {
			\WP_CLI::error( sprintf( 'Failed generating child theme at: %s', $destination ) );
		}

		$metadata_updates = $this->collect_metadata_updates(
			$destination,
			array(
				'label'        => $label,
				'machine_name' => $machine_name,
				'parent'       => $parent,
				'version'      => $version,
			)
		);
		$this->apply_metadata_updates( $destination, $metadata_updates );

		if ( $activate ) {
			$this->activate_theme( $machine_name );
		} else {
			\WP_CLI::log( sprintf( 'Activate it with: wp theme activate %s', $machine_name ) );
		}

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
		if ( ! $this->make_directory( $destination ) ) {
			return false;
		}

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $source, \RecursiveDirectoryIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST
			);
		} catch ( \UnexpectedValueException $exception ) {
			\WP_CLI::warning( sprintf( 'Could not read source directory: %s', $exception->getMessage() ) );
			return false;
		}

		foreach ( $iterator as $item ) {
			$path     = $item->getPathname();
			$relative = $this->relative_path( $source, $path );

			if ( $this->should_skip_copy_path( $relative ) ) {
				continue;
			}

			$target = $this->join_path( $destination, $relative );

			if ( $item->isDir() ) {
				if ( ! $this->make_directory( $target ) ) {
					\WP_CLI::warning( sprintf( 'Could not create directory: %s', $target ) );
					return false;
				}

				continue;
			}

			if ( $item->isLink() ) {
				\WP_CLI::warning( sprintf( 'Skipping symlink in starter theme: %s', $path ) );
				continue;
			}

			if ( ! $this->make_directory( dirname( $target ) ) || ! copy( $path, $target ) ) {
				\WP_CLI::warning( sprintf( 'Could not copy %s to %s.', $path, $target ) );
				return false;
			}
		}

		return true;
	}

	/**
	 * Collects targeted starter metadata updates.
	 *
	 * @param string $root   Theme root to read.
	 * @param array  $config Generation config.
	 * @return array<int, array{file:string,contents:string}>
	 */
	private function collect_metadata_updates( string $root, array $config ): array {
		$updates      = array();
		$theme_label  = $config['label'];
		$machine_name = $config['machine_name'];
		$parent       = $config['parent'];
		$version      = $config['version'];

		// Update known metadata surfaces deliberately. Avoid blind recursive text
		// replacement so example prose, generated assets, and project content are
		// not mutated unexpectedly.
		$this->collect_text_update(
			$updates,
			$root,
			'style.css',
			function ( string $contents ) use ( $theme_label, $machine_name, $parent ): string {
				$contents = $this->replace_theme_header( $contents, 'Theme Name', $theme_label );
				$contents = $this->replace_theme_header( $contents, 'Text Domain', $machine_name );
				return $this->replace_theme_header( $contents, 'Template', $parent );
			}
		);

		$this->collect_json_update(
			$updates,
			$root,
			'package.json',
			function ( array $data ) use ( $machine_name ): array {
				$data['name'] = $machine_name;
				return $data;
			}
		);

		$this->collect_json_update(
			$updates,
			$root,
			'project.emulsify.json',
			function ( array $data ) use ( $theme_label, $machine_name, $version ): array {
				if ( ! isset( $data['project'] ) || ! is_array( $data['project'] ) ) {
					$data['project'] = array();
				}

				$data['project']['platform']             = 'wordpress';
				$data['project']['name']                 = $theme_label;
				$data['project']['machineName']          = $machine_name;
				$data['project']['generatedFrom']        = self::GENERATED_FROM;
				$data['project']['generatedFromVersion'] = $version;

				return $data;
			}
		);

		$this->collect_text_update(
			$updates,
			$root,
			'functions.php',
			function ( string $contents ) use ( $theme_label ): string {
				return str_replace( 'Whisk child theme hooks.', $theme_label . ' child theme hooks.', $contents );
			}
		);

		$this->collect_text_update(
			$updates,
			$root,
			'templates/page.twig',
			function ( string $contents ) use ( $machine_name ): string {
				return str_replace( self::STARTER_SLUG . '-page', $machine_name . '-page', $contents );
			}
		);

		$this->collect_pattern_updates( $updates, $root, $machine_name );

		return $updates;
	}

	/**
	 * Adds starter pattern metadata updates.
	 *
	 * @param array  $updates      Update accumulator.
	 * @param string $root         Theme root.
	 * @param string $machine_name Generated child theme machine name.
	 * @return void
	 */
	private function collect_pattern_updates( array &$updates, string $root, string $machine_name ): void {
		$pattern_dir = $this->join_path( $root, 'patterns' );

		if ( ! is_dir( $pattern_dir ) ) {
			// Patterns are optional in Whisk. Empty generated themes should not pay
			// a filesystem or warning cost for a feature they have not adopted.
			return;
		}

		$files = glob( $pattern_dir . '/*.json' );

		if ( ! is_array( $files ) ) {
			return;
		}

		sort( $files );

		foreach ( $files as $path ) {
			$relative = 'patterns/' . basename( $path );

			$this->collect_json_update(
				$updates,
				$root,
				$relative,
				function ( array $data ) use ( $machine_name ): array {
					if ( isset( $data['name'] ) && is_string( $data['name'] ) && 0 === strpos( $data['name'], self::STARTER_SLUG . '/' ) ) {
						$data['name'] = $machine_name . '/' . substr( $data['name'], strlen( self::STARTER_SLUG ) + 1 );
					}

					return $data;
				}
			);
		}
	}

	/**
	 * Adds a text file update when the callback changes the contents.
	 *
	 * @param array    $updates  Update accumulator.
	 * @param string   $root     Theme root.
	 * @param string   $relative Relative file path.
	 * @param callable $callback Content updater.
	 * @return void
	 */
	private function collect_text_update( array &$updates, string $root, string $relative, callable $callback ): void {
		$path = $this->join_path( $root, $relative );

		if ( ! is_readable( $path ) ) {
			\WP_CLI::warning( sprintf( 'Expected starter file is not readable: %s', $path ) );
			return;
		}

		$contents = file_get_contents( $path );

		if ( ! is_string( $contents ) ) {
			\WP_CLI::warning( sprintf( 'Could not read starter file: %s', $path ) );
			return;
		}

		$updated = $callback( $contents );

		if ( $updated !== $contents ) {
			$updates[] = array(
				'file'     => $relative,
				'contents' => $updated,
			);
		}
	}

	/**
	 * Adds a JSON file update when the callback changes the decoded data.
	 *
	 * @param array    $updates  Update accumulator.
	 * @param string   $root     Theme root.
	 * @param string   $relative Relative file path.
	 * @param callable $callback Data updater.
	 * @return void
	 */
	private function collect_json_update( array &$updates, string $root, string $relative, callable $callback ): void {
		$path = $this->join_path( $root, $relative );

		if ( ! is_readable( $path ) ) {
			\WP_CLI::warning( sprintf( 'Expected starter JSON file is not readable: %s', $path ) );
			return;
		}

		$contents = file_get_contents( $path );

		if ( ! is_string( $contents ) ) {
			\WP_CLI::warning( sprintf( 'Could not read starter JSON file: %s', $path ) );
			return;
		}

		$data = json_decode( $contents, true );

		if ( ! is_array( $data ) ) {
			\WP_CLI::warning( sprintf( 'Could not decode starter JSON file: %s', $path ) );
			return;
		}

		$updated_data = $callback( $data );
		$updated      = json_encode( $updated_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( ! is_string( $updated ) ) {
			\WP_CLI::warning( sprintf( 'Could not encode starter JSON file: %s', $path ) );
			return;
		}

		$updated .= "\n";

		if ( $updated !== $contents ) {
			$updates[] = array(
				'file'     => $relative,
				'contents' => $updated,
			);
		}
	}

	/**
	 * Applies collected metadata updates to a generated child theme.
	 *
	 * @param string $destination Destination theme root.
	 * @param array  $updates     File updates.
	 * @return void
	 */
	private function apply_metadata_updates( string $destination, array $updates ): void {
		foreach ( $updates as $update ) {
			$path = $this->join_path( $destination, $update['file'] );

			if ( false === file_put_contents( $path, $update['contents'] ) ) {
				\WP_CLI::warning( sprintf( 'Could not update generated file: %s', $path ) );
				continue;
			}

			\WP_CLI::log( sprintf( 'Updated %s.', $update['file'] ) );
		}
	}

	/**
	 * Reports a dry-run plan.
	 *
	 * @param string $source           Source theme root.
	 * @param string $destination      Destination theme root.
	 * @param array  $metadata_updates Planned metadata updates.
	 * @param bool   $force            Whether force replacement was requested.
	 * @param bool   $activate         Whether activation was requested.
	 * @param string $machine_name     Generated machine name.
	 * @return void
	 */
	private function report_dry_run( string $source, string $destination, array $metadata_updates, bool $force, bool $activate, string $machine_name ): void {
		\WP_CLI::log( 'Dry run: no files will be written.' );

		if ( file_exists( $destination ) && $force ) {
			\WP_CLI::warning( sprintf( 'Would replace existing destination because --force was provided: %s', $destination ) );
		}

		\WP_CLI::log( sprintf( 'Would copy %d starter files from %s.', $this->count_copyable_files( $source ), $source ) );

		foreach ( $metadata_updates as $update ) {
			\WP_CLI::log( sprintf( 'Would update %s.', $update['file'] ) );
		}

		if ( $activate ) {
			\WP_CLI::log( sprintf( 'Would activate child theme "%s".', $machine_name ) );
		}

		\WP_CLI::success( sprintf( 'Dry run complete. Child theme would be created at "%s".', $destination ) );
	}

	/**
	 * Activates a generated child theme.
	 *
	 * @param string $machine_name Theme stylesheet slug.
	 * @return void
	 */
	private function activate_theme( string $machine_name ): void {
		if ( ! function_exists( 'switch_theme' ) ) {
			\WP_CLI::warning( sprintf( 'Could not activate "%s" because switch_theme() is unavailable.', $machine_name ) );
			return;
		}

		switch_theme( $machine_name );

		\WP_CLI::success( sprintf( 'Activated child theme "%s".', $machine_name ) );
	}

	/**
	 * Replaces a WordPress theme header value.
	 *
	 * @param string $contents File contents.
	 * @param string $field    Header field.
	 * @param string $value    Header value.
	 * @return string Updated file contents.
	 */
	private function replace_theme_header( string $contents, string $field, string $value ): string {
		$pattern = '/^(\s*\*\s*' . preg_quote( $field, '/' ) . ':\s*).*$/mi';
		$updated = preg_replace_callback(
			$pattern,
			static function ( array $matches ) use ( $value ): string {
				return $matches[1] . $value;
			},
			$contents,
			1
		);

		if ( ! is_string( $updated ) || $updated === $contents ) {
			\WP_CLI::warning( sprintf( 'Could not update "%s" in style.css.', $field ) );
			return $contents;
		}

		return $updated;
	}

	/**
	 * Counts files that would be copied from the starter.
	 *
	 * @param string $source Source theme root.
	 * @return int Copyable file count.
	 */
	private function count_copyable_files( string $source ): int {
		$count = 0;

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $source, \RecursiveDirectoryIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST
			);
		} catch ( \UnexpectedValueException $exception ) {
			return 0;
		}

		foreach ( $iterator as $item ) {
			if ( ! $item->isFile() ) {
				continue;
			}

			$relative = $this->relative_path( $source, $item->getPathname() );

			if ( ! $this->should_skip_copy_path( $relative ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Gets the version to record in generated child theme metadata.
	 *
	 * @param string $source Starter source path.
	 * @return string Version string.
	 */
	private function get_generated_from_version( string $source ): string {
		$package = $this->read_json_file( $this->join_path( dirname( $source ), 'package.json' ) );

		if ( isset( $package['version'] ) && is_string( $package['version'] ) && '' !== trim( $package['version'] ) ) {
			return trim( $package['version'] );
		}

		return self::GENERATED_FROM_VERSION;
	}

	/**
	 * Gets the reason an existing destination should not be force-replaced.
	 *
	 * @param string $destination Destination theme root.
	 * @param string $parent      Selected parent theme slug.
	 * @return string|null Error reason, or NULL when replacement is allowed.
	 */
	private function get_destination_replacement_error( string $destination, string $parent ): ?string {
		if ( ! is_dir( $destination ) ) {
			return 'destination is not a theme directory';
		}

		$template = $this->read_theme_header_value( $this->join_path( $destination, 'style.css' ), 'Template' );

		if ( null === $template ) {
			return 'missing readable style.css Template header';
		}

		$allowed_templates = array_values( array_unique( array_filter( array( 'emulsify', $parent ) ) ) );

		if ( ! in_array( $template, $allowed_templates, true ) ) {
			return sprintf( 'style.css Template is "%s", expected "%s"', $template, implode( '" or "', $allowed_templates ) );
		}

		$project = $this->read_json_file( $this->join_path( $destination, 'project.emulsify.json' ) );

		if ( null === $project ) {
			return 'missing readable project.emulsify.json';
		}

		if ( ! isset( $project['project'] ) || ! is_array( $project['project'] ) ) {
			return 'project.emulsify.json is missing project metadata';
		}

		if ( 'wordpress' !== ( $project['project']['platform'] ?? null ) ) {
			return 'project.emulsify.json is missing project.platform: wordpress';
		}

		if ( ! isset( $project['project']['machineName'] ) || ! is_string( $project['project']['machineName'] ) || '' === trim( $project['project']['machineName'] ) ) {
			return 'project.emulsify.json is missing project.machineName';
		}

		$has_generated_from         = array_key_exists( 'generatedFrom', $project['project'] );
		$has_generated_from_version = array_key_exists( 'generatedFromVersion', $project['project'] );

		if ( $has_generated_from && ( ! is_string( $project['project']['generatedFrom'] ) || '' === trim( $project['project']['generatedFrom'] ) ) ) {
			return 'project.emulsify.json has an invalid project.generatedFrom';
		}

		if ( $has_generated_from && self::GENERATED_FROM !== $project['project']['generatedFrom'] ) {
			return sprintf( 'project.emulsify.json generatedFrom is "%s", expected "%s"', $project['project']['generatedFrom'], self::GENERATED_FROM );
		}

		if ( $has_generated_from && ( ! $has_generated_from_version || ! is_string( $project['project']['generatedFromVersion'] ) || '' === trim( $project['project']['generatedFromVersion'] ) ) ) {
			return 'project.emulsify.json is missing project.generatedFromVersion';
		}

		if ( ! $has_generated_from && $has_generated_from_version ) {
			return 'project.emulsify.json has project.generatedFromVersion without project.generatedFrom';
		}

		return null;
	}

	/**
	 * Reads a WordPress theme header value.
	 *
	 * @param string $path  File path.
	 * @param string $field Header field.
	 * @return string|null Header value, or NULL when unavailable.
	 */
	private function read_theme_header_value( string $path, string $field ): ?string {
		if ( ! is_readable( $path ) ) {
			return null;
		}

		$contents = file_get_contents( $path );

		if ( ! is_string( $contents ) ) {
			return null;
		}

		if ( ! preg_match( '/^\s*(?:\*\s*)?' . preg_quote( $field, '/' ) . ':\s*(.+?)\s*$/mi', $contents, $matches ) ) {
			return null;
		}

		return trim( $matches[1] );
	}

	/**
	 * Reads a JSON file.
	 *
	 * @param string $path File path.
	 * @return array|null Decoded JSON data, or NULL when unavailable.
	 */
	private function read_json_file( string $path ): ?array {
		if ( ! is_readable( $path ) ) {
			return null;
		}

		$contents = file_get_contents( $path );

		if ( ! is_string( $contents ) ) {
			return null;
		}

		$data = json_decode( $contents, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Removes a generated destination before force replacement.
	 *
	 * @param string $path Path to remove.
	 * @return bool TRUE on success.
	 */
	private function remove_path( string $path ): bool {
		if ( ! file_exists( $path ) ) {
			return true;
		}

		// --force removal is scoped to the computed child theme destination. The
		// generator validates that destination before this method is called.
		if ( is_file( $path ) || is_link( $path ) ) {
			return unlink( $path );
		}

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
		} catch ( \UnexpectedValueException $exception ) {
			\WP_CLI::warning( sprintf( 'Could not read destination for removal: %s', $exception->getMessage() ) );
			return false;
		}

		foreach ( $iterator as $item ) {
			$item_path = $item->getPathname();

			if ( $item->isDir() && ! $item->isLink() ) {
				if ( ! rmdir( $item_path ) ) {
					return false;
				}
			} elseif ( ! unlink( $item_path ) ) {
				return false;
			}
		}

		return rmdir( $path );
	}

	/**
	 * Creates a directory through WordPress when available.
	 *
	 * @param string $path Directory path.
	 * @return bool TRUE on success.
	 */
	private function make_directory( string $path ): bool {
		if ( is_dir( $path ) ) {
			return true;
		}

		if ( function_exists( 'wp_mkdir_p' ) ) {
			return wp_mkdir_p( $path );
		}

		return mkdir( $path, 0777, true );
	}

	/**
	 * Checks whether a starter path should be skipped during copy.
	 *
	 * @param string $relative Relative starter path.
	 * @return bool TRUE when the path should be skipped.
	 */
	private function should_skip_copy_path( string $relative ): bool {
		$parts = preg_split( '#[\\\\/]#', $relative );

		if ( ! is_array( $parts ) ) {
			return false;
		}

		foreach ( $parts as $part ) {
			if ( in_array( $part, self::EXCLUDED_COPY_PATHS, true ) ) {
				// Match any path segment so nested dependencies and generated build
				// output cannot leak into a new project child theme.
				return true;
			}
		}

		return false;
	}

	/**
	 * Converts a human theme name into a filesystem-safe slug.
	 *
	 * @param string $label Theme label.
	 * @return string Theme slug.
	 */
	private static function convert_label_to_machine_name( string $label ): string {
		$slug = preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $label ) );
		$slug = preg_replace( '/-{2,}/', '-', (string) $slug );
		$slug = trim( (string) $slug, '-' );

		return $slug;
	}

	/**
	 * Gets the sanitized human-readable child theme label.
	 *
	 * @param array $args Positional CLI arguments.
	 * @return string Theme label.
	 */
	private function get_theme_label( array $args ): string {
		$label = isset( $args[0] ) && is_string( $args[0] ) && '' !== trim( $args[0] ) ? $args[0] : 'New Theme';
		$label = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $label ) : trim( strip_tags( $label ) );

		return '' !== $label ? $label : 'New Theme';
	}

	/**
	 * Gets the generated or explicitly supplied machine name.
	 *
	 * @param string $label      Theme label.
	 * @param array  $assoc_args Named CLI arguments.
	 * @return string Theme slug.
	 */
	private function get_machine_name( string $label, array $assoc_args ): string {
		$raw_machine_name = isset( $assoc_args['machine-name'] ) && is_string( $assoc_args['machine-name'] )
			? $assoc_args['machine-name']
			: $label;
		$machine_name     = self::convert_label_to_machine_name( $raw_machine_name );

		if ( '' === $machine_name && isset( $assoc_args['machine-name'] ) ) {
			\WP_CLI::error( 'Machine name cannot be empty.' );
		}

		if ( '' === $machine_name ) {
			$machine_name = 'new-theme';
		}

		if ( isset( $assoc_args['machine-name'] ) && $raw_machine_name !== $machine_name ) {
			\WP_CLI::warning( sprintf( 'Normalized --machine-name from "%s" to "%s".', $raw_machine_name, $machine_name ) );
		}

		return $machine_name;
	}

	/**
	 * Gets the parent theme slug.
	 *
	 * @param array $assoc_args Named CLI arguments.
	 * @return string Parent slug.
	 */
	private function get_parent_slug( array $assoc_args ): string {
		$parent = isset( $assoc_args['parent'] ) && is_string( $assoc_args['parent'] ) ? $assoc_args['parent'] : 'emulsify';

		if ( function_exists( 'sanitize_key' ) ) {
			$parent = sanitize_key( $parent );
		} else {
			$parent = preg_replace( '/[^a-z0-9_-]/', '', strtolower( $parent ) );
		}

		return '' !== $parent ? $parent : 'emulsify';
	}

	/**
	 * Reads a boolean WP-CLI flag.
	 *
	 * @param array  $assoc_args Named CLI arguments.
	 * @param string $name       Flag name.
	 * @return bool TRUE when the flag is enabled.
	 */
	private function get_flag_value( array $assoc_args, string $name ): bool {
		if ( ! array_key_exists( $name, $assoc_args ) ) {
			return false;
		}

		return false !== $assoc_args[ $name ] && 'false' !== $assoc_args[ $name ] && '0' !== $assoc_args[ $name ];
	}

	/**
	 * Computes a path relative to a root.
	 *
	 * @param string $root Root path.
	 * @param string $path Full path.
	 * @return string Relative path.
	 */
	private function relative_path( string $root, string $path ): string {
		return ltrim( substr( $path, strlen( rtrim( $root, '/\\' ) ) ), '/\\' );
	}

	/**
	 * Joins path segments.
	 *
	 * @param string ...$segments Path segments.
	 * @return string Joined path.
	 */
	private function join_path( string ...$segments ): string {
		$path = '';

		foreach ( $segments as $segment ) {
			if ( '' === $segment ) {
				continue;
			}

			$path = '' === $path ? rtrim( $segment, '/\\' ) : $path . '/' . trim( $segment, '/\\' );
		}

		return $path;
	}
}
