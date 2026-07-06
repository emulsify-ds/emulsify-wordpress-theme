<?php
/**
 * Smoke checks for optional editor enhancements.
 *
 * @package Emulsify
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

$GLOBALS['emulsify_editor_enhancements_smoke_hooks']   = array();
$GLOBALS['emulsify_editor_enhancements_smoke_styles']  = array();
$GLOBALS['emulsify_editor_enhancements_smoke_scripts'] = array();
$GLOBALS['emulsify_editor_enhancements_smoke_inline']  = array();
$GLOBALS['emulsify_editor_enhancements_smoke_captions'] = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['emulsify_editor_enhancements_smoke_hooks'][ $hook ][ $priority ][] = array(
			'accepted_args' => $accepted_args,
			'callback'      => $callback,
		);

		ksort( $GLOBALS['emulsify_editor_enhancements_smoke_hooks'][ $hook ] );

		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$arguments ) {
		if ( empty( $GLOBALS['emulsify_editor_enhancements_smoke_hooks'][ $hook ] ) ) {
			return $value;
		}

		foreach ( $GLOBALS['emulsify_editor_enhancements_smoke_hooks'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = call_user_func_array(
					$callback['callback'],
					array_slice(
						array_merge( array( $value ), $arguments ),
						0,
						$callback['accepted_args']
					)
				);
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook, ...$arguments ): void {
		if ( empty( $GLOBALS['emulsify_editor_enhancements_smoke_hooks'][ $hook ] ) ) {
			return;
		}

		foreach ( $GLOBALS['emulsify_editor_enhancements_smoke_hooks'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				call_user_func_array(
					$callback['callback'],
					array_slice( $arguments, 0, $callback['accepted_args'] )
				);
			}
		}
	}
}

if ( ! function_exists( 'get_stylesheet_directory' ) ) {
	function get_stylesheet_directory(): string {
		return $GLOBALS['emulsify_editor_enhancements_smoke_child'];
	}
}

if ( ! function_exists( 'get_template_directory' ) ) {
	function get_template_directory(): string {
		return $GLOBALS['emulsify_editor_enhancements_smoke_parent'];
	}
}

if ( ! function_exists( 'get_stylesheet_directory_uri' ) ) {
	function get_stylesheet_directory_uri(): string {
		return 'https://example.test/child';
	}
}

if ( ! function_exists( 'get_template_directory_uri' ) ) {
	function get_template_directory_uri(): string {
		return 'https://example.test/parent';
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle, string $src, array $deps = array(), $ver = false ): void {
		$GLOBALS['emulsify_editor_enhancements_smoke_styles'][] = compact( 'handle', 'src', 'deps', 'ver' );
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, string $src, array $deps = array(), $ver = false, $args = array() ): void {
		$GLOBALS['emulsify_editor_enhancements_smoke_scripts'][] = compact( 'handle', 'src', 'deps', 'ver', 'args' );
	}
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( string $handle, string $data, string $position = 'after' ): bool {
		$GLOBALS['emulsify_editor_enhancements_smoke_inline'][] = compact( 'handle', 'data', 'position' );

		return true;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return trim( preg_replace( '/[^a-z0-9_-]+/', '-', strtolower( $key ) ), '-' );
	}
}

if ( ! function_exists( 'wp_get_attachment_caption' ) ) {
	function wp_get_attachment_caption( int $attachment_id ) {
		return $GLOBALS['emulsify_editor_enhancements_smoke_captions'][ $attachment_id ] ?? '';
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}

/**
 * Fails the smoke script when an assertion is false.
 *
 * @param bool   $condition Assertion condition.
 * @param string $message   Failure message.
 * @return void
 */
function emulsify_editor_enhancements_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Writes a fixture file.
 *
 * @param string $path     File path.
 * @param string $contents File contents.
 * @return void
 */
function emulsify_editor_enhancements_smoke_write( string $path, string $contents ): void {
	$directory = dirname( $path );

	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0777, true ) ) {
		throw new RuntimeException( sprintf( 'Could not create fixture directory: %s', $directory ) );
	}

	if ( false === file_put_contents( $path, $contents ) ) {
		throw new RuntimeException( sprintf( 'Could not write fixture file: %s', $path ) );
	}
}

/**
 * Recursively removes a path.
 *
 * @param string $path Path to remove.
 * @return void
 */
