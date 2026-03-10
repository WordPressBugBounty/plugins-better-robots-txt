<?php
namespace Pagup\BetterRobots\Core;

/**
 * Handles request data sanitization for Better Robots.txt
 */
class Request {
    private const ERROR_PREFIX = 'Better Robots.txt';

    /**
     * Get safe POST value that matches whitelist
     *
     * @param string $val POST key
     * @param array $safe Whitelist of allowed values
     * @return string Sanitized value or empty string
     */
    public static function safe(string $val, array $safe): string {
        try {
            if (isset($_POST[$val]) && in_array($_POST[$val], $safe)) {
                return sanitize_text_field($_POST[$val]);
            }

            return "";
        } catch (\Exception $e) {
            error_log(sprintf("%s Request Error: %s", self::ERROR_PREFIX, $e->getMessage()));
            return "";
        }
    }

    /**
     * Get sanitized text field from POST
     *
     * @param string $key POST key
     * @return string Sanitized value or empty string
     */
    public static function text(string $key): string {
        try {
            if (isset($_POST[$key]) && !empty($_POST[$key])) {
                return sanitize_text_field($_POST[$key]);
            }

            return "";
        } catch (\Exception $e) {
            error_log(sprintf("%s Request Error: %s", self::ERROR_PREFIX, $e->getMessage()));
            return "";
        }
    }

    /**
     * Get sanitized textarea field from POST
     *
     * @param string $key POST key
     * @return string Sanitized value or empty string
     */
    public static function textarea(string $key): string {
        try {
            if (isset($_POST[$key]) && !empty($_POST[$key])) {
                return sanitize_textarea_field($_POST[$key]);
            }

            return "";
        } catch (\Exception $e) {
            error_log(sprintf("%s Request Error: %s", self::ERROR_PREFIX, $e->getMessage()));
            return "";
        }
    }

    /**
     * Get numeric value from POST
     *
     * @param string $key POST key
     * @return string Sanitized numeric value or empty string
     */
    public static function numeric(string $key): string {
        try {
            if (isset($_POST[$key]) && is_numeric($_POST[$key])) {
                return sanitize_text_field($_POST[$key]);
            }

            return "";
        } catch (\Exception $e) {
            error_log(sprintf("%s Request Error: %s", self::ERROR_PREFIX, $e->getMessage()));
            return "";
        }
    }

    /**
     * Check if POST key exists and is not empty
     *
     * @param string $key POST key
     * @return bool True if key exists and not empty
     */
    public static function check(string $key): bool {
        return isset($_POST[$key]) && !empty($_POST[$key]);
    }

    /**
     * Sanitize an array recursively
     *
     * @param array $array Array to sanitize
     * @return array|null Sanitized array or null
     */
    public static function array($array): ?array {
        try {
            if (!is_array($array)) {
                return null;
            }

            foreach ((array) $array as $k => $v) {
                if (is_array($v)) {
                    $array[$k] = static::array($v);
                } else {
                    $array[$k] = sanitize_text_field($v);
                }
            }

            return $array;
        } catch (\Exception $e) {
            error_log(sprintf("%s Request Error: %s", self::ERROR_PREFIX, $e->getMessage()));
            return null;
        }
    }
}
