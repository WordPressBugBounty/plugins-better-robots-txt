<?php
namespace Pagup\BetterRobots\Core;

/**
 * Handles plugin path and URL utilities for Better Robots.txt
 */
class Plugin {
    private const ERROR_PREFIX = 'Better Robots.txt';

    /**
     * Get plugin URL for a file
     *
     * @param string $filePath File path relative to plugin root
     * @return string Full URL to the file
     */
    public static function url(string $filePath): string {
        try {
            return plugins_url($filePath, ROBOTS_PLUGIN_ROOT . 'better-robots-txt.php');
        } catch (\Exception $e) {
            error_log(sprintf("%s Plugin Error: %s", self::ERROR_PREFIX, $e->getMessage()));
            return '';
        }
    }

    /**
     * Get plugin path for a file
     *
     * @param string $filePath File path relative to plugin root
     * @return string Full filesystem path to the file
     */
    public static function path(string $filePath): string {
        try {
            return ROBOTS_PLUGIN_ROOT . $filePath;
        } catch (\Exception $e) {
            error_log(sprintf("%s Plugin Error: %s", self::ERROR_PREFIX, $e->getMessage()));
            return '';
        }
    }

    /**
     * Load a view file
     *
     * @param string $file View file name (without .view.php extension)
     * @param array $data Data to extract into view scope
     */
    public static function view(string $file, array $data = []): void {
        try {
            extract($data);
            require static::path("admin/views/{$file}.view.php");
        } catch (\Exception $e) {
            error_log(sprintf("%s Plugin Error: %s", self::ERROR_PREFIX, $e->getMessage()));
        }
    }

    /**
     * Debug dump and die
     */
    public static function dd(): void {
        array_map(function($x) {
            var_dump($x);
        }, func_get_args());
        die;
    }
}
