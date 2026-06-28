<?php
/**
 * Smoke tests for Emulsify Timber attribute helpers.
 *
 * @package Emulsify
 */

require_once __DIR__ . '/../../includes/class-attribute-bag.php';
require_once __DIR__ . '/../../includes/class-twig.php';

use Emulsify\Theme\AttributeBag;
use Emulsify\Theme\Twig;

/**
 * Asserts two values are identical.
 *
 * @param string $label    Assertion label.
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @return void
 */
function emulsify_attribute_helper_assert_same( string $label, $expected, $actual ): void {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, sprintf( "%s failed.\nExpected: %s\nActual:   %s\n", $label, var_export( $expected, true ), var_export( $actual, true ) ) );
	exit( 1 );
}

/**
 * Registers helper functions with a Twig environment.
 *
 * @param \Twig\Environment $environment Twig environment.
 * @param Twig              $helpers     Emulsify helper integration.
 * @return void
 */
function emulsify_register_smoke_twig_functions( \Twig\Environment $environment, Twig $helpers ): void {
	foreach ( $helpers->functions( array() ) as $name => $definition ) {
		$options = array(
			'is_safe'       => $definition['is_safe'] ?? array(),
			'needs_context' => $definition['needs_context'] ?? false,
		);

		$environment->addFunction( new \Twig\TwigFunction( $name, $definition['callable'], $options ) );
	}
}

$helpers = new Twig();

emulsify_attribute_helper_assert_same(
	'bem positional syntax',
	'class="example-card example-card--featured"',
	(string) $helpers->bem( 'example-card', array( 'featured' ) )
);

emulsify_attribute_helper_assert_same(
	'bem object syntax',
	'data-state="open" disabled class="card__title card__title--featured u-mb-0"',
	(string) $helpers->bem(
		array(
			'block'      => 'card',
			'element'    => 'title',
			'modifiers'  => array( 'featured' ),
			'extra'      => array( 'u-mb-0' ),
			'attributes' => array(
				'data-state'  => 'open',
				'disabled'    => true,
				'onclick bad' => 'ignored',
			),
		)
	)
);

emulsify_attribute_helper_assert_same(
	'add_attributes array syntax',
	'class="foo" data-label="A&amp;B" hidden',
	(string) $helpers->add_attributes(
		array(
			'class'      => array( 'foo' ),
			'data-label' => 'A&B',
			'hidden'     => true,
		)
	)
);

$bag = new AttributeBag(
	array(
		'class' => array( 'foo', 'foo' ),
		'id'    => 'example',
	)
);
$bag->merge(
	array(
		'class' => array( 'bar' ),
		'id'    => 'updated',
	)
);

emulsify_attribute_helper_assert_same(
	'AttributeBag merge syntax',
	'class="foo bar" id="updated"',
	(string) $helpers->add_attributes( $bag )
);

emulsify_attribute_helper_assert_same(
	'context-aware add_attributes syntax',
	'class="context foo"',
	(string) $helpers->add_attributes(
		array( 'attributes' => new AttributeBag( array( 'class' => array( 'context' ) ) ) ),
		array( 'class' => array( 'foo' ) )
	)
);

emulsify_attribute_helper_assert_same(
	'context-aware bem syntax',
	'class="example-card example-card--featured context"',
	(string) $helpers->bem(
		array( 'attributes' => new AttributeBag( array( 'class' => array( 'context' ) ) ) ),
		'example-card',
		array( 'featured' )
	)
);

$autoload = __DIR__ . '/../../vendor/autoload.php';

if ( is_readable( $autoload ) ) {
	require_once $autoload;
}

if ( class_exists( \Twig\Environment::class ) && class_exists( \Twig\Loader\ArrayLoader::class ) && class_exists( \Twig\TwigFunction::class ) ) {
	$environment = new \Twig\Environment(
		new \Twig\Loader\ArrayLoader(
			array(
				'fixture' => '{{ bem("example-card", ["featured"]) }}|{{ add_attributes({ class: ["foo"] }) }}',
			)
		),
		array( 'autoescape' => false )
	);

	emulsify_register_smoke_twig_functions( $environment, $helpers );

	emulsify_attribute_helper_assert_same(
		'Twig fixture render',
		'class="example-card example-card--featured"|class="foo"',
		$environment->render( 'fixture' )
	);

	echo "Attribute helper smoke checks passed with Twig fixture rendering.\n";
	exit( 0 );
}

echo "Attribute helper smoke checks passed. Twig fixture rendering skipped because Twig is not installed.\n";