function emulsify_editor_enhancements_smoke_remove( string $path ): void {
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

$repo_root = dirname( __DIR__, 2 );
$work_root = sys_get_temp_dir() . '/emulsify-editor-enhancements-' . uniqid( '', true );
$child     = $work_root . '/child-theme';
$parent    = $work_root . '/parent-theme';

$GLOBALS['emulsify_editor_enhancements_smoke_child']  = $child;
$GLOBALS['emulsify_editor_enhancements_smoke_parent'] = $parent;

try {
	require_once $repo_root . '/includes/Editor/Enhancements.php';

	$service = new Emulsify\Theme\Editor\Enhancements();
	$service->register();

	foreach ( array( 'enqueue_block_editor_assets', 'render_block_data', 'render_block' ) as $hook ) {
		emulsify_editor_enhancements_smoke_assert(
			isset( $GLOBALS['emulsify_editor_enhancements_smoke_hooks'][ $hook ] ),
			sprintf( 'Editor enhancements should register the %s hook.', $hook )
		);
	}

	$file_block = array(
		'blockName' => 'core/file',
		'attrs'     => array(
			'id'                          => 42,
			'emulsifyShowMediaCaption'    => true,
			'className'                   => 'existing-class',
		),
	);
	$content    = '<div class="wp-block-file"><a href="/example.pdf">Example PDF</a></div>';

	emulsify_editor_enhancements_smoke_assert(
		$content === apply_filters( 'render_block', $content, $file_block, null ),
		'File captions should be no-op by default.'
	);

	add_filter(
		'emulsify_theme_editor_enhancements_config',
		static function ( array $config ): array {
			$config['columnsEqualHeight']['enabled'] = true;
			$config['fileCaption']['enabled']        = true;
			$config['embedVariations']['enabled']    = true;
			$config['placement']                     = array(
				'enabled'    => true,
				'blocks'     => array( 'acf/example-hero' ),
				'singleton'  => true,
				'requireTop' => true,
			);

			return $config;
		}
	);

	$asset_directories_seen = false;
	$asset_files_seen       = false;

	add_filter(
		'emulsify_theme_editor_asset_directories',
		static function ( array $directories, string $directory ) use ( &$asset_directories_seen ): array {
			$asset_directories_seen = $asset_directories_seen || 'dist/global/editor' === $directory;

			return $directories;
		},
		10,
		2
	);

	add_filter(
		'emulsify_theme_editor_asset_files',
		static function ( array $assets, string $directory ) use ( &$asset_files_seen ): array {
			$asset_files_seen = $asset_files_seen || 'dist/global/editor' === $directory;

			return $assets;
		},
		10,
		2
	);

	$parsed = apply_filters( 'render_block_data', $file_block, $file_block, null );

	emulsify_editor_enhancements_smoke_assert(
		false !== strpos( $parsed['attrs']['className'], 'existing-class' ) && false !== strpos( $parsed['attrs']['className'], 'has-media-caption' ),
		'File caption render_block_data should append the configured class.'
	);

	$GLOBALS['emulsify_editor_enhancements_smoke_captions'][42] = 'Media caption';
	$rendered = apply_filters( 'render_block', $content, $file_block, null );

	emulsify_editor_enhancements_smoke_assert(
		false !== strpos( $rendered, '<p class="wp-block-file__media-caption">Media caption</p></div>' ),
		'File caption render_block should append the attachment caption.'
	);

	emulsify_editor_enhancements_smoke_write( $child . '/dist/global/editor/js/index.js', 'window.editorSmoke = true;' );
	emulsify_editor_enhancements_smoke_write( $child . '/dist/global/editor/css/index.css', '.editor-smoke{}' );
	emulsify_editor_enhancements_smoke_write( $parent . '/dist/global/editor/js/index.js', 'window.parentEditorSmoke = true;' );

	do_action( 'enqueue_block_editor_assets' );

	emulsify_editor_enhancements_smoke_assert(
		$asset_directories_seen,
		'emulsify_theme_editor_asset_directories should run during editor asset discovery.'
	);
	emulsify_editor_enhancements_smoke_assert(
		$asset_files_seen,
		'emulsify_theme_editor_asset_files should run during editor asset discovery.'
	);
	emulsify_editor_enhancements_smoke_assert(
		1 === count( $GLOBALS['emulsify_editor_enhancements_smoke_styles'] ),
		'Editor asset enqueue should include the child editor stylesheet.'
	);
	emulsify_editor_enhancements_smoke_assert(
		1 === count( $GLOBALS['emulsify_editor_enhancements_smoke_scripts'] ),
		'Editor asset enqueue should include the child editor script and skip duplicate parent relative paths.'
	);
	emulsify_editor_enhancements_smoke_assert(
		in_array( 'wp-blocks', $GLOBALS['emulsify_editor_enhancements_smoke_scripts'][0]['deps'], true ),
		'Editor script should declare WordPress editor dependencies.'
	);
	emulsify_editor_enhancements_smoke_assert(
		! empty( $GLOBALS['emulsify_editor_enhancements_smoke_inline'] )
		&& false !== strpos( $GLOBALS['emulsify_editor_enhancements_smoke_inline'][0]['data'], 'emulsifyEditorEnhancements' )
		&& false !== strpos( $GLOBALS['emulsify_editor_enhancements_smoke_inline'][0]['data'], '"fileCaption":{"enabled":true' ),
		'Editor script should receive the filtered enhancement config.'
	);

	echo "Editor enhancements smoke checks passed.\n";
} finally {
	emulsify_editor_enhancements_smoke_remove( $work_root );
}
