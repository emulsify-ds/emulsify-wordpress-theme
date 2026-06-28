<?php
/**
 * Smoke checks for the WP-CLI child theme generator.
 *
 * @package Emulsify
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

if ( ! class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		/**
		 * Captured messages.
		 *
		 * @var array<int, array{type:string,message:string}>
		 */
		public static $messages = array();

		public static function add_command( $name, $callable ): void {}

		public static function log( string $message ): void {
			self::$messages[] = array(
				'type'    => 'log',
				'message' => $message,
			);
		}

		public static function warning( string $message ): void {
			self::$messages[] = array(
				'type'    => 'warning',
				'message' => $message,
			);
		}

		public static function success( string $message ): void {
			self::$messages[] = array(
				'type'    => 'success',
				'message' => $message,
			);
		}

		public static function error( string $message ): void {
			throw new RuntimeException( $message );
		}
	}
}

if ( ! function_exists( 'get_theme_root' ) ) {
	function get_theme_root(): string {
		return $GLOBALS['emulsify_theme_root'];
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( string $target ): bool {
		return is_dir( $target ) || mkdir( $target, 0777, true );
	}
}

if ( ! function_exists( 'switch_theme' ) ) {
	function switch_theme( string $stylesheet ): void {
		$GLOBALS['emulsify_activated_theme'] = $stylesheet;
	}
}

/**
 * Fails the smoke script when an assertion is false.
 *
 * @param bool   $condition Assertion condition.
 * @param string $message   Failure message.
 * @return void
 */
function emulsify_cli_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Recursively copies test fixtures.
 *
 * @param string $source      Source path.
 * @param string $destination Destination path.
 * @return void
 */
function emulsify_cli_smoke_copy( string $source, string $destination ): void {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $item ) {
		$path     = $item->getPathname();
		$relative = ltrim( substr( $path, strlen( rtrim( $source, '/\\' ) ) ), '/\\' );

		if ( in_array( 'node_modules', preg_split( '#[\\\\/]#', $relative ), true ) ) {
			continue;
		}

		$target = $destination . '/' . $relative;

		if ( $item->isDir() ) {
			if ( ! is_dir( $target ) && ! mkdir( $target, 0777, true ) ) {
				throw new RuntimeException( sprintf( 'Could not create fixture directory: %s', $target ) );
			}

			continue;
		}

		if ( ! is_dir( dirname( $target ) ) && ! mkdir( dirname( $target ), 0777, true ) ) {
			throw new RuntimeException( sprintf( 'Could not create fixture directory: %s', dirname( $target ) ) );
		}

		if ( ! copy( $path, $target ) ) {
			throw new RuntimeException( sprintf( 'Could not copy fixture file: %s', $path ) );
		}
	}
}

/**
 * Recursively removes a path.
 *
 * @param string $path Path to remove.
 * @return void
 */
function emulsify_cli_smoke_remove( string $path ): void {
	if ( ! file_exists( $path ) ) {
		return;
	}

	if ( is_file( $path ) || is_link( $path ) ) {
		unlink( $path );
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $item ) {
		$item->isDir() && ! $item->isLink() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}

	rmdir( $path );
}

/**
 * Reads and decodes a JSON fixture file.
 *
 * @param string $path JSON file path.
 * @return array Decoded JSON.
 */
function emulsify_cli_smoke_json( string $path ): array {
	$data = json_decode( file_get_contents( $path ), true );

	emulsify_cli_smoke_assert( is_array( $data ), sprintf( 'Could not decode JSON file: %s', $path ) );

	return $data;
}

$repo_root = dirname( __DIR__, 2 );
$work_root = sys_get_temp_dir() . '/emulsify-child-theme-generator-' . uniqid( '', true );
$theme_root = $work_root . '/themes';
$parent_root = $theme_root . '/emulsify';

$GLOBALS['emulsify_theme_root']      = $theme_root;
$GLOBALS['emulsify_activated_theme'] = null;

