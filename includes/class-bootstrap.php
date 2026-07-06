<?php
/**
 * Loads and starts the Emulsify theme runtime.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme;

/**
 * Coordinates theme services.
 */
final class Bootstrap {

	/**
	 * Absolute path to the parent theme directory.
	 *
	 * @var string
	 */
	private $theme_dir;

	/**
	 * Starts the theme runtime.
	 *
	 * @param string $theme_dir Absolute path to the parent theme directory.
	 * @return void
	 */
	public static function init( string $theme_dir ): void {
		$bootstrap = new self( $theme_dir );
		$bootstrap->register();
	}

	/**
	 * Constructor.
	 *
	 * @param string $theme_dir Absolute path to the parent theme directory.
	 */
	private function __construct( string $theme_dir ) {
		$this->theme_dir = rtrim( $theme_dir, '/\\' );
	}

	/**
	 * Registers all theme services.
	 *
	 * @return void
	 */
	private function register(): void {
		$this->load_vendor_autoload();
		$this->load_classes();

		// These services use WordPress APIs directly and must stay available even
		// when Timber is missing. The Missing_Timber service owns frontend failure
		// handling later in this method.
		( new Setup() )->register();
		( new Assets() )->register();
		( new Acf_Local_JSON() )->register();
		( new Core_Block_Twig_Renderer() )->register();
		( new Editor_Enhancements() )->register();
		( new Editor_Policy() )->register();
		( new Patterns() )->register();
		( new Blocks\Registry() )->register();
		( new Cli() )->register();

		$timber = new Timber_Integration();

		if ( $timber->register() ) {
			// Timber-dependent services are registered only after Timber has
			// initialized, which keeps admin, CLI, and non-template requests usable
			// in partially installed environments.
			( new Context() )->register();
			( new Twig() )->register();
			return;
		}

		( new Missing_Timber() )->register();
	}

	/**
	 * Loads Composer dependencies when the theme was installed with Composer.
	 *
	 * @return void
	 */
	private function load_vendor_autoload(): void {
		$autoload = $this->theme_dir . '/vendor/autoload.php';

		if ( is_readable( $autoload ) ) {
			require_once $autoload;
		}
	}

	/**
	 * Loads theme service classes.
	 *
	 * @return void
	 */
	private function load_classes(): void {
		spl_autoload_register(
			function ( string $class ): void {
				$this->autoload_runtime_class( $class );
			}
		);
	}

	/**
	 * Loads runtime classes when Composer autoloading is unavailable.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	private function autoload_runtime_class( string $class ): void {
		$file = $this->runtime_class_file( $class );

		if ( null !== $file && is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Resolves runtime class files for PSR-4 paths and legacy class-* filenames.
	 *
	 * @param string $class Fully qualified class name.
	 * @return string|null Runtime class file path, or null for another namespace.
	 */
	private function runtime_class_file( string $class ): ?string {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return null;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$relative_path  = str_replace( '\\', '/', $relative_class );
		$psr4_file      = $this->theme_dir . '/includes/' . $relative_path . '.php';

		if ( is_readable( $psr4_file ) ) {
			return $psr4_file;
		}

		$parts      = explode( '/', $relative_path );
		$class_name = array_pop( $parts );
		$directory  = empty( $parts ) ? '' : implode( '/', $parts ) . '/';

		return $this->theme_dir . '/includes/' . $directory . 'class-' . $this->class_file_slug( $class_name ) . '.php';
	}

	/**
	 * Converts a runtime class name to the legacy class-* file slug.
	 *
	 * @param string $class_name Short class name.
	 * @return string File slug.
	 */
	private function class_file_slug( string $class_name ): string {
		$slug = str_replace( '_', '-', $class_name );
		$slug = preg_replace( '/(?<=[a-z0-9])([A-Z])/', '-$1', $slug );

		return strtolower( (string) $slug );
	}
}
