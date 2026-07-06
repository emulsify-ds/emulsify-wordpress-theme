<?php
/**
 * Backward-compatible aliases for runtime classes moved into domain namespaces.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme;

$aliases = array(
	Runtime\Setup::class                         => __NAMESPACE__ . '\\Setup',
	Runtime\Assets::class                        => __NAMESPACE__ . '\\Assets',
	Runtime\Context::class                       => __NAMESPACE__ . '\\Context',
	Runtime\Twig::class                          => __NAMESPACE__ . '\\Twig',
	Runtime\TimberIntegration::class             => __NAMESPACE__ . '\\Timber_Integration',
	Runtime\MissingTimber::class                 => __NAMESPACE__ . '\\Missing_Timber',
	Blocks\ComponentLocator::class               => __NAMESPACE__ . '\\Blocks\\Component_Locator',
	Blocks\AcfBlocks::class                      => __NAMESPACE__ . '\\Blocks\\Acf_Blocks',
	Blocks\NativeBlocks::class                   => __NAMESPACE__ . '\\Blocks\\Native_Blocks',
	Blocks\CoreBlockTwigRenderer::class          => __NAMESPACE__ . '\\Core_Block_Twig_Renderer',
	Blocks\Patterns::class                       => __NAMESPACE__ . '\\Patterns',
	Editor\Enhancements::class                   => __NAMESPACE__ . '\\Editor_Enhancements',
	Editor\Policy::class                         => __NAMESPACE__ . '\\Editor_Policy',
	Acf\LocalJson::class                         => __NAMESPACE__ . '\\Acf_Local_JSON',
	Cli\GenerateChildThemeCommand::class         => __NAMESPACE__ . '\\Cli',
	Support\AttributeBag::class                  => __NAMESPACE__ . '\\AttributeBag',
);

foreach ( $aliases as $class => $alias ) {
	if ( ! class_exists( $alias, false ) && class_exists( $class ) ) {
		class_alias( $class, $alias );
	}
}
