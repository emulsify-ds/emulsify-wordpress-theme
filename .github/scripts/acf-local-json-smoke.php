<?php
/**
 * Smoke checks for ACF Local JSON path integration.
 *
 * @package Emulsify
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

$GLOBALS['emulsify_acf_json_smoke_filters'] = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['emulsify_acf_json_smoke_filters'][ $hook ][ $priority ][] = array(
			'accepted_args' => $accepted_args,
			'callback'      => $callback,
		);

		ksort( $GLOBALS['emulsify_acf_json_smoke_filters'][ $hook ] );

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$arguments ) {
		if ( empty( $GLOBALS['emulsify_acf_json_smoke_filters'][ $hook ] ) ) {
			return $value;
		}

		foreach ( $GLOBALS['emulsify_acf_json_smoke_filters'][ $hook ] as $callbacks ) {
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

if ( ! function_exists( 'get_stylesheet_directory' ) ) {
	function get_stylesheet_directory(): string {
		return $GLOBALS['emulsify_acf_json_smoke_child'];
	}
}

/**
 * Fails the smoke script when an assertion is false.
 *
 * @param bool   $condition Assertion condition.
 * @param string $message   Failure message.
 * @return void
 */
function emulsify_acf_json_smoke_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Recursively removes a path.
 *
 * @param string $path Path to remove.
 * @return void
 */
function emulsify_acf_json_smoke_remove( string $path ): void {
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
$work_root = sys_get_temp_dir() . '/emulsify-acf-local-json-' . uniqid( '', true );
$child     = $work_root . '/child-theme';
$default   = $child . '/config/acf-json';
$custom    = $work_root . '/custom-acf-json';
$extra     = $work_root . '/extra-acf-json';

$GLOBALS['emulsify_acf_json_smoke_child'] = $child;

try {
	require_once $repo_root . '/includes/class-acf-local-json.php';

	$inactive = new Emulsify\Theme\Acf_Local_JSON();
	$inactive->register();

	emulsify_acf_json_smoke_assert(
		empty( $GLOBALS['emulsify_acf_json_smoke_filters']['acf/settings/save_json'] )
		&& empty( $GLOBALS['emulsify_acf_json_smoke_filters']['acf/settings/load_json'] ),
		'ACF Local JSON service should not register ACF filters when ACF is unavailable.'
	);

	if ( ! function_exists( 'acf' ) ) {
		function acf(): bool {
			return true;
		}
	}

	$service = new Emulsify\Theme\Acf_Local_JSON();
	$service->register();

	emulsify_acf_json_smoke_assert(
		isset( $GLOBALS['emulsify_acf_json_smoke_filters']['acf/settings/save_json'] )
		&& isset( $GLOBALS['emulsify_acf_json_smoke_filters']['acf/settings/load_json'] ),
		'ACF Local JSON service should register ACF filters when ACF is available.'
	);

	$incoming_save = '/acf/default-save';
	$incoming_load = array( '/acf/default-load' );

	emulsify_acf_json_smoke_assert(
		$incoming_save === apply_filters( 'acf/settings/save_json', $incoming_save ),
		'ACF Local JSON save path should no-op when config/acf-json is missing.'
	);
	emulsify_acf_json_smoke_assert(
		$incoming_load === apply_filters( 'acf/settings/load_json', $incoming_load ),
		'ACF Local JSON load paths should no-op when config/acf-json is missing.'
	);

	if ( ! mkdir( $default, 0777, true ) || ! mkdir( $custom, 0777, true ) || ! mkdir( $extra, 0777, true ) ) {
		throw new RuntimeException( 'Could not create ACF JSON smoke fixture directories.' );
	}

	emulsify_acf_json_smoke_assert(
		$default === apply_filters( 'acf/settings/save_json', $incoming_save ),
		'ACF Local JSON save path should use child config/acf-json by default.'
	);
	emulsify_acf_json_smoke_assert(
		array( '/acf/default-load', $default ) === apply_filters( 'acf/settings/load_json', $incoming_load ),
		'ACF Local JSON load paths should keep the default path and add child config/acf-json.'
	);

	add_filter(
		'emulsify_theme_acf_json_remove_default_load_path',
		static function (): bool {
			return true;
		}
	);

	emulsify_acf_json_smoke_assert(
		array( $default ) === apply_filters( 'acf/settings/load_json', $incoming_load ),
		'ACF Local JSON should remove the default load path only when explicitly configured.'
	);

	add_filter(
		'emulsify_theme_acf_json_save_path',
		static function () use ( $custom ): string {
			return $custom;
		}
	);

	add_filter(
		'emulsify_theme_acf_json_load_paths',
		static function ( array $paths ) use ( $extra ): array {
			$paths[] = $extra;

			return $paths;
		}
	);

	emulsify_acf_json_smoke_assert(
		$custom === apply_filters( 'acf/settings/save_json', $incoming_save ),
		'ACF Local JSON save path filter should override the default save path.'
	);
	emulsify_acf_json_smoke_assert(
		array( $custom, $extra ) === apply_filters( 'acf/settings/load_json', $incoming_load ),
		'ACF Local JSON load paths filter should alter final load paths.'
	);

	add_filter(
		'emulsify_theme_acf_json_enabled',
		static function (): bool {
			return false;
		},
		1
	);

	emulsify_acf_json_smoke_assert(
		$incoming_save === apply_filters( 'acf/settings/save_json', $incoming_save )
		&& $incoming_load === apply_filters( 'acf/settings/load_json', $incoming_load ),
		'ACF Local JSON enabled filter should disable all path changes.'
	);

	echo "ACF Local JSON smoke checks passed.\n";
} catch ( Throwable $throwable ) {
	fwrite( STDERR, $throwable->getMessage() . "\n" );
	exit( 1 );
} finally {
	emulsify_acf_json_smoke_remove( $work_root );
}