try {
	if ( ! mkdir( $parent_root . '/whisk', 0777, true ) ) {
		throw new RuntimeException( sprintf( 'Could not create fixture root: %s', $parent_root ) );
	}

	emulsify_cli_smoke_copy( $repo_root . '/whisk', $parent_root . '/whisk' );
	file_put_contents(
		$parent_root . '/whisk/patterns/smoke-pattern.json',
		json_encode(
			array(
				'name'        => 'whisk/smoke-pattern',
				'title'       => 'Smoke Pattern',
				'description' => 'Temporary pattern fixture for child-theme generation smoke coverage.',
				'categories'  => array( 'text' ),
				'content'     => '<!-- wp:paragraph --><p>Smoke pattern content</p><!-- /wp:paragraph -->',
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		) . "\n"
	);

	require_once $repo_root . '/includes/class-cli.php';

	$cli = new Emulsify\Theme\Cli();

	$cli( array( 'Acme Theme' ), array( 'machine-name' => 'acme-child' ) );

	$destination = $theme_root . '/acme-child';
	$style       = file_get_contents( $destination . '/style.css' );
	$package     = emulsify_cli_smoke_json( $destination . '/package.json' );
	$project     = emulsify_cli_smoke_json( $destination . '/project.emulsify.json' );
	$page        = file_get_contents( $destination . '/templates/page.twig' );
	$functions   = file_get_contents( $destination . '/functions.php' );
	$smoke_pattern = emulsify_cli_smoke_json( $destination . '/patterns/smoke-pattern.json' );

	emulsify_cli_smoke_assert( is_dir( $destination ), 'Expected generated child theme directory to exist.' );
	emulsify_cli_smoke_assert( ! is_dir( $destination . '/node_modules' ), 'Generated child theme should not copy node_modules.' );
	emulsify_cli_smoke_assert( is_file( $destination . '/config/acf-json/.gitkeep' ), 'Generated child theme should copy the ACF Local JSON convention directory.' );
	emulsify_cli_smoke_assert( false !== strpos( $style, 'Theme Name: Acme Theme' ), 'style.css should update Theme Name.' );
	emulsify_cli_smoke_assert( false !== strpos( $style, 'Text Domain: acme-child' ), 'style.css should update Text Domain.' );
	emulsify_cli_smoke_assert( false !== strpos( $style, 'Template: emulsify' ), 'style.css should keep the parent Template slug.' );
	emulsify_cli_smoke_assert( 'acme-child' === $package['name'], 'package.json should update name.' );
	emulsify_cli_smoke_assert( 'wordpress' === $project['project']['platform'], 'project.emulsify.json should preserve the WordPress platform adapter.' );
	emulsify_cli_smoke_assert( 'Acme Theme' === $project['project']['name'], 'project.emulsify.json should update project name.' );
	emulsify_cli_smoke_assert( 'acme-child' === $project['project']['machineName'], 'project.emulsify.json should update machineName.' );
	emulsify_cli_smoke_assert( false !== strpos( $page, 'acme-child-page' ), 'Example template should update slug class.' );
	emulsify_cli_smoke_assert( false === strpos( $page, 'whisk-page' ), 'Example template should not keep the whisk slug class.' );
	emulsify_cli_smoke_assert( false !== strpos( $functions, 'Acme Theme child theme hooks.' ), 'functions.php should update visible Whisk label.' );
	emulsify_cli_smoke_assert( is_file( $destination . '/src/components/.gitkeep' ), 'Generated child theme should keep the empty component source placeholder.' );
	emulsify_cli_smoke_assert( is_file( $destination . '/patterns/.gitkeep' ), 'Generated child theme should keep the empty pattern placeholder.' );
	emulsify_cli_smoke_assert( ! is_dir( $destination . '/src/components/button' ), 'Generated child theme should not include the removed starter button component.' );
	emulsify_cli_smoke_assert( ! is_file( $destination . '/src/foundation.scss' ), 'Generated child theme should not include an assumed foundation Sass entry.' );
	emulsify_cli_smoke_assert( ! is_file( $destination . '/src/layout.scss' ), 'Generated child theme should not include an assumed layout Sass entry.' );
	emulsify_cli_smoke_assert( ! is_file( $destination . '/src/tokens.scss' ), 'Generated child theme should not include an assumed tokens Sass entry.' );
	emulsify_cli_smoke_assert( ! is_dir( $destination . '/dist' ), 'Generated child theme should not copy ignored build output directories.' );
	emulsify_cli_smoke_assert( ! is_dir( $destination . '/.out' ), 'Generated child theme should not copy ignored Storybook output directories.' );
	emulsify_cli_smoke_assert( 'acme-child/smoke-pattern' === $smoke_pattern['name'], 'Generated child theme should update copied pattern namespaces when patterns exist.' );
	emulsify_cli_smoke_assert( false !== strpos( $smoke_pattern['content'], 'Smoke pattern content' ), 'Generated child theme should copy optional pattern content when patterns exist.' );

	$failed_without_force = false;

	try {
		$cli( array( 'Acme Theme' ), array( 'machine-name' => 'acme-child' ) );
	} catch ( RuntimeException $exception ) {
		$failed_without_force = false !== strpos( $exception->getMessage(), 'Destination already exists' );
	}

	emulsify_cli_smoke_assert( $failed_without_force, 'Existing destination should fail without --force.' );

	file_put_contents( $destination . '/remove-me.txt', 'stale' );

	$cli( array( 'Acme Theme' ), array( 'machine-name' => 'acme-child', 'force' => true ) );

	emulsify_cli_smoke_assert( ! file_exists( $destination . '/remove-me.txt' ), '--force should replace the existing destination.' );

	WP_CLI::$messages                       = array();
	$GLOBALS['emulsify_activated_theme']    = null;
	$dry_run_destination                    = $theme_root . '/dry-run-child';

	$cli( array( 'Dry Run Theme' ), array( 'machine-name' => 'dry-run-child', 'dry-run' => true, 'activate' => true ) );

	emulsify_cli_smoke_assert( ! file_exists( $dry_run_destination ), '--dry-run should not create a child theme directory.' );
	emulsify_cli_smoke_assert( null === $GLOBALS['emulsify_activated_theme'], '--dry-run should not activate the child theme.' );
	emulsify_cli_smoke_assert(
		(bool) array_filter(
			WP_CLI::$messages,
			static function ( array $message ): bool {
				return false !== strpos( $message['message'], 'Dry run complete' );
			}
		),
		'--dry-run should report completion.'
	);

	$invalid_machine_name_failed = false;

	try {
		$cli( array( 'Invalid Theme' ), array( 'machine-name' => '!!!' ) );
	} catch ( RuntimeException $exception ) {
		$invalid_machine_name_failed = false !== strpos( $exception->getMessage(), 'Machine name cannot be empty' );
	}

	emulsify_cli_smoke_assert( $invalid_machine_name_failed, 'Invalid --machine-name values should fail.' );

	$cli( array( 'Active Theme' ), array( 'machine-name' => 'active-child', 'activate' => true ) );

	emulsify_cli_smoke_assert( 'active-child' === $GLOBALS['emulsify_activated_theme'], '--activate should call switch_theme() with the child slug.' );

	echo "Child theme generator smoke checks passed.\n";
} catch ( Throwable $throwable ) {
	fwrite( STDERR, $throwable->getMessage() . "\n" );
	exit( 1 );
} finally {
	emulsify_cli_smoke_remove( $work_root );
}
