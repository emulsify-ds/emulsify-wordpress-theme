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
		// Keep load order explicit. Shared value objects and block discovery
		// helpers are required before the services that instantiate them.
		$files = array(
			'class-acf-local-json.php',
			'class-attribute-bag.php',
			'class-assets.php',
			'Blocks/class-component-locator.php',
			'Blocks/class-acf-blocks.php',
			'Blocks/class-native-blocks.php',
			'Blocks/class-registry.php',
			'class-cli.php',
			'class-context.php',
			'class-core-block-twig-renderer.php',
			'class-editor-enhancements.php',
			'class-editor-policy.php',
			'class-missing-timber.php',
			'class-patterns.php',
			'class-setup.php',
			'class-timber-integration.php',
			'class-twig.php',
		);

		foreach ( $files as $file ) {
			require_once __DIR__ . '/' . $file;
		}
	}
}
