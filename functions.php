<?php

declare(strict_types=1);

// Ensure WP-CLI is available before proceeding.
if (!class_exists('WP_CLI')) {
    return;
}

/**
 * Class Emulsify_CLI
 *
 * This class provides a WP-CLI command to generate an Emulsify sub-theme.
 */
class Emulsify_CLI
{
    /**
     * Creates an Emulsify sub-theme.
     *
     * ## OPTIONS
     *
     * <theme_name>
     * : The name of the new theme.
     *
     * ## EXAMPLES
     *
     *     wp emulsify new-theme
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args)
    {
        list($theme_name) = $args;
        $machine_name = $this->convertLabelToMachineName($theme_name);

        // Define source (base theme) and destination (new theme) paths.
        $source = get_theme_root() . '/emulsify/whisk';
        $destination = get_theme_root() . '/' . $machine_name;

        // Validate source directory existence.
        if (!is_dir($source)) {
            WP_CLI::error("The source directory ($source) does not exist.");
        }

        // Prevent overwriting an existing theme.
        if (is_dir($destination)) {
            WP_CLI::error("The destination directory ($destination) already exists.");
        }

        // Recursively copy theme files and directories.
        $this->copyTheme($source, $destination, 'whisk', $machine_name);

        // Replace instances of "whisk" with the new theme name inside file contents.
        $this->renameInstances($destination, 'whisk', $machine_name);

        WP_CLI::success("Theme '$theme_name' has been created successfully.");
    }

    /**
     * Recursively copies a theme while renaming instances of the base theme name in filenames.
     *
     * @param string $src Source directory (original theme).
     * @param string $dst Destination directory (new theme).
     * @param string $search The original theme name to search for.
     * @param string $replace The new theme name to replace with.
     */
    private function copyTheme(string $src, string $dst, string $search, string $replace): void
    {
        $dir = opendir($src);
        @mkdir($dst, 0755, true); // Ensure directory is created with correct permissions.

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $src_file = $src . '/' . $file;
            $dst_file = $dst . '/' . str_replace($search, $replace, $file);

            // If it's a directory, recursively copy its contents.
            if (is_dir($src_file)) {
                $this->copyTheme($src_file, $dst_file, $search, $replace);
            } else {
                copy($src_file, $dst_file);
            }
        }

        closedir($dir);
    }

    /**
     * Recursively replaces all instances of a string inside files within a directory.
     *
     * @param string $dir The directory to search within.
     * @param string $search The string to search for.
     * @param string $replace The string to replace it with.
     */
    private function renameInstances(string $dir, string $search, string $replace): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($files as $file) {
            if ($file->isFile()) {
                $contents = file_get_contents($file->getRealPath());
                $updated_contents = str_replace($search, $replace, $contents);
                file_put_contents($file->getRealPath(), $updated_contents);
            }
        }
    }

    /**
     * Converts a given theme name into a machine-friendly format.
     *
     * @param string $label The human-readable theme name.
     * @return string The machine-friendly version of the theme name.
     */
    private function convertLabelToMachineName(string $label): string
    {
        return strtolower(preg_replace('/[^a-z0-9_]+/ui', '_', $label));
    }
}

// Register the WP-CLI command.
WP_CLI::add_command('emulsify', 'Emulsify_CLI');
