<?php
/**
 * Parses Emulsify switch tags.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Twig;

use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\Parser;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Parses switch, case, default, and endswitch tags.
 *
 * Based on the Emulsify Tools implementation for Drupal.
 */
final class SwitchTokenParser extends AbstractTokenParser {

	/**
	 * Stops case-value parsing before Twig consumes the "or" delimiter.
	 */
	private const CASE_VALUE_PRECEDENCE = 11;

	/**
	 * Gets the opening tag name.
	 *
	 * @return string Twig tag name.
	 */
	public function getTag(): string {
		return 'switch';
	}

	/**
	 * Parses a switch block.
	 *
	 * @param Token $token Opening switch token.
	 * @return SwitchNode Parsed switch node.
	 * @throws SyntaxError When the switch block contains an unexpected tag.
	 */
	public function parse( Token $token ): SwitchNode {
		$lineno = $token->getLine();
		$parser = $this->parser;
		$stream = $parser->getStream();
		$nodes  = array(
			'value' => $this->parse_expression( $parser ),
		);

		$stream->expect( Token::BLOCK_END_TYPE );

		while ( $stream->getCurrent()->test( Token::TEXT_TYPE ) && '' === trim( $stream->getCurrent()->getValue() ) ) {
			$stream->next();
		}

		$stream->expect( Token::BLOCK_START_TYPE );

		$cases = array();
		$end   = false;

		while ( ! $end ) {
			$next = $stream->next();

			switch ( $next->getValue() ) {
				case 'case':
					$values = array();

					while ( true ) {
						$values[] = $this->parse_expression( $parser, self::CASE_VALUE_PRECEDENCE );

						if ( $stream->test( Token::OPERATOR_TYPE, 'or' ) ) {
							$stream->next();
							continue;
						}

						break;
					}

					$stream->expect( Token::BLOCK_END_TYPE );
					$cases[] = $this->node_collection(
						array(
							'values' => $this->node_collection( $values ),
							'body'   => $parser->subparse( array( $this, 'decide_if_fork' ) ),
						)
					);
					break;

				case 'default':
					$stream->expect( Token::BLOCK_END_TYPE );
					$nodes['default'] = $parser->subparse( array( $this, 'decide_if_end' ) );
					break;

				case 'endswitch':
					$end = true;
					break;

				default:
					$message = sprintf(
						'Unexpected tag "%s". Twig was looking for "case", "default", or "endswitch" to close the "switch" block started on line %d.',
						$next->getValue(),
						$lineno
					);
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Twig syntax errors are not rendered HTML.
					throw new SyntaxError( $message, $lineno );
			}
		}

		$nodes['cases'] = $this->node_collection( $cases );
		$stream->expect( Token::BLOCK_END_TYPE );

		return new SwitchNode( $nodes, array(), $lineno );
	}

	/**
	 * Checks whether the parser reached another switch branch.
	 *
	 * @param Token $token Current Twig token.
	 * @return bool TRUE at another switch branch.
	 */
	public function decide_if_fork( Token $token ): bool {
		return $token->test( array( 'case', 'default', 'endswitch' ) );
	}

	/**
	 * Checks whether the parser reached the end of a switch block.
	 *
	 * @param Token $token Current Twig token.
	 * @return bool TRUE at the closing switch tag.
	 */
	public function decide_if_end( Token $token ): bool {
		return $token->test( array( 'endswitch' ) );
	}

	/**
	 * Parses an expression across the Twig versions allowed by Timber 2.x.
	 *
	 * @param Parser $parser     Active Twig parser.
	 * @param int    $precedence Expression precedence floor.
	 * @return Node Parsed expression node.
	 */
	private function parse_expression( Parser $parser, int $precedence = 0 ): Node {
		if ( version_compare( Environment::VERSION, '3.21.0', '>=' ) ) {
			return $parser->parseExpression( $precedence );
		}

		$expression_parser = $parser->getExpressionParser();

		return $expression_parser->parseExpression( $precedence );
	}

	/**
	 * Builds a node collection across the Twig versions allowed by Timber 2.x.
	 *
	 * @param array $nodes Child nodes.
	 * @return Node Twig node collection.
	 */
	private function node_collection( array $nodes ): Node {
		if ( class_exists( Nodes::class ) ) {
			return new Nodes( $nodes );
		}

		return new Node( $nodes );
	}
}
