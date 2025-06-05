<?php
/**
 * Class-emulsify-theme.php
 *
 * Main theme bootstrap file for Timber and Twig integration.
 *
 * @package Emulsify
 */

namespace Emulsify;

use Timber\Site;
use Timber\Timber;
use Twig\Environment;
use Twig\TwigFilter;

/**
 * Class Emulsify
 *
 * Sets up theme supports, context, filters, and functions for Timber.
 */
class Emulsify_Theme extends Site {

	/**
	 * Initializes theme: registers hooks and filters.
	 */
	public function __construct() {
		add_action( 'after_setup_theme', array( $this, 'theme_supports' ) );
		add_filter( 'timber/context', array( $this, 'add_to_context' ) );
		add_filter( 'timber/twig/filters', array( $this, 'add_filters_to_twig' ) );
		add_filter( 'timber/twig/functions', array( $this, 'add_functions_to_twig' ) );
		parent::__construct();
	}

	/**
	 * Adds required theme supports.
	 *
	 * @return void
	 */
	public function theme_supports() {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		// …any other theme supports…
	}

	/**
	 * Adds global context variables to Timber.
	 *
	 * @param array $context Context array.
	 * @return array Modified context.
	 */
	public function add_to_context( $context ) {
		$context['site'] = $this;
		// …any other context vars…
		return $context;
	}

	/**
	 * Registers custom Twig filters.
	 *
	 * @param array $filters Existing Twig filters.
	 * @return array Modified filters.
	 */
	public function add_filters_to_twig( $filters ) {
		$additional = array(
		// Example of a custom filter:
		// 'my_filter' => [
		// 'callable' => [ $this, 'my_filter_callback' ],
		// ],.
		);
		return array_merge( $filters, $additional );
	}

	/**
	 * Registers custom Twig functions.
	 *
	 * @param array $functions Existing Twig functions.
	 * @return array Modified functions.
	 */
	public function add_functions_to_twig( $functions ) {
		$additional = array(
			'bem'            => array(
				'callable'      => array( $this, 'bem' ),
				'needs_context' => true,
				'is_safe'       => array( 'html' ),
			),
			'add_attributes' => array(
				'callable'      => array( $this, 'add_attributes' ),
				'needs_context' => true,
				'is_safe'       => array( 'html' ),
			),
		);
		return array_merge( $functions, $additional );
	}

	/**
	 * BEM helper for Timber/Twig.
	 *
	 * Usage in Twig:
	 *   <div {{ bem(this, 'button', ['primary','large'], 'form', ['extra-class']) }}></div>
	 *
	 * Or via an options array/object:
	 *   {{ bem({
	 *       block:    'button',
	 *       element:  'icon',
	 *       modifiers:['small'],
	 *       extra:    ['js-clickable']
	 *   }) }}
	 *
	 * @param string|array|object $base_class  Either the base class name (string) or an options array/object.
	 * @param array|string        $modifiers   Modifier suffixes.
	 * @param string              $blockname         Optional block name for elements (makes block__element).
	 * @param array|string        $extra       Any extra classes to append.
	 *
	 * @return string                   Safe HTML attribute string, e.g. class="block block--mod extra".
	 */
	public function bem( $base_class, $modifiers = array(), $blockname = '', $extra = array() ) {
		// Allow passing an options array/object as single arg.
		if ( is_object( $base_class ) || ( is_array( $base_class ) && isset( $base_class['block'] ) ) ) {
			$opts       = (array) $base_class;
			$base_class = $opts['block'] ?? '';
			$blockname  = $opts['element'] ?? '';
			$modifiers  = $opts['modifiers'] ?? array();
			$extra      = $opts['extra'] ?? array();
		}

		// 1) Normalize to flat arrays
		$modifiers = is_array( $modifiers ) ? $modifiers : array( $modifiers );
		$extra     = is_array( $extra ) ? $extra : array( $extra );

		// 2) Filter out non‐scalar values so we never get an array element inside $classes
		$modifiers = array_filter(
			$modifiers,
			function ( $m ) {
				return is_scalar( $m ) && '' !== (string) $m;
			}
		);
		$extra     = array_filter(
			$extra,
			function ( $e ) {
				return is_scalar( $e ) && '' !== (string) $e;
			}
		);

		// 3) Build the BEM base and modifier classes
		$classes   = array();
		$base      = $blockname ? "{$blockname}__{$base_class}" : $base_class;
		$classes[] = $base;
		foreach ( $modifiers as $mod ) {
			$classes[] = "{$base}--{$mod}";
		}
		// 4) Any extra non‐BEM classes
		foreach ( $extra as $e ) {
			$classes[] = (string) $e;
		}

		// 5) Return a single, escaped class="…" string
		$class_string = implode( ' ', $classes );
		return sprintf( 'class="%s"', esc_attr( $class_string ) );
	}

	/**
	 * Build a string of HTML attributes from an associative array.
	 *
	 * Usage in Twig:
	 *   <input {{ add_attributes(this, { id: 'email', class: bem(this,'input') }) }}>
	 *
	 * @param array $context               Timber context (unused here).
	 * @param array $additional_attributes ['attr' => 'value' or [ 'a','b' ], 'custom'=>'x=y' ].
	 *
	 * @return string A string like: id="email" class="input input--large" data-foo="bar"
	 */
	public function add_attributes( $context, $additional_attributes = array() ) {
		$attributes = array();

		foreach ( $additional_attributes as $key => $value ) {
			switch ( gettype( $value ) ) {
				case 'array':
					foreach ( $value as $index => $item ) {
						// Handle bem() output.
						if ( $item instanceof Attribute ) {
							// Remove the item.
							unset( $value[ $index ] );
							$value = array_merge( $value, $item->toArray()[ $key ] );
						}
					}
					break;

				default:
					// Set value to an empty string.
					$value = '';
					break;
			}
			// Merge additional attribute values with existing ones.
			if ( $context['attributes']->offsetExists( $key ) ) {
				$existing_attribute = $context['attributes']->offsetGet( $key )->value();
				$value              = array_merge( $existing_attribute, $value );
			}
			$context['attributes']->setAttribute( $key, $value );
		}

		// Set all attributes.
		foreach ( $context['attributes'] as $key => $value ) {
			$attributes->setAttribute( $key, $value );
			// Remove this attribute from context so it doesn't filter down to child
			// elements.
			$context['attributes']->removeAttribute( $key );
		}

		return $attributes;
	}
}

Timber::init();

// Lets get cooking.
new Emulsify_Theme();
