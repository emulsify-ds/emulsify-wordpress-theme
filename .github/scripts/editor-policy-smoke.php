<?php
/**
 * Smoke checks for block editor policy filters.
 *
 * @package Emulsify
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from the command line.\n" );
	exit( 1 );
}

$GLOBALS['emulsify_editor_policy_smoke_filters']      = array();
$GLOBALS['emulsify_editor_policy_smoke_current_caps'] = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['emulsify_editor_policy_smoke_filters'][ $hook ][ $priority ][] = array(
			'accepted_args' => $accepted_args,
			'callback'      => $callback,
		);

		ksort( $GLOBALS['emulsify_editor_policy_smoke_filters'][ $hook ] );

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$arguments ) {
		if ( empty( $GLOBALS['emulsify_editor_policy_smoke_filters'][ $hook ] ) ) {
			return $value;
		}

		foreach ( $GLOBALS['emulsify_editor_policy_smoke_filters'][ $hook ] as $callbacks ) {
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
		return $GLOBALS['emulsify_editor_policy_smoke_child'];
	}
}

if ( ! function_exists( 'get_template_directory' ) ) {
	function get_template_directory(): string {
		return $GLOBALS['emulsify_editor_policy_smoke_parent'];
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $post ): string {
		return is_object( $post ) && isset( $post->post_type ) ? (string) $post->post_type : '';
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return in_array( $capability, $GLOBALS['emulsify_editor_policy_smoke_current_caps'], true );
	}
}

/**
 * Fails the smoke script when an assertion is false.
 *
 * @param bool   $condition Assertion condition.
 * @param string $message   Failure message.
 * @return void
 */
