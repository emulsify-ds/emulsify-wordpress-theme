<?php
/**
 * Initializes Timber when it is available.
 *
 * @package Emulsify
 */

namespace Emulsify\Theme;

/**
 * Timber runtime guard.
 */
final class Timber_Integration {

	/**
	 * Initializes Timber.
	 *
	 * @return bool TRUE when Timber is available and initialized.
	 */
	public function register(): bool {
		if ( ! $this->is_available() ) {
			return false;
		}

		try {
			\Timber\Timber::init();
		} catch ( \Throwable $throwable ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks whether Timber is loaded by Composer or a plugin.
	 *
	 * @return bool TRUE when Timber can be used.
	 */
	private function is_available(): bool {
		return class_exists( '\Timber\Timber' );
	}
}
