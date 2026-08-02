<?php
/**
 * Resolves project machine-name component references.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Twig;

/**
 * Wraps a Twig loader with project machine-name component support.
 */
final class ProjectComponentLoader implements \Twig\Loader\LoaderInterface {

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
	 * @param string                       $machine_name Active project machine name.
	 * @param array                        $roots        Component root paths.
	 * @param \Twig\Loader\LoaderInterface $loader       Wrapped Twig loader.
	 */
	public function __construct( string $machine_name, array $roots, \Twig\Loader\LoaderInterface $loader ) {
		$this->prefix = $machine_name . ':';
		$this->roots  = $roots;
		$this->loader = $loader;
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
	 * @throws \Twig\Error\LoaderError When project template resolution fails.
	 */
	public function getSourceContext( string $name ): \Twig\Source {
		$path = $this->find_project_template( $name );

		if ( null !== $path ) {
			$contents = file_get_contents( $path );

			if ( false === $contents ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered HTML.
				throw new \Twig\Error\LoaderError( sprintf( 'Unable to read project component template "%s".', $name ) );
			}

			return new \Twig\Source( $contents, $name, $path );
		}

		if ( $this->is_project_reference( $name ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered HTML.
			throw new \Twig\Error\LoaderError( $this->project_loader_error( $name ) );
		}

		return $this->loader->getSourceContext( $name );
	}

	/**
	 * Gets the cache key for a template name.
	 *
	 * @param string $name Template logical name.
	 * @return string Cache key.
	 * @throws \Twig\Error\LoaderError When project template resolution fails.
	 */
	public function getCacheKey( string $name ): string {
		$path = $this->find_project_template( $name );

		if ( null !== $path ) {
			return $path;
		}

		if ( $this->is_project_reference( $name ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered HTML.
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
	 * @throws \Twig\Error\LoaderError When project template resolution fails.
	 */
	public function isFresh( string $name, int $time ): bool {
		$path = $this->find_project_template( $name );

		if ( null !== $path ) {
			$modified = filemtime( $path );

			return false !== $modified && $modified <= $time;
		}

		if ( $this->is_project_reference( $name ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered HTML.
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
			// Support the single-directory component convention first, then the
			// older flat component.twig form. Roots are already child-first.
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
			// Keep project component references intentionally shallow:
			// component-name or group/component-name. Legacy deep paths are
			// still available through explicit @namespace configuration.
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
}
