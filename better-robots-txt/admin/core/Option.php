<?php
namespace Pagup\BetterRobots\Core;

use WP_Post;

/**
 * Handles WordPress options management for Better Robots.txt
 */
class Option {
    private const OPTION_NAME = 'robots_txt';
    private const ERROR_PREFIX = 'Better Robots.txt';

    /**
     * Gets all plugin options
     *
     * @return array|false Plugin options or false if not set
     */
    public static function all() {
        return get_option(self::OPTION_NAME);
    }

    /**
     * Get option value from database or provided array
     *
     * @param string $key Option key
     * @param array|null $options_array Optional array to use instead of database
     * @return mixed Option value or empty string if not found
     */
    public static function get(string $key, $options_array = null) {
        try {
            if ($options_array !== null) {
                return isset($options_array[$key]) ? $options_array[$key] : '';
            }

            $option = static::all();

            if (isset($option[$key])) {
                return $option[$key];
            }

            return '';
        } catch (\Exception $e) {
            error_log(sprintf("%s Option Error: %s", self::ERROR_PREFIX, $e->getMessage()));
            return '';
        }
    }

    /**
     * Check if option exists and is not empty
     *
     * @param string $key Option key
     * @param array|null $options_array Optional array to use instead of database
     * @return bool True if option exists and is not empty
     */
    public static function check(string $key, $options_array = null): bool {
        try {
            if ($options_array !== null) {
                return isset($options_array[$key]) && !empty($options_array[$key]);
            }

            $option = static::all();
            return isset($option[$key]) && !empty($option[$key]);
        } catch (\Exception $e) {
            error_log(sprintf("%s Option Error: %s", self::ERROR_PREFIX, $e->getMessage()));
            return false;
        }
    }

    /**
     * Check if option value equals specific value
     *
     * @param string $option Option key
     * @param mixed $val Value to compare
     * @param array|null $options_array Optional array to use instead of database
     * @return bool True if option equals value
     */
    public static function valid(string $option, $val, $options_array = null): bool {
        try {
            return static::check($option, $options_array) && static::get($option, $options_array) == $val;
        } catch (\Exception $e) {
            error_log(sprintf("%s Option Error: %s", self::ERROR_PREFIX, $e->getMessage()));
            return false;
        }
    }

    /**
     * Gets post meta value for current post
     *
     * @param string $key Meta key to retrieve
     * @return mixed Meta value or empty string if not found
     */
    public static function post_meta(string $key) {
        try {
            global $post;

            if (!$post instanceof WP_Post) {
                return '';
            }

            $meta_value = get_post_meta($post->ID, $key, true);
            return !empty($meta_value) ? $meta_value : '';
        } catch (\Exception $e) {
            error_log(sprintf("%s Option Error: %s", self::ERROR_PREFIX, $e->getMessage()));
            return '';
        }
    }

    /**
     * Updates a plugin option
     *
     * @param string $key Option key to update
     * @param mixed $value New value
     * @return bool Success status
     */
    public static function update(string $key, $value): bool {
        try {
            $options = static::all() ?: [];

            if (!is_array($options)) {
                $options = [];
            }

            $options[$key] = $value;
            return update_option(self::OPTION_NAME, $options);
        } catch (\Exception $e) {
            error_log(sprintf("%s Option Error: %s", self::ERROR_PREFIX, $e->getMessage()));
            return false;
        }
    }

    /**
     * Deletes a plugin option
     *
     * @param string $key Option key to delete
     * @return bool Success status
     */
    public static function delete(string $key): bool {
        try {
            $options = static::all();

            if (!is_array($options) || !isset($options[$key])) {
                return false;
            }

            unset($options[$key]);
            return update_option(self::OPTION_NAME, $options);
        } catch (\Exception $e) {
            error_log(sprintf("%s Option Error: %s", self::ERROR_PREFIX, $e->getMessage()));
            return false;
        }
    }

    /**
     * Normalizes option types to ensure they are of the correct data type.
     *
     * @param array $options Array of options to normalize.
     * @return array The normalized array of options.
     */
    public static function normalize_option_types(array $options): array {
        // Ensure boolean types with default values
        $boolean_fields = [
            'remove_settings' => false,
            'hide_robotstxt' => false,
            'create_physical_file' => false,
        ];

        // Set default values for boolean fields if they don't exist
        foreach ($boolean_fields as $field => $default) {
            if (!isset($options[$field])) {
                $options[$field] = $default;
            } else {
                $options[$field] = filter_var($options[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $options;
    }
}
