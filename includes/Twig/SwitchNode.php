<?php
/**
 * Compiles Emulsify switch tags.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Twig;

use Twig\Compiler;
use Twig\Node\Node;

/**
 * Compiles switch nodes to native PHP switch statements.
 */
#[\Twig\Attribute\YieldReady]
final class SwitchNode extends Node {

	/**
	 * Compiles the switch and its branches.
	 *
	 * @param Compiler $compiler Twig compiler.
	 * @return void
	 */
	public function compile( Compiler $compiler ): void {
		$compiler
			->addDebugInfo( $this )
			->write( 'switch (' )
			->subcompile( $this->getNode( 'value' ) )
			->raw( ") {\n" )
			->indent();

		foreach ( $this->getNode( 'cases' ) as $case ) {
			if ( ! $case->hasNode( 'body' ) ) {
				continue;
			}

			foreach ( $case->getNode( 'values' ) as $value ) {
				$compiler
					->write( 'case ' )
					->subcompile( $value )
					->raw( ":\n" );
			}

			$compiler
				->write( "{\n" )
				->indent()
				->subcompile( $case->getNode( 'body' ) )
				->write( "break;\n" )
				->outdent()
				->write( "}\n" );
		}

		if ( $this->hasNode( 'default' ) ) {
			$compiler
				->write( "default:\n" )
				->write( "{\n" )
				->indent()
				->subcompile( $this->getNode( 'default' ) )
				->outdent()
				->write( "}\n" );
		}

		$compiler
			->outdent()
			->write( "}\n" );
	}
}
