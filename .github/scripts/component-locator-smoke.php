<?php
/**
 * Smoke checks for component block discovery.
 *
 * @package Emulsify
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

$GLOBALS['emulsify_locator_smoke_hooks'] = array();
$GLOBALS['emulsify_locator_transients']  = array();
$GLOBALS['emulsify_locator_environment'] = 'production';

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['emulsify_locator_smoke_hooks'][ $hook ][ $priority ][] = array(
			'accepted_args' => $accepted_args,
			'callback'      => $callback,
		);

		ksort( $GLOBALS['emulsify_locator_smoke_hooks'][ $hook ] );

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$arguments ) {
		if ( empty( $GLOBALS['emulsify_locator_smoke_hooks'][ $hook ] ) ) {
			return $value;
		}

		foreach ( $GLOBALS['emulsify_locator_smoke_hooks'][ $hook ] as $callbacks ) {
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

if ( ! function_exists( 'get_stylesheet' ) ) {
	function get_stylesheet(): string {
		return $GLOBALS['emulsify_locator_stylesheet'];
	}
}

if ( ! function_exists( 'get_stylesheet_directory' ) ) {
	function get_stylesheet_directory(): string {
		return $GLOBALS['emulsify_locator_child_theme'];
	}
}

if ( ! function_exists( 'get_template' ) ) {
	function get_template(): string {
		return $GLOBALS['emulsify_locator_template'];
	}
}

if ( ! function_exists( 'get_template_directory' ) ) {
	function get_template_directory(): string {
		return $GLOBALS['emulsify_locator_parent_theme'];
	}
}

if ( ! function_exists( 'wp_get_theme' ) ) {
	function wp_get_theme( string $stylesheet = '' ) {
		return new class( $stylesheet ) {
			/**
			 * Theme stylesheet slug.
			 *
			 * @var string
			 */
			private $stylesheet;

			/**
			 * Constructor.
			 *
			 * @param string $stylesheet Theme stylesheet slug.
			 */
			public function __construct( string $stylesheet ) {
				$this->stylesheet = $stylesheet;
			}

			/**
			 * Gets theme metadata.
			 *
			 * @param string $header Metadata header.
			 * @return string Metadata value.
			 */
			public function get( string $header ): string {
				if ( 'Version' !== $header ) {
					return '';
				}

				return isset( $GLOBALS['emulsify_locator_theme_versions'][ $this->stylesheet ] )
					? $GLOBALS['emulsify_locator_theme_versions'][ $this->stylesheet ]
					: '';
			}
		};
	}
}

