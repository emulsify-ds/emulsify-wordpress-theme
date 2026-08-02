<?php
/**
 * Smoke checks for Emulsify Twig switch tags.
 *
 * @package Emulsify
 */

use Emulsify\Theme\Runtime\Twig as TwigIntegration;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Asserts two rendered values are identical.
 *
 * @param string $label    Assertion label.
 * @param string $expected Expected value.
 * @param string $actual   Actual value.
 * @return void
 */
function emulsify_twig_switch_assert_same( string $label, string $expected, string $actual ): void {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, sprintf( "%s failed.\nExpected: %s\nActual:   %s\n", $label, var_export( $expected, true ), var_export( $actual, true ) ) );
	exit( 1 );
}

$environment = new Environment(
	new ArrayLoader(
		array(
			'multiple' => <<<'TWIG'
{% switch value %}{% case 'alpha' or 'beta' %}matched{% default %}default{% endswitch %}
TWIG,
			'default' => <<<'TWIG'
{% switch value %}{% case 'alpha' %}matched{% default %}default{% endswitch %}
TWIG,
			'break' => <<<'TWIG'
{% switch value %}{% case 'beta' %}first{% case 'beta' %}second{% default %}default{% endswitch %}
TWIG,
			'expression' => <<<'TWIG'
{% switch value %}{% case ('alpha' ~ suffix) or secondary %}expression{% default %}default{% endswitch %}
TWIG,
			'loose' => <<<'TWIG'
{% switch value %}{% case '2' %}numeric{% default %}default{% endswitch %}
TWIG,
		)
	),
	array(
		'autoescape' => false,
		'cache'      => false,
		'use_yield'  => true,
	)
);

$environment = ( new TwigIntegration() )->extensions( $environment );

emulsify_twig_switch_assert_same( 'multiple case values', 'matched', trim( $environment->render( 'multiple', array( 'value' => 'beta' ) ) ) );
emulsify_twig_switch_assert_same( 'default branch', 'default', trim( $environment->render( 'default', array( 'value' => 'gamma' ) ) ) );
emulsify_twig_switch_assert_same( 'automatic case break', 'first', trim( $environment->render( 'break', array( 'value' => 'beta' ) ) ) );
emulsify_twig_switch_assert_same(
	'case expressions',
	'expression',
	trim(
		$environment->render(
			'expression',
			array(
				'secondary' => 'beta',
				'suffix'    => '-card',
				'value'     => 'alpha-card',
			)
		)
	)
);
emulsify_twig_switch_assert_same( 'PHP-style scalar matching', 'numeric', trim( $environment->render( 'loose', array( 'value' => 2 ) ) ) );

echo "Twig switch smoke checks passed.\n";
