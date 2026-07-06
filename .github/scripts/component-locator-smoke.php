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

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$arguments ) {
		return $value;
	}
}

if ( ! function_exists( 'get_stylesheet_directory' ) ) {
	function get_stylesheet_directory(): string {
		return $GLOBALS['emulsify_locator_child_theme'];
	}
}

if ( ! function_exists( 'get_template_directory' ) ) {
	function get_template_directory(): string {
		return $GLOBALS['emulsify_locator_parent_theme'];
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

	echo "Component locator smoke checks passed.\n";
} catch ( Throwable $throwable ) {
	fwrite( STDERR, $throwable->getMessage() . "\n" );
	exit( 1 );
} finally {
	emulsify_locator_smoke_remove( $work_root );
}