if ( ! function_exists( 'wp_get_environment_type' ) ) {
	function wp_get_environment_type(): string {
		return $GLOBALS['emulsify_locator_environment'];
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $key ) {
		if ( array_key_exists( 'emulsify_locator_transient_override', $GLOBALS ) ) {
			return $GLOBALS['emulsify_locator_transient_override'];
		}

		return array_key_exists( $key, $GLOBALS['emulsify_locator_transients'] )
			? $GLOBALS['emulsify_locator_transients'][ $key ]
			: false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $key, $value, int $expiration = 0 ): bool {
		unset( $expiration );

		$GLOBALS['emulsify_locator_transients'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $key ): bool {
		$exists = array_key_exists( $key, $GLOBALS['emulsify_locator_transients'] );
		unset( $GLOBALS['emulsify_locator_transients'][ $key ] );

		return $exists;
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( string $title ): string {
		$slug = preg_replace( '/[^a-z0-9]+/i', '-', strtolower( $title ) );
		$slug = trim( (string) $slug, '-' );

		return $slug;
	}
}

if ( ! function_exists( 'acf_register_block_type' ) ) {
	function acf_register_block_type( array $args ) {
		$GLOBALS['emulsify_locator_acf_registered'][] = $args;

		return $args;
	}
}

if ( ! function_exists( 'acf_get_block_type' ) ) {
	function acf_get_block_type( string $name ) {
		return isset( $GLOBALS['emulsify_locator_existing_acf_blocks'][ $name ] )
			? $GLOBALS['emulsify_locator_existing_acf_blocks'][ $name ]
			: null;
	}
}

/**
 * Fails the smoke script when an assertion is false.
 *
 * @param bool   $condition Assertion condition.
 * @param string $message   Failure message.
 * @return void
 */
function emulsify_locator_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Writes a fixture file, creating its parent directory when needed.
 *
 * @param string $path     File path.
 * @param string $contents File contents.
 * @return void
 */
function emulsify_locator_smoke_write( string $path, string $contents ): void {
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
function emulsify_locator_smoke_remove( string $path ): void {
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
 * Gets a list of record relative paths.
 *
 * @param array $records Component records.
 * @return array Relative paths.
 */
function emulsify_locator_smoke_relatives( array $records ): array {
	return array_map(
		static function ( array $record ): string {
			return $record['relative'];
		},
		$records
	);
}

/**
 * Checks whether a duplicate record was captured.
 *
 * @param array  $duplicates Duplicate records.
 * @param string $type       Duplicate type.
 * @param string $name       Duplicate name/key.
 * @return bool TRUE when a matching record exists.
 */
function emulsify_locator_smoke_has_duplicate( array $duplicates, string $type, string $name ): bool {
	foreach ( $duplicates as $duplicate ) {
		if ( $type === $duplicate['type'] && $name === $duplicate['name'] ) {
			return true;
		}
	}

	return false;
}

/**
 * Gets registered ACF block names from the smoke stub.
 *
 * @return array Registered ACF block names.
 */
function emulsify_locator_smoke_acf_registered_names(): array {
	return array_map(
		static function ( array $args ): string {
			return $args['name'];
		},
		$GLOBALS['emulsify_locator_acf_registered']
	);
}

$repo_root = dirname( __DIR__, 2 );
$work_root = sys_get_temp_dir() . '/emulsify-component-locator-' . uniqid( '', true );
$child = $work_root . '/child-theme';
$parent = $work_root . '/parent-theme';

$GLOBALS['emulsify_locator_child_theme']  = $child;
$GLOBALS['emulsify_locator_parent_theme'] = $parent;
$GLOBALS['emulsify_locator_stylesheet']   = 'child-theme';
$GLOBALS['emulsify_locator_template']     = 'parent-theme';
$GLOBALS['emulsify_locator_theme_versions'] = array(
	'child-theme'  => '1.0.0',
	'parent-theme' => '2.0.0',
);
$GLOBALS['emulsify_locator_acf_registered'] = array();
$GLOBALS['emulsify_locator_existing_acf_blocks'] = array();

try {
	emulsify_locator_smoke_write( $child . '/dist/components/card/card.component.json', '{"title":"Child Card"}' );
	emulsify_locator_smoke_write( $child . '/dist/components/card/card.twig', '<article>Child card</article>' );
	emulsify_locator_smoke_write( $child . '/dist/components/icon-card/icon-card.component.json', '{"title":"Child Icon Card"}' );
	emulsify_locator_smoke_write( $child . '/dist/components/icon-card/icon-card.twig', '<article>Child icon card</article>' );
	emulsify_locator_smoke_write( $parent . '/dist/components/card/card.component.json', '{"title":"Parent Card"}' );
	emulsify_locator_smoke_write( $parent . '/dist/components/card/card.twig', '<article>Parent card</article>' );
	emulsify_locator_smoke_write( $parent . '/dist/components/icon/card/card.component.json', '{"title":"Parent Icon Card"}' );
	emulsify_locator_smoke_write( $parent . '/dist/components/icon/card/card.twig', '<article>Parent icon card</article>' );
	emulsify_locator_smoke_write( $parent . '/dist/components/parent-card/parent-card.component.json', '{"title":"Parent Only"}' );
	emulsify_locator_smoke_write( $parent . '/dist/components/parent-card/parent-card.twig', '<article>Parent only</article>' );
	emulsify_locator_smoke_write( $child . '/dist/components/native/block.json', '{"name":"emulsify/native"}' );
	emulsify_locator_smoke_write( $parent . '/dist/components/duplicate-native/block.json', '{"name":"emulsify/native"}' );
	emulsify_locator_smoke_write( $parent . '/dist/components/native/block.json', '{"name":"emulsify/native-parent"}' );
	emulsify_locator_smoke_write( $parent . '/dist/components/parent-native/block.json', '{"name":"emulsify/parent-native"}' );

	require_once $repo_root . '/includes/Support/FileDiscovery.php';
	require_once $repo_root . '/includes/Blocks/ComponentLocator.php';

	$locator = new Emulsify\Theme\Blocks\ComponentLocator();
	$acf = $locator->acf_components();

	emulsify_locator_smoke_assert(
		array( 'card', 'icon-card', 'parent-card' ) === emulsify_locator_smoke_relatives( $acf ),
		'ACF/Twig discovery should be deterministic and child-theme-first.'
	);
	emulsify_locator_smoke_assert(
		false !== strpos( $acf[0]['metadata_path'], '/child-theme/dist/components/card/card.component.json' ),
		'Child ACF/Twig component metadata should override a parent component at the same relative path.'
	);
	emulsify_locator_smoke_assert(
		'dist/components/card/card.twig' === $acf[0]['template'],
		'ACF/Twig discovery should return the theme-relative Twig template.'
	);

	emulsify_locator_smoke_write( $child . '/dist/components/late-native/block.json', '{"name":"emulsify/late-native"}' );

	$native = $locator->native_block_directories();

	emulsify_locator_smoke_assert(
		array( 'native', 'parent-native' ) === emulsify_locator_smoke_relatives( $native ),
		'Native block discovery should reuse the memoized file index and remain child-theme-first.'
	);
	emulsify_locator_smoke_assert(
		false !== strpos( $native[0]['path'], '/child-theme/dist/components/native' ),
		'Child native block metadata should override a parent block at the same relative path.'
	);

	$locator_duplicates = $locator->skipped_duplicates();

	emulsify_locator_smoke_assert(
		emulsify_locator_smoke_has_duplicate( $locator_duplicates, 'acf_component_path', 'card' ),
		'ACF/Twig discovery should report duplicate component paths.'
	);
	emulsify_locator_smoke_assert(
		emulsify_locator_smoke_has_duplicate( $locator_duplicates, 'acf_component_slug', 'icon-card' ),
		'ACF/Twig discovery should report duplicate component slugs.'
	);
	emulsify_locator_smoke_assert(
		emulsify_locator_smoke_has_duplicate( $locator_duplicates, 'native_component_path', 'native' ),
		'Native discovery should report duplicate component paths.'
	);
	emulsify_locator_smoke_assert(
		emulsify_locator_smoke_has_duplicate( $locator_duplicates, 'native_block_name', 'emulsify/native' ),
		'Native discovery should report duplicate block.json name values.'
	);

	emulsify_locator_smoke_write( $child . '/dist/components/late-card/late-card.component.json', '{"title":"Late Card"}' );
	emulsify_locator_smoke_write( $child . '/dist/components/late-card/late-card.twig', '<article>Late card</article>' );

	emulsify_locator_smoke_assert(
		array( 'card', 'icon-card', 'parent-card' ) === emulsify_locator_smoke_relatives( $locator->acf_components() ),
		'ACF/Twig discovery should return the per-request memoized result on repeated calls.'
	);

	$fresh_locator = new Emulsify\Theme\Blocks\ComponentLocator();

	emulsify_locator_smoke_assert(
		in_array( 'late-card', emulsify_locator_smoke_relatives( $fresh_locator->acf_components() ), true ),
		'A fresh locator instance should see files that were not present during the previous locator scan.'
	);
	emulsify_locator_smoke_assert(
		in_array( 'late-native', emulsify_locator_smoke_relatives( $fresh_locator->native_block_directories() ), true ),
		'A fresh locator instance should see native blocks that were not present during the previous locator scan.'
	);

	emulsify_locator_smoke_write( $child . '/dist/components/acf-name-a/acf-name-a.component.json', '{"name":"emulsify/shared-acf","title":"Shared A"}' );
	emulsify_locator_smoke_write( $child . '/dist/components/acf-name-a/acf-name-a.twig', '<article>Shared A</article>' );
	emulsify_locator_smoke_write( $child . '/dist/components/acf-name-b/acf-name-b.component.json', '{"name":"emulsify/shared-acf","title":"Shared B"}' );
	emulsify_locator_smoke_write( $child . '/dist/components/acf-name-b/acf-name-b.twig', '<article>Shared B</article>' );

	require_once $repo_root . '/includes/Support/AssetManifest.php';
	require_once $repo_root . '/includes/Blocks/AcfBlocks.php';

	$acf_blocks = new Emulsify\Theme\Blocks\AcfBlocks( new Emulsify\Theme\Blocks\ComponentLocator() );
	$acf_blocks->register_blocks();
	$registered_names = emulsify_locator_smoke_acf_registered_names();

	emulsify_locator_smoke_assert(
		1 === count( array_keys( $registered_names, 'emulsify-shared-acf', true ) ),
		'ACF block registration should normalize and skip duplicate final block names.'
	);
	emulsify_locator_smoke_assert(
		emulsify_locator_smoke_has_duplicate( $acf_blocks->skipped_duplicates(), 'acf_block_name', 'emulsify-shared-acf' ),
		'ACF block registration should report duplicate normalized final block names.'
	);

	add_filter(
		'emulsify_theme_component_discovery_cache_enabled',
		static function (): bool {
			return true;
		}
	);
	add_filter(
		'emulsify_theme_component_discovery_cache_key_parts',
		static function ( array $key_parts ): array {
			$key_parts['smoke'] = 'component-locator';

			return $key_parts;
		}
	);
	add_filter(
		'emulsify_theme_component_discovery_cache_ttl',
		static function (): int {
			return 3600;
		}
	);

	$cache_child  = $work_root . '/cache-child';
	$cache_parent = $work_root . '/cache-parent';
	$GLOBALS['emulsify_locator_child_theme']  = $cache_child;
	$GLOBALS['emulsify_locator_parent_theme'] = $cache_parent;
	$GLOBALS['emulsify_locator_stylesheet']   = 'cache-child';
	$GLOBALS['emulsify_locator_template']     = 'cache-parent';
	$GLOBALS['emulsify_locator_theme_versions'] = array(
		'cache-child'  => '1.0.0',
		'cache-parent' => '2.0.0',
	);
	$GLOBALS['emulsify_locator_transients'] = array();
	emulsify_locator_smoke_write( $cache_child . '/dist/components/cache-card/cache-card.component.json', '{"title":"Cache Card"}' );
	emulsify_locator_smoke_write( $cache_child . '/dist/components/cache-card/cache-card.twig', '<article>Cache card</article>' );

	$cache_locator = new Emulsify\Theme\Blocks\ComponentLocator();

	emulsify_locator_smoke_assert(
		array( 'cache-card' ) === emulsify_locator_smoke_relatives( $cache_locator->acf_components() ),
		'Missing persistent discovery cache should fall back to filesystem scanning.'
	);

	emulsify_locator_smoke_write( $cache_child . '/dist/components/cache-late/cache-late.component.json', '{"title":"Cache Late"}' );
	emulsify_locator_smoke_write( $cache_child . '/dist/components/cache-late/cache-late.twig', '<article>Cache late</article>' );

	$cached_locator = new Emulsify\Theme\Blocks\ComponentLocator();

	emulsify_locator_smoke_assert(
		! in_array( 'cache-late', emulsify_locator_smoke_relatives( $cached_locator->acf_components() ), true ),
		'Enabled persistent discovery cache should be reused across locator instances.'
	);
	emulsify_locator_smoke_assert(
		Emulsify\Theme\Blocks\ComponentLocator::clear_discovery_cache(),
		'Component discovery cache clear method should delete the active transient.'
	);

	$cleared_locator = new Emulsify\Theme\Blocks\ComponentLocator();

	emulsify_locator_smoke_assert(
		in_array( 'cache-late', emulsify_locator_smoke_relatives( $cleared_locator->acf_components() ), true ),
		'Clearing persistent discovery cache should force the next locator to scan.'
	);

	$version_child  = $work_root . '/version-child';
	$version_parent = $work_root . '/version-parent';
	$GLOBALS['emulsify_locator_child_theme']  = $version_child;
	$GLOBALS['emulsify_locator_parent_theme'] = $version_parent;
	$GLOBALS['emulsify_locator_stylesheet']   = 'version-child';
	$GLOBALS['emulsify_locator_template']     = 'version-parent';
	$GLOBALS['emulsify_locator_theme_versions'] = array(
		'version-child'  => '1.0.0',
		'version-parent' => '2.0.0',
	);
	$GLOBALS['emulsify_locator_transients'] = array();
	emulsify_locator_smoke_write( $version_child . '/dist/components/version-card/version-card.component.json', '{"title":"Version Card"}' );
	emulsify_locator_smoke_write( $version_child . '/dist/components/version-card/version-card.twig', '<article>Version card</article>' );
	( new Emulsify\Theme\Blocks\ComponentLocator() )->acf_components();
	emulsify_locator_smoke_write( $version_child . '/dist/components/version-late/version-late.component.json', '{"title":"Version Late"}' );
	emulsify_locator_smoke_write( $version_child . '/dist/components/version-late/version-late.twig', '<article>Version late</article>' );

	emulsify_locator_smoke_assert(
		! in_array( 'version-late', emulsify_locator_smoke_relatives( ( new Emulsify\Theme\Blocks\ComponentLocator() )->acf_components() ), true ),
		'Persistent discovery cache should hold until an invalidating key part changes.'
	);

	$GLOBALS['emulsify_locator_theme_versions']['version-child'] = '1.0.1';

	emulsify_locator_smoke_assert(
		in_array( 'version-late', emulsify_locator_smoke_relatives( ( new Emulsify\Theme\Blocks\ComponentLocator() )->acf_components() ), true ),
		'Changing the child theme version should change the persistent discovery cache key.'
	);

	$mtime_child  = $work_root . '/mtime-child';
	$mtime_parent = $work_root . '/mtime-parent';
	$GLOBALS['emulsify_locator_child_theme']  = $mtime_child;
	$GLOBALS['emulsify_locator_parent_theme'] = $mtime_parent;
	$GLOBALS['emulsify_locator_stylesheet']   = 'mtime-child';
	$GLOBALS['emulsify_locator_template']     = 'mtime-parent';
	$GLOBALS['emulsify_locator_theme_versions'] = array(
		'mtime-child'  => '1.0.0',
		'mtime-parent' => '2.0.0',
	);
	$GLOBALS['emulsify_locator_transients'] = array();
	emulsify_locator_smoke_write( $mtime_child . '/dist/emulsify-assets.json', '{"assets":{}}' );
	emulsify_locator_smoke_write( $mtime_child . '/dist/components/mtime-card/mtime-card.component.json', '{"title":"Mtime Card"}' );
	emulsify_locator_smoke_write( $mtime_child . '/dist/components/mtime-card/mtime-card.twig', '<article>Mtime card</article>' );
	( new Emulsify\Theme\Blocks\ComponentLocator() )->acf_components();
	emulsify_locator_smoke_write( $mtime_child . '/dist/components/mtime-late/mtime-late.component.json', '{"title":"Mtime Late"}' );
	emulsify_locator_smoke_write( $mtime_child . '/dist/components/mtime-late/mtime-late.twig', '<article>Mtime late</article>' );

	emulsify_locator_smoke_assert(
		! in_array( 'mtime-late', emulsify_locator_smoke_relatives( ( new Emulsify\Theme\Blocks\ComponentLocator() )->acf_components() ), true ),
		'Persistent discovery cache should include the manifest mtime in its key.'
	);

	touch( $mtime_child . '/dist/emulsify-assets.json', time() + 10 );
	clearstatcache( true, $mtime_child . '/dist/emulsify-assets.json' );

	emulsify_locator_smoke_assert(
		in_array( 'mtime-late', emulsify_locator_smoke_relatives( ( new Emulsify\Theme\Blocks\ComponentLocator() )->acf_components() ), true ),
		'Changing the asset manifest mtime should change the persistent discovery cache key.'
	);

	$invalid_child  = $work_root . '/invalid-cache-child';
	$invalid_parent = $work_root . '/invalid-cache-parent';
	$GLOBALS['emulsify_locator_child_theme']  = $invalid_child;
	$GLOBALS['emulsify_locator_parent_theme'] = $invalid_parent;
	$GLOBALS['emulsify_locator_stylesheet']   = 'invalid-cache-child';
	$GLOBALS['emulsify_locator_template']     = 'invalid-cache-parent';
	$GLOBALS['emulsify_locator_theme_versions'] = array(
		'invalid-cache-child'  => '1.0.0',
		'invalid-cache-parent' => '2.0.0',
	);
	$GLOBALS['emulsify_locator_transients'] = array();
	$GLOBALS['emulsify_locator_transient_override'] = array(
		'files' => array(
			array(
				'path' => $invalid_child . '/broken.component.json',
			),
		),
	);
	emulsify_locator_smoke_write( $invalid_child . '/dist/components/invalid-card/invalid-card.component.json', '{"title":"Invalid Card"}' );
	emulsify_locator_smoke_write( $invalid_child . '/dist/components/invalid-card/invalid-card.twig', '<article>Invalid card</article>' );

	emulsify_locator_smoke_assert(
		array( 'invalid-card' ) === emulsify_locator_smoke_relatives( ( new Emulsify\Theme\Blocks\ComponentLocator() )->acf_components() ),
		'Invalid persistent discovery cache data should fall back to filesystem scanning.'
	);
	unset( $GLOBALS['emulsify_locator_transient_override'] );

	echo "Component locator smoke checks passed.\n";
} catch ( Throwable $throwable ) {
	fwrite( STDERR, $throwable->getMessage() . "\n" );
	exit( 1 );
} finally {
	emulsify_locator_smoke_remove( $work_root );
}
