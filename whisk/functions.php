<?php
/**
 * whisk
 * Emulsify subtheme
 * https://github.com/emulsify-ds/emulsify-wordpress-theme/
 */

// Load Composer dependencies.
require_once dirname(__DIR__, 4) . '/vendor/autoload.php';

require_once __DIR__ . '/includes/site.php';

Timber\Timber::init();

// Sets the directories (inside your theme) to find .twig files.
Timber::$dirname = [ 'templates' ];

new whiskSite();
