<?php
/**
 * Registers Emulsify switch tags with Twig.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Twig;

use Twig\Extension\AbstractExtension;

/**
 * Adds the switch token parser to Twig.
 */
final class SwitchExtension extends AbstractExtension {

	/**
	 * Gets the extension token parsers.
	 *
	 * @return array Twig token parsers.
	 */
	public function getTokenParsers(): array {
		return array(
			new SwitchTokenParser(),
		);
	}
}
