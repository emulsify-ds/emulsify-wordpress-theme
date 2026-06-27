<?php
/**
 * Registers Twig loader paths and helpers.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme;

/**
 * Twig integration for Timber.
 */
final class Twig {

	/**
	 * Registers Timber Twig hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'timber/loader/loader', array( $this, 'loader_paths' ) );
		add_filter( 'timber/twig/functions', array( $this, 'functions' ) );
	}

	/**
	 * Adds theme paths and namespaces to the Twig loader.
	 *
	 * @param mixed $loader Timber loader.
	 * @return mixed Updated Timber loader.
	 */
	public function loader_paths( $loader ) {
		if ( ! is_object( $loader ) || ! method_exists( $loader, 'addPath' ) ) {
			return $loader;
		}

		$paths = array(
			array( get_stylesheet_directory() . '/templates', 'templates' ),
			array( get_template_directory() . '/templates', 'templates' ),
			array( get_template_directory() . '/templates', 'emulsify-tpl' ),
			array( get_stylesheet_directory() . '/src/components', 'components' ),
			array( get_template_directory() . '/src/components', 'components' ),
			array( get_stylesheet_directory() . '/components', 'components' ),
			array( get_template_directory() . '/components', 'components' ),
		);

		foreach ( $paths as $path ) {
			$this->add_path( $loader, $path[0], $path[1] );
		}

		return $loader;
	}

	/**
	 * Registers Twig functions.
	 *
	 * @param array $functions Existing Timber functions.
	 * @return array Updated Timber functions.
	 */
	public function functions( array $functions ): array {
		$functions['bem'] = array(
			'callable' => array( $this, 'bem' ),
			'is_safe'  => array( 'html' ),
		);

		$functions['add_attributes'] = array(
			'callable' => array( $this, 'add_attributes' ),
			'is_safe'  => array( 'html' ),
		);

		return $functions;
	}

	/**
	 * Builds a BEM class attribute.
	 *
	 * @param mixed        $base_class Base class string or options array.
	 * @param array|string $modifiers  Modifier suffixes.
	 * @param string       $blockname  Optional BEM block name.
	 * @param array|string $extra      Extra classes.
	 * @return string HTML class attribute.
	 */
	public function bem( $base_class, $modifiers = array(), string $blockname = '', $extra = array() ): string {
		if ( is_object( $base_class ) || is_array( $base_class ) ) {
			$options = (array) $base_class;

			if ( isset( $options['block'], $options['element'] ) ) {
				$blockname  = (string) $options['block'];
				$base_class = (string) $options['element'];
			} else {
				$base_class = $options['base_class'] ?? $options['block'] ?? '';
				$blockname  = $options['blockname'] ?? $options['block_name'] ?? '';
			}

			$modifiers = $options['modifiers'] ?? array();
			$extra     = $options['extra_classes'] ?? $options['extra'] ?? array();
		}

		$base_class = trim( (string) $base_class );
		$blockname  = trim( $blockname );

		if ( '' === $base_class ) {
			return '';
		}

		$base      = '' !== $blockname ? $blockname . '__' . $base_class : $base_class;
		$classes   = array( $base );
		$modifiers = $this->normalize_list( $modifiers );
		$extra     = $this->normalize_list( $extra );

		foreach ( $modifiers as $modifier ) {
			$classes[] = $base . '--' . $modifier;
		}

		$classes = array_merge( $classes, $extra );

		return sprintf( 'class="%s"', esc_attr( implode( ' ', array_unique( $classes ) ) ) );
	}

	/**
	 * Builds HTML attributes from an associative array.
	 *
	 * @param mixed $attributes            Attribute array or legacy context argument.
	 * @param mixed $additional_attributes Optional attribute array.
	 * @return string HTML attributes.
	 */
	public function add_attributes( $attributes = array(), $additional_attributes = null ): string {
		if ( is_array( $additional_attributes ) ) {
			$attributes = $additional_attributes;
		}

		if ( ! is_array( $attributes ) ) {
			return '';
		}

		$output = array();

		foreach ( $attributes as $name => $value ) {
			if ( null === $value || false === $value ) {
				continue;
			}

			if ( is_int( $name ) ) {
				if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
					$output[] = trim( (string) $value );
				}
				continue;
			}

			$name = sanitize_key( (string) $name );

			if ( '' === $name ) {
				continue;
			}

			if ( true === $value ) {
				$output[] = esc_attr( $name );
				continue;
			}

			if ( 'class' === $name ) {
				$value = implode( ' ', $this->normalize_list( $this->normalize_class_attribute( $value ) ) );
			} elseif ( is_array( $value ) ) {
				$value = implode( ' ', $this->normalize_list( $value ) );
			}

			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$output[] = sprintf( '%s="%s"', esc_attr( $name ), esc_attr( (string) $value ) );
			}
		}

		return implode( ' ', $output );
	}

	/**
	 * Adds a Twig path when it exists.
	 *
	 * @param mixed  $loader    Timber loader.
	 * @param string $path      Filesystem path.
	 * @param string $namespace Twig namespace.
	 * @return void
	 */
	private function add_path( $loader, string $path, string $namespace ): void {
		if ( is_dir( $path ) && is_readable( $path ) ) {
			$loader->addPath( $path, $namespace );
		}
	}

	/**
	 * Normalizes class-like values into a flat list.
	 *
	 * @param mixed $value Class value.
	 * @return array Class list.
	 */
	private function normalize_list( $value ): array {
		$items = array();

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$items = array_merge( $items, $this->normalize_list( $item ) );
			}
			return $items;
		}

		if ( is_scalar( $value ) ) {
			$value = trim( (string) $value );

			if ( '' !== $value ) {
				$items[] = $value;
			}
		}

		return $items;
	}

	/**
	 * Extracts classes from a class attribute string.
	 *
	 * @param mixed $value Class attribute or class list.
	 * @return mixed Normalized class value.
	 */
	private function normalize_class_attribute( $value ) {
		if ( is_string( $value ) && preg_match( '/class=["\']([^"\']+)["\']/', $value, $matches ) ) {
			return $matches[1];
		}

		return $value;
	}
}
