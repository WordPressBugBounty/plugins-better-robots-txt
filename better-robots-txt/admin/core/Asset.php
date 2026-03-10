<?php
namespace Pagup\BetterRobots\Core;

use Pagup\BetterRobots\Core\Plugin;

/**
 * Handles asset enqueueing for Better Robots.txt
 */
class Asset {
    private const ERROR_PREFIX = 'Better Robots.txt';

    /**
     * Enqueue a stylesheet
     *
     * @param string $name Asset handle
     * @param string $file File path relative to plugin root
     */
    public static function style(string $name, string $file): void {
        try {
            wp_register_style(
                $name,
                Plugin::url($file),
                [],
                filemtime(Plugin::path($file))
            );

            wp_enqueue_style($name);
        } catch (\Exception $e) {
            error_log(sprintf("%s Asset Error: %s", self::ERROR_PREFIX, $e->getMessage()));
        }
    }

    /**
     * Enqueue a remote stylesheet
     *
     * @param string $name Asset handle
     * @param string $file Remote URL
     */
    public static function style_remote(string $name, string $file): void {
        try {
            wp_register_style($name, $file);
            wp_enqueue_style($name);
        } catch (\Exception $e) {
            error_log(sprintf("%s Asset Error: %s", self::ERROR_PREFIX, $e->getMessage()));
        }
    }

    /**
     * Enqueue a script
     *
     * @param string $name Asset handle
     * @param string $file File path relative to plugin root
     * @param array $array Dependencies
     * @param bool $footer Load in footer
     */
    public static function script(string $name, string $file, array $array = [], bool $footer = false): void {
        try {
            wp_register_script(
                $name,
                Plugin::url($file),
                $array,
                filemtime(Plugin::path($file)),
                $footer
            );

            wp_enqueue_script($name);
        } catch (\Exception $e) {
            error_log(sprintf("%s Asset Error: %s", self::ERROR_PREFIX, $e->getMessage()));
        }
    }

    /**
     * Enqueue a remote script
     *
     * @param string $name Asset handle
     * @param string $file Remote URL
     * @param array $array Dependencies
     * @param mixed $ver Version or false
     * @param bool $footer Load in footer
     */
    public static function script_remote(string $name, string $file, array $array = [], $ver = false, bool $footer = false): void {
        try {
            wp_register_script($name, $file, $array, $ver, $footer);
            wp_enqueue_script($name);
        } catch (\Exception $e) {
            error_log(sprintf("%s Asset Error: %s", self::ERROR_PREFIX, $e->getMessage()));
        }
    }
}