function emulsify_editor_policy_smoke_assert( bool $condition, string $message ): void {
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
function emulsify_editor_policy_smoke_write( string $path, string $contents ): void {
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
function emulsify_editor_policy_smoke_remove( string $path ): void {
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
$work_root = sys_get_temp_dir() . '/emulsify-editor-policy-' . uniqid( '', true );
$child     = $work_root . '/child-theme';
$parent    = $work_root . '/parent-theme';
$context   = (object) array(
	'post' => (object) array(
		'post_type' => 'page',
	),
);

$GLOBALS['emulsify_editor_policy_smoke_child']  = $child;
$GLOBALS['emulsify_editor_policy_smoke_parent'] = $parent;

try {
	require_once $repo_root . '/includes/Editor/Policy.php';

	$policy = new Emulsify\Theme\Editor\Policy();
	$policy->register();

	foreach ( array( 'allowed_block_types_all', 'block_editor_settings_all', 'register_post_type_args', 'block_type_metadata_settings', 'register_block_type_args' ) as $hook ) {
		emulsify_editor_policy_smoke_assert(
			isset( $GLOBALS['emulsify_editor_policy_smoke_filters'][ $hook ] ),
			sprintf( 'Editor policy should register the %s hook.', $hook )
		);
	}

	emulsify_editor_policy_smoke_assert(
		true === apply_filters( 'allowed_block_types_all', true, $context ),
		'Editor policy should preserve default allowed block behavior when unconfigured.'
	);

	$default_settings = array(
		'blockPatterns'      => array(
			array(
				'name' => 'core/query',
			),
		),
		'enableUserPatterns' => true,
	);

	emulsify_editor_policy_smoke_assert(
		$default_settings === apply_filters( 'block_editor_settings_all', $default_settings, $context ),
		'Editor policy should preserve editor settings when unconfigured.'
	);

	emulsify_editor_policy_smoke_assert(
		array( 'capabilities' => array( 'create_posts' => 'edit_posts' ) ) === apply_filters(
			'register_post_type_args',
			array(
				'capabilities' => array(
					'create_posts' => 'edit_posts',
				),
			),
			'wp_block'
		),
		'Editor policy should preserve wp_block capabilities when unconfigured.'
	);

	emulsify_editor_policy_smoke_write(
		$child . '/patterns/hero.json',
		(string) json_encode(
			array(
				'content' => '<!-- wp:acf/project-hero {"name":"acf/project-hero"} /--><!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->',
			)
		)
	);

	add_filter(
		'emulsify_theme_editor_policy_options',
		static function ( array $options ) use ( $child ): array {
			$options['allowed_block_types'] = array(
				'default'      => array( 'core/paragraph', 'heading' ),
				'by_post_type' => array(
					'page' => array( 'core/image' ),
				),
			);
			$options['auto_allow_pattern_blocks']            = true;
			$options['pattern_directories']                  = array( $child . '/patterns' );
			$options['pattern_namespaces']                   = array( 'project', 'theme' );
			$options['disable_user_patterns_for_non_admins'] = true;
			$options['admin_capability']                     = 'manage_options';
			$options['restrict_wp_block_creation']           = true;
			$options['wp_block_create_capability']           = 'manage_options';
			$options['block_support_overrides']              = array(
				'*'           => array(
					'supports' => array(
						'styles' => false,
					),
				),
				'core/button' => array(
					'styles'   => array(),
					'supports' => array(
						'color' => array(
							'gradients' => false,
						),
					),
				),
			);

			return $options;
		}
	);

	add_filter(
		'emulsify_theme_allowed_block_types',
		static function ( ?array $blocks, string $post_type ): ?array {
			if ( 'page' === $post_type && is_array( $blocks ) ) {
				$blocks[] = 'core/quote';
			}

			return $blocks;
		},
		10,
		2
	);

	add_filter(
		'emulsify_theme_pattern_namespaces',
		static function ( array $namespaces ): array {
			$namespaces[] = 'child';

			return $namespaces;
		}
	);

	add_filter(
		'emulsify_theme_block_support_overrides',
		static function ( array $overrides, string $block_name, array $settings, string $source ): array {
			if ( 'core/button' === $block_name && 'register_block_type_args' === $source ) {
				$overrides['core/button']['supports']['anchor'] = false;
			}

			return $overrides;
		},
		10,
		4
	);

	$allowed = apply_filters( 'allowed_block_types_all', array( 'core/html' ), $context );

	foreach ( array( 'core/html', 'core/paragraph', 'core/heading', 'core/image', 'core/quote', 'acf/project-hero' ) as $expected_block ) {
		emulsify_editor_policy_smoke_assert(
			in_array( $expected_block, $allowed, true ),
			sprintf( 'Allowed block policy should include %s.', $expected_block )
		);
	}

	emulsify_editor_policy_smoke_assert(
		1 === count( array_keys( $allowed, 'core/paragraph', true ) ),
		'Allowed block policy should deduplicate configured and pattern-derived block names.'
	);

	$filtered_settings = apply_filters(
		'block_editor_settings_all',
		array(
			'blockPatterns'      => array(
				array(
					'name' => 'project/hero',
				),
				array(
					'name' => 'theme/card',
				),
				array(
					'name' => 'child/banner',
				),
				array(
					'name' => 'core/query',
				),
			),
			'enableUserPatterns' => true,
		),
		$context
	);
	$pattern_names     = array_column( $filtered_settings['blockPatterns'], 'name' );

	emulsify_editor_policy_smoke_assert(
		array( 'project/hero', 'theme/card', 'child/banner' ) === $pattern_names,
		'Pattern namespace policy should keep only configured namespaces.'
	);
	emulsify_editor_policy_smoke_assert(
		false === $filtered_settings['enableUserPatterns'],
		'Editor policy should disable user-created patterns for users without the admin capability.'
	);

	$wp_block_args = apply_filters( 'register_post_type_args', array( 'capabilities' => array( 'edit_posts' => 'edit_posts' ) ), 'wp_block' );

	emulsify_editor_policy_smoke_assert(
		true === $wp_block_args['map_meta_cap'] && 'manage_options' === $wp_block_args['capabilities']['create_posts'],
		'Editor policy should restrict wp_block creation to the configured capability.'
	);
	emulsify_editor_policy_smoke_assert(
		array() === apply_filters( 'register_post_type_args', array(), 'post' ),
		'Editor policy should leave non-wp_block post type arguments alone.'
	);

	$metadata_settings = apply_filters(
		'block_type_metadata_settings',
		array(
			'supports' => array(
				'spacing' => true,
			),
		),
		array(
			'name' => 'core/button',
		)
	);

	emulsify_editor_policy_smoke_assert(
		false === $metadata_settings['supports']['styles']
		&& true === $metadata_settings['supports']['spacing']
		&& false === $metadata_settings['supports']['color']['gradients']
		&& array() === $metadata_settings['styles'],
		'Block support overrides should apply to metadata settings without dropping existing supports.'
	);

	$registration_args = apply_filters(
		'register_block_type_args',
		array(
			'supports' => array(
				'anchor' => true,
			),
		),
		'core/button'
	);

	emulsify_editor_policy_smoke_assert(
		false === $registration_args['supports']['styles']
		&& false === $registration_args['supports']['anchor']
		&& false === $registration_args['supports']['color']['gradients'],
		'Block support overrides should apply to register_block_type_args and honor source-aware filters.'
	);

	echo "Editor policy smoke checks passed.\n";
} catch ( Throwable $throwable ) {
	fwrite( STDERR, $throwable->getMessage() . "\n" );
	exit( 1 );
} finally {
	emulsify_editor_policy_smoke_remove( $work_root );
}
