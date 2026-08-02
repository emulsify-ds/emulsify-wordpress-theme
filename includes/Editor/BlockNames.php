<?php
/**
 * Normalizes block names used by editor policy services.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme\Editor;

/**
 * Block name normalization helper.
 */
final class BlockNames {

	/**
	 * Normalizes a list of block names.
	 *
	 * @param array $blocks Block name candidates.
	 * @return array Normalized block names.
	 */
	public static function normalize( array $blocks ): array {
		$names = array();

		foreach ( $blocks as $block ) {
			$name = self::normalize_one( $block );

			if ( '' !== $name ) {
				$names[] = $name;
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * Normalizes a block name candidate.
	 *
	 * @param mixed $block Block name candidate.
	 * @return string Block name or an empty string.
	 */
	private static function normalize_one( $block ): string {
		if ( ! is_scalar( $block ) ) {
			return '';
		}

		$name = strtolower( trim( (string) $block ) );

		if ( '' === $name ) {
			return '';
		}

		if ( false === strpos( $name, '/' ) ) {
			$name = 'core/' . $name;
		}

		return preg_match( '/^[a-z0-9-]+\/[a-z0-9-]+$/', $name ) ? $name : '';
	}
}
