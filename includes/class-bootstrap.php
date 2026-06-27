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

		( new Setup() )->register();
		( new Assets() )->register();
		( new Blocks() )->register();
		( new Cli() )->register();

		$timber = new Timber_Integration();

		if ( $timber->register() ) {
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
		$files = array(
			'class-assets.php',
			'class-blocks.php',
			'class-cli.php',
			'class-context.php',
			'class-missing-timber.php',
			'class-setup.php',
			'class-timber-integration.php',
			'class-twig.php',
		);

		foreach ( $files as $file ) {
			require_once __DIR__ . '/' . $file;
		}
	}
}
