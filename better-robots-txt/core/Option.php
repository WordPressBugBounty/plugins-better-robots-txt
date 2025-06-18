<?php
namespace Pagup\BetterRobots\Core;

class Option
{
    public static function all()
    {
        return get_option( 'robots_txt' );
    }

    /**
     * Get option value from database or provided array
     * 
     * @param string $key Option key
     * @param array|null $options_array Optional array to use instead of database
     * @return mixed Option value or empty string if not found
     */
    public static function get($key, $options_array = null)
    {
        if ($options_array !== null) {
            return isset($options_array[$key]) ? $options_array[$key] : '';
        }
        
        $option = static::all();

        if (isset($option[$key])) {
            return $option[$key];
        }

        return '';
    }

    /**
     * Check if option exists and is not empty
     * 
     * @param string $key Option key
     * @param array|null $options_array Optional array to use instead of database
     * @return bool True if option exists and is not empty
     */
    public static function check($key, $options_array = null)
    {
        if ($options_array !== null) {
            return isset($options_array[$key]) && !empty($options_array[$key]);
        }
        
        $option = static::all();
        return isset($option[$key]) && !empty($option[$key]);
    }

    /**
     * Check if option value equals specific value
     * 
     * @param string $option Option key
     * @param string $val Value to compare
     * @param array|null $options_array Optional array to use instead of database
     * @return bool True if option equals value
     */
    public static function valid($option, $val, $options_array = null)
    {
        return static::check($option, $options_array) && static::get($option, $options_array) == $val;
    }

    public static function post_meta($key)
    {
        global $post;

        if ( isset($post) && !empty( get_post_meta($post->ID, $key, true) ))
        {
            return get_post_meta($post->ID, $key, true);
        }

        return '';
        
    }
}