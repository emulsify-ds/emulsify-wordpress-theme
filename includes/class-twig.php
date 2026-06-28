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

		$namespaces = array_merge(
			array(
				array(
					'namespace' => 'templates',
					'path'      => get_stylesheet_directory() . '/templates',
				),
				array(
					'namespace' => 'templates',
					'path'      => get_template_directory() . '/templates',
				),
				array(
					'namespace' => 'emulsify-tpl',
					'path'      => get_template_directory() . '/templates',
				),
			),
			$this->project_structure_namespaces(),
			array(
				array(
					'namespace' => 'components',
					'path'      => get_stylesheet_directory() . '/src/components',
				),
				array(
					'namespace' => 'components',
					'path'      => get_stylesheet_directory() . '/components',
				),
				array(
					'namespace' => 'components',
					'path'      => get_template_directory() . '/src/components',
				),
				array(
					'namespace' => 'components',
					'path'      => get_template_directory() . '/components',
				),
			)
		);

		/**
		 * Filters Twig namespace paths before they are added to Timber.
		 *
		 * Namespace records should include path and namespace keys. Existing
		 * child-first order is preserved unless a filter intentionally changes it.
		 *
		 * @param array $namespaces Twig namespace path records.
		 * @param mixed $loader     Timber loader instance.
		 */
		$filtered = apply_filters( 'emulsify_theme_twig_namespaces', $namespaces, $loader );

		if ( is_array( $filtered ) ) {
			$namespaces = $filtered;
		}

		foreach ( $namespaces as $namespace ) {
			if ( ! is_array( $namespace ) || empty( $namespace['path'] ) || empty( $namespace['namespace'] ) ) {
				continue;
			}

			$this->add_path( $loader, (string) $namespace['path'], (string) $namespace['namespace'] );
		}

		return $this->with_project_component_loader( $loader );
	}

	/**
	 * Registers Twig functions.
	 *
	 * @param array $functions Existing Timber functions.
	 * @return array Updated Timber functions.
	 */
	public function functions( array $functions ): array {
		$functions['bem'] = array(
			'callable'      => array( $this, 'bem' ),
			'needs_context' => true,
			'is_safe'       => array( 'html' ),
		);

		$functions['add_attributes'] = array(
			'callable'      => array( $this, 'add_attributes' ),
			'needs_context' => true,
			'is_safe'       => array( 'html' ),
		);

		return $functions;
	}

	/**
	 * Builds a BEM class attribute.
	 *
	 * @param mixed ...$arguments Core-style BEM arguments.
	 * @return AttributeBag HTML attributes.
	 */
	public function bem( ...$arguments ): AttributeBag {
		$context = $this->shift_twig_context( $arguments );
		$options = $this->normalize_bem_options(
			$arguments[0] ?? '',
			$arguments[1] ?? array(),
			$arguments[2] ?? '',
			$arguments[3] ?? array(),
			$arguments[4] ?? array()
		);
		$base_class = trim( (string) $options['base_class'] );
		$blockname  = trim( (string) $options['blockname'] );
		$classes    = array();

		if ( '' !== $base_class ) {
			$class_prefix = '' !== $blockname ? $blockname . '__' . $base_class : $base_class;
			$classes[]    = $class_prefix;

			foreach ( $this->normalize_list( $options['modifiers'] ) as $modifier ) {
				$classes[] = $class_prefix . '--' . $modifier;
			}
		}

		$classes = array_merge( $classes, $this->normalize_list( $options['extra'] ) );

		$attribute_bag = new AttributeBag( $options['attributes'] );
		$attribute_bag->addClass( $classes );
		$attribute_bag->merge( $this->attributes_from_context( $context ) );

		return $attribute_bag;
	}

	/**
	 * Builds HTML attributes from an associative array.
	 *
	 * @param mixed ...$arguments Attribute arguments.
	 * @return AttributeBag HTML attributes.
	 */
	public function add_attributes( ...$arguments ): AttributeBag {
		$context        = $this->shift_twig_context( $arguments );
		$attribute_bag  = $this->attributes_from_context( $context );
		$additional     = $arguments[0] ?? array();
		$legacy_context = array();

		if ( $this->is_twig_context( $additional ) && isset( $arguments[1] ) ) {
			$legacy_context = $additional;
			$additional     = $arguments[1];
		}

		if ( ! empty( $legacy_context ) ) {
			$attribute_bag->merge( $this->attributes_from_context( $legacy_context ) );
		}

		$attribute_bag->merge( $additional );

		return $attribute_bag;
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
	 * Gets Twig namespace records declared by project.emulsify.json.
	 *
	 * Emulsify Core already supports variant.structureImplementations for
	 * component-system roots. Honor the same config here so Storybook/Core and
	 * WordPress runtime Twig resolution stay aligned.
	 *
	 * @return array Twig namespace path records.
	 */
	private function project_structure_namespaces(): array {
		$config = $this->project_config();

		if ( empty( $config['variant']['structureImplementations'] ) || ! is_array( $config['variant']['structureImplementations'] ) ) {
			return array();
		}

		$records   = array();
		$theme_dir = rtrim( get_stylesheet_directory(), '/\\' );

		foreach ( $config['variant']['structureImplementations'] as $implementation ) {
			if ( ! is_array( $implementation ) || empty( $implementation['name'] ) || empty( $implementation['directory'] ) ) {
				continue;
			}

			$namespace = ltrim( (string) $implementation['name'], '@' );

			if ( 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $namespace ) ) {
				continue;
			}

			$resolved = $this->resolve_project_path( $theme_dir, (string) $implementation['directory'] );

			if ( '' === $resolved ) {
				continue;
			}

			$records[] = array(
				'namespace' => $namespace,
				'path'      => $resolved,
			);
		}

		return $records;
	}

	/**
	 * Resolves a project config path to a safe child-theme-relative path.
	 *
	 * @param string $theme_dir Theme root.
	 * @param string $path      Project-configured path.
	 * @return string Resolved path, or empty string when invalid.
	 */
	private function resolve_project_path( string $theme_dir, string $path ): string {
		$path = trim( str_replace( '\\', '/', $path ) );

		if (
			'' === $path
			|| false !== strpos( $path, "\0" )
			|| 0 === strpos( $path, '/' )
			|| preg_match( '#(^|/)\.\.(/|$)#', $path )
		) {
			return '';
		}

		return rtrim( $theme_dir, '/\\' ) . '/' . ltrim( $path, '/' );
	}

	/**
	 * Wraps the Timber loader with project machine-name component support.
	 *
	 * @param mixed $loader Timber loader.
	 * @return mixed Updated Timber loader.
	 */
	private function with_project_component_loader( $loader ) {
		if ( ! $this->can_wrap_twig_loader( $loader ) ) {
			return $loader;
		}

		$machine_name = $this->project_machine_name();

		if ( '' === $machine_name ) {
			return $loader;
		}

		$roots = $this->project_component_roots( $machine_name, $loader );

		if ( empty( $roots ) ) {
			return $loader;
		}

		return $this->create_project_component_loader( $machine_name, $roots, $loader );
	}

	/**
	 * Checks whether the current loader can be wrapped safely.
	 *
	 * @param mixed $loader Timber loader.
	 * @return bool TRUE when Twig loader classes are available.
	 */
	private function can_wrap_twig_loader( $loader ): bool {
		return interface_exists( '\Twig\Loader\LoaderInterface' )
			&& class_exists( '\Twig\Source' )
			&& class_exists( '\Twig\Error\LoaderError' )
			&& is_a( $loader, '\Twig\Loader\LoaderInterface' );
	}

	/**
	 * Reads the active child theme project machine name.
	 *
	 * @return string Project machine name, or empty string when unavailable.
	 */
	private function project_machine_name(): string {
		$data = $this->project_config();

		if ( ! is_array( $data ) || empty( $data['project']['machineName'] ) ) {
			return '';
		}

		$machine_name = trim( (string) $data['project']['machineName'] );

		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $machine_name ) ) {
			return '';
		}

		return $machine_name;
	}

	/**
	 * Reads active child theme Emulsify project metadata.
	 *
	 * @return array Project config, or an empty array when unavailable.
	 */
	private function project_config(): array {
		$path = get_stylesheet_directory() . '/project.emulsify.json';

		if ( ! is_readable( $path ) ) {
			return array();
		}

		$contents = file_get_contents( $path );

		if ( false === $contents ) {
			return array();
		}

		$data = json_decode( $contents, true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Gets project component root paths in child-first order.
	 *
	 * @param string $machine_name Active project machine name.
	 * @param mixed  $loader       Timber loader.
	 * @return array Component root paths.
	 */
	private function project_component_roots( string $machine_name, $loader ): array {
		$roots = array(
			array(
				'path'   => get_stylesheet_directory() . '/src/components',
				'source' => 'child',
			),
			array(
				'path'   => get_stylesheet_directory() . '/components',
				'source' => 'child',
			),
			array(
				'path'   => get_template_directory() . '/src/components',
				'source' => 'parent',
			),
			array(
				'path'   => get_template_directory() . '/components',
				'source' => 'parent',
			),
		);

		/**
		 * Filters roots used for machineName:component Twig references.
		 *
		 * Roots can be path strings or arrays with a path key. The default order
		 * mirrors @components: active child paths first, then parent paths.
		 *
		 * @param array  $roots        Project component root records.
		 * @param string $machine_name Active project machine name.
		 * @param mixed  $loader       Timber loader instance.
		 */
		$filtered = apply_filters( 'emulsify_theme_project_component_roots', $roots, $machine_name, $loader );

		if ( is_array( $filtered ) ) {
			$roots = $filtered;
		}

		return $this->normalize_project_component_roots( $roots );
	}

	/**
	 * Normalizes component root records to readable unique paths.
	 *
	 * @param array $roots Component root records.
	 * @return array Component root paths.
	 */
	private function normalize_project_component_roots( array $roots ): array {
		$paths = array();
		$seen  = array();

		foreach ( $roots as $root ) {
			$path = is_array( $root ) ? ( $root['path'] ?? '' ) : $root;

			if ( ! is_scalar( $path ) ) {
				continue;
			}

			$path = rtrim( (string) $path, '/\\' );

			if ( '' === $path || ! is_dir( $path ) || ! is_readable( $path ) ) {
				continue;
			}

			$key = realpath( $path );
			$key = false !== $key ? $key : $path;

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$paths[]      = $path;
		}

		return $paths;
	}

	/**
	 * Creates a Twig loader wrapper for project component references.
	 *
	 * @param string                        $machine_name Active project machine name.
	 * @param array                         $roots        Component root paths.
	 * @param \Twig\Loader\LoaderInterface $loader       Timber loader.
	 * @return \Twig\Loader\LoaderInterface Twig loader wrapper.
	 */
	private function create_project_component_loader( string $machine_name, array $roots, $loader ) {
		return new class( $machine_name, $roots, $loader ) implements \Twig\Loader\LoaderInterface {

			/**
			 * Project machine name.
			 *
			 * @var string
			 */
			private $machine_name;

			/**
			 * Project template prefix.
			 *
			 * @var string
			 */
			private $prefix;

			/**
			 * Component root paths.
			 *
			 * @var array
			 */
			private $roots;

			/**
			 * Wrapped Twig loader.
			 *
			 * @var \Twig\Loader\LoaderInterface
			 */
			private $loader;

			/**
			 * Resolved project template cache.
			 *
			 * @var array
			 */
			private $cache = array();

			/**
			 * Constructs the project component loader wrapper.
			 *
			 * @param string                        $machine_name Active project machine name.
			 * @param array                         $roots        Component root paths.
			 * @param \Twig\Loader\LoaderInterface $loader       Wrapped Twig loader.
			 */
			public function __construct( string $machine_name, array $roots, \Twig\Loader\LoaderInterface $loader ) {
				$this->machine_name = $machine_name;
				$this->prefix       = $machine_name . ':';
				$this->roots        = $roots;
				$this->loader       = $loader;
			}

			/**
			 * Proxies filesystem namespace registration to the wrapped loader.
			 *
			 * @param string $path      Filesystem path.
			 * @param string $namespace Twig namespace.
			 * @return void
			 */
			public function addPath( string $path, string $namespace = '__main__' ): void {
				if ( method_exists( $this->loader, 'addPath' ) ) {
					$this->loader->addPath( $path, $namespace );
				}
			}

			/**
			 * Proxies prepended filesystem namespace registration when available.
			 *
			 * @param string $path      Filesystem path.
			 * @param string $namespace Twig namespace.
			 * @return void
			 */
			public function prependPath( string $path, string $namespace = '__main__' ): void {
				if ( method_exists( $this->loader, 'prependPath' ) ) {
					$this->loader->prependPath( $path, $namespace );
				}
			}

			/**
			 * Proxies filesystem namespace paths when available.
			 *
			 * @param string $namespace Twig namespace.
			 * @return array Namespace paths.
			 */
			public function getPaths( string $namespace = '__main__' ): array {
				if ( method_exists( $this->loader, 'getPaths' ) ) {
					return $this->loader->getPaths( $namespace );
				}

				return array();
			}

			/**
			 * Proxies filesystem namespace names when available.
			 *
			 * @return array Namespace names.
			 */
			public function getNamespaces(): array {
				if ( method_exists( $this->loader, 'getNamespaces' ) ) {
					return $this->loader->getNamespaces();
				}

				return array();
			}

			/**
			 * Returns source for a template logical name.
			 *
			 * @param string $name Template logical name.
			 * @return \Twig\Source Template source.
			 */
			public function getSourceContext( string $name ): \Twig\Source {
				$path = $this->find_project_template( $name );

				if ( null !== $path ) {
					$contents = file_get_contents( $path );

					if ( false === $contents ) {
						throw new \Twig\Error\LoaderError( sprintf( 'Unable to read project component template "%s".', $name ) );
					}

					return new \Twig\Source( $contents, $name, $path );
				}

				if ( $this->is_project_reference( $name ) ) {
					throw new \Twig\Error\LoaderError( $this->project_loader_error( $name ) );
				}

				return $this->loader->getSourceContext( $name );
			}

			/**
			 * Gets the cache key for a template name.
			 *
			 * @param string $name Template logical name.
			 * @return string Cache key.
			 */
			public function getCacheKey( string $name ): string {
				$path = $this->find_project_template( $name );

				if ( null !== $path ) {
					return $path;
				}

				if ( $this->is_project_reference( $name ) ) {
					throw new \Twig\Error\LoaderError( $this->project_loader_error( $name ) );
				}

				return $this->loader->getCacheKey( $name );
			}

			/**
			 * Checks whether a template is fresh.
			 *
			 * @param string $name Template logical name.
			 * @param int    $time Cached template timestamp.
			 * @return bool TRUE when the source is fresh.
			 */
			public function isFresh( string $name, int $time ): bool {
				$path = $this->find_project_template( $name );

				if ( null !== $path ) {
					$modified = filemtime( $path );

					return false !== $modified && $modified <= $time;
				}

				if ( $this->is_project_reference( $name ) ) {
					throw new \Twig\Error\LoaderError( $this->project_loader_error( $name ) );
				}

				return $this->loader->isFresh( $name, $time );
			}

			/**
			 * Checks whether a template exists.
			 *
			 * @param string $name Template logical name.
			 * @return bool TRUE when the template exists.
			 */
			public function exists( string $name ) {
				if ( $this->is_project_reference( $name ) ) {
					return null !== $this->find_project_template( $name );
				}

				return $this->loader->exists( $name );
			}

			/**
			 * Finds a project component template path.
			 *
			 * @param string $name Template logical name.
			 * @return string|null Resolved template path.
			 */
			private function find_project_template( string $name ): ?string {
				if ( array_key_exists( $name, $this->cache ) ) {
					return $this->cache[ $name ];
				}

				$component = $this->project_component_name( $name );

				if ( null === $component ) {
					return null;
				}

				$parts    = explode( '/', $component );
				$filename = end( $parts ) . '.twig';

				foreach ( $this->roots as $root ) {
					foreach ( array( $component . '/' . $filename, $component . '.twig' ) as $relative ) {
						$path = $root . '/' . $relative;

						if ( is_file( $path ) && is_readable( $path ) ) {
							$realpath = realpath( $path );

							$this->cache[ $name ] = false !== $realpath ? $realpath : $path;

							return $this->cache[ $name ];
						}
					}
				}

				return null;
			}

			/**
			 * Gets the safe project component name from a template reference.
			 *
			 * @param string $name Template logical name.
			 * @return string|null Component name.
			 */
			private function project_component_name( string $name ): ?string {
				if ( ! $this->is_project_reference( $name ) ) {
					return null;
				}

				$component = substr( $name, strlen( $this->prefix ) );

				if ( 1 !== preg_match( '/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)?$/', $component ) ) {
					return null;
				}

				return $component;
			}

			/**
			 * Checks whether a template name starts with the project prefix.
			 *
			 * @param string $name Template logical name.
			 * @return bool TRUE when the name targets this project namespace.
			 */
			private function is_project_reference( string $name ): bool {
				return 0 === strncmp( $name, $this->prefix, strlen( $this->prefix ) );
			}

			/**
			 * Gets the loader error for a project reference.
			 *
			 * @param string $name Template logical name.
			 * @return string Error message.
			 */
			private function project_loader_error( string $name ): string {
				$component = substr( $name, strlen( $this->prefix ) );

				if ( '' === $component || false !== strpos( $component, "\0" ) || null === $this->project_component_name( $name ) ) {
					return sprintf( 'Project component template name "%s" is invalid.', $name );
				}

				return sprintf( 'Project component template "%s" is not defined.', $name );
			}
		};
	}

	/**
	 * Normalizes positional and object-style BEM arguments.
	 *
	 * @param mixed $base_class Base class or options object.
	 * @param mixed $modifiers  Modifier values.
	 * @param mixed $blockname  Block name.
	 * @param mixed $extra      Extra classes.
	 * @param mixed $attributes Extra attributes.
	 * @return array Normalized BEM options.
	 */
	private function normalize_bem_options( $base_class, $modifiers, $blockname, $extra, $attributes ): array {
		if ( ! is_array( $base_class ) && ! is_object( $base_class ) ) {
			return array(
				'base_class' => $base_class,
				'modifiers'  => $modifiers,
				'blockname'  => $blockname,
				'extra'      => $extra,
				'attributes' => $attributes,
			);
		}

		$options              = is_array( $base_class ) ? $base_class : get_object_vars( $base_class );
		$has_bem_object_shape = $this->has_non_empty_option( $options, 'block' ) && $this->has_non_empty_option( $options, 'element' );

		return array(
			'base_class' => $this->first_option( $options, array( 'baseClass', 'base_class', 'base' ), $has_bem_object_shape ? $options['element'] : ( $options['block'] ?? '' ) ),
			'modifiers'  => $options['modifiers'] ?? array(),
			'blockname'  => $this->first_option( $options, array( 'blockname', 'blockName' ), $has_bem_object_shape ? $options['block'] : ( $options['element'] ?? '' ) ),
			'extra'      => $options['extra'] ?? array(),
			'attributes' => $options['attributes'] ?? array(),
		);
	}

	/**
	 * Removes a Timber context argument from a Twig callback argument list.
	 *
	 * @param array $arguments Function arguments.
	 * @return array Timber context.
	 */
	private function shift_twig_context( array &$arguments ): array {
		if ( count( $arguments ) > 1 && is_array( $arguments[0] ) ) {
			return array_shift( $arguments );
		}

		return array();
	}

	/**
	 * Checks whether a value looks like a Timber context array.
	 *
	 * @param mixed $value Value to inspect.
	 * @return bool TRUE when the value looks like Timber context.
	 */
	private function is_twig_context( $value ): bool {
		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( array( 'attributes', 'site', 'theme', 'post', 'wp' ) as $key ) {
			if ( array_key_exists( $key, $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds an AttributeBag from Timber context attributes.
	 *
	 * @param array $context Timber context.
	 * @return AttributeBag Context attributes.
	 */
	private function attributes_from_context( array $context ): AttributeBag {
		return new AttributeBag( $context['attributes'] ?? array() );
	}

	/**
	 * Gets the first non-empty option value.
	 *
	 * @param array $options Option map.
	 * @param array $keys    Candidate keys.
	 * @param mixed $default Default value.
	 * @return mixed Option value.
	 */
	private function first_option( array $options, array $keys, $default = null ) {
		foreach ( $keys as $key ) {
			if ( $this->has_non_empty_option( $options, $key ) ) {
				return $options[ $key ];
			}
		}

		return $default;
	}

	/**
	 * Checks whether an option key contains a non-empty value.
	 *
	 * @param array  $options Option map.
	 * @param string $key     Option key.
	 * @return bool TRUE when set and non-empty.
	 */
	private function has_non_empty_option( array $options, string $key ): bool {
		return array_key_exists( $key, $options ) && null !== $options[ $key ] && '' !== $options[ $key ] && false !== $options[ $key ];
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
}
