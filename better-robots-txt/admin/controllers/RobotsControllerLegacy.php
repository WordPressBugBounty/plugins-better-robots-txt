<?php

namespace Pagup\BetterRobots\Controllers;

use Pagup\BetterRobots\Core\Option;
use Pagup\BetterRobots\Traits\Sitemap;
use Pagup\BetterRobots\Traits\RobotsHelper;
class RobotsControllerLegacy {
    use RobotsHelper, Sitemap;
    protected $yoast_sitemap_url = '';

    protected $xml_sitemap_url = '';

    public function __construct() {
        // Check if physical file option is enabled
        $create_physical = Option::check( 'create_physical_file' ) && Option::get( 'create_physical_file' ) === 'yes';
        // Only add virtual robots.txt if physical file is not enabled and we're public
        if ( get_option( 'blog_public' ) && !$create_physical ) {
            remove_action( 'do_robots', 'do_robots' );
            add_action( 'do_robots', array(&$this, 'robots_txt') );
        }
        if ( !is_array( Option::all() ) ) {
            $this->default_options();
        }
        $this->yoast_sitemap_url = home_url() . '/sitemap_index.xml';
        $this->xml_sitemap_url = home_url() . '/sitemap.xml';
    }

    public function robots_txt() {
        if ( is_robots() ) {
            // Clear any previous output and start clean
            if ( ob_get_level() ) {
                ob_end_clean();
            }
            // Set headers to match WordPress default robots.txt exactly
            status_header( 200 );
            header( 'Content-Type: text/plain; charset=utf-8' );
            nocache_headers();
            // Disable error reporting for this output to prevent any PHP notices/warnings
            $error_reporting = error_reporting();
            error_reporting( 0 );
            // Generate the robots.txt content
            $robots_content = $this->generate_robots_content();
            // Restore error reporting
            error_reporting( $error_reporting );
            // Output the content and exit
            echo $robots_content;
            exit;
        }
    }

    /**
     * Generate robots.txt content
     * 
     * @param array|null $options Optional. If provided, uses these options instead of live options
     * @return string The complete robots.txt content
     */
    public function generate_robots_content( $options = null ) {
        $robots_content = '';
        // Step 4 - Custom rules / Default Rules
        if ( $this->check_option( 'user_agents', $options ) ) {
            $robots_content .= $this->sanitize_robots_content( stripcslashes( $this->get_option( 'user_agents', $options ) ) ) . "\n\n";
        }
        $agents = $this->agents();
        foreach ( $agents as $key => $bot ) {
            if ( $this->check_option( $bot['slug'], $options ) ) {
                if ( $this->get_option( $bot['slug'], $options ) == "allow" ) {
                    $robots_content .= 'User-agent: ' . $bot['agent'] . "\nAllow: " . $bot['path'] . "\n\n";
                } elseif ( $this->get_option( $bot['slug'], $options ) == "disallow" ) {
                    $robots_content .= 'User-agent: ' . $bot['agent'] . "\nDisallow: " . $bot['path'] . "\n\n";
                }
            }
        }
        if ( $this->check_option( 'chinese_bot', $options ) ) {
            $robots_content .= "# Popular chinese search engines\n\n";
            if ( $this->get_option( 'chinese_bot', $options ) == "allow" ) {
                $robots_content .= $this->chinese_bots( "Allow" );
            } elseif ( $this->get_option( 'chinese_bot', $options ) == "disallow" ) {
                $robots_content .= $this->chinese_bots( "Disallow" );
            }
        }
        // Step 2 - Bad Bots - "AI recommended setting" by ChatGPT
        if ( $this->check_option( 'bad_bots_chatgpt', $options ) ) {
            $robots_content .= "# Block Bad Bots. AI recommended setting by ChatGPT\n\n";
            foreach ( $this->bad_bots_chatgpt() as $badbot ) {
                $robots_content .= "User-agent: " . $badbot . "\n" . "Disallow: /\n";
            }
            $robots_content .= "\n";
        }
        // Step 2 - ChatGPT Bot Blocker - Block ChatGPT Bot from scrapping your content
        if ( $this->check_option( 'block_chatgpt_bot', $options ) ) {
            $robots_content .= "# ChatGPT Bot Blocker - Block ChatGPT Bot from scrapping your content\n\n";
            $robots_content .= "User-agent: GPTBot" . "\n" . "Disallow: /\n";
            $robots_content .= "\n";
        }
        // end pro
        // Step 8 - Ads.txt and Appads.txt
        if ( $this->check_option( 'ads-txt', $options ) ) {
            if ( $this->get_option( 'ads-txt', $options ) == "allow" ) {
                $robots_content .= "# Allow/Disallow Ads.txt\n\n";
                $robots_content .= "User-agent: *\nAllow: /ads.txt\n\n";
            } elseif ( $this->get_option( 'ads-txt', $options ) == "disallow" ) {
                $robots_content .= "# Allow/Disallow Ads.txt\n\n";
                $robots_content .= "User-agent: *\nDisallow: /ads.txt\n\n";
            }
        }
        if ( $this->check_option( 'app-ads-txt', $options ) ) {
            if ( $this->get_option( 'app-ads-txt', $options ) == "allow" ) {
                $robots_content .= "# Allow/Disallow App-ads.txt\n\n";
                $robots_content .= "User-agent: *\nAllow: /app-ads.txt\n\n";
            } elseif ( $this->get_option( 'app-ads-txt', $options ) == "disallow" ) {
                $robots_content .= "# Allow/Disallow App-ads.txt\n\n";
                $robots_content .= "User-agent: *\nDisallow: /app-ads.txt\n\n";
            }
        }
        // Post Meta Box
        $post_metas = $this->post_metas();
        if ( !empty( $post_metas ) && is_array( $post_metas ) ) {
            $robots_content .= "# Manual rules with Better Robots.txt Post Meta Box\n\n";
            $robots_content .= "User-agent: *\n";
            foreach ( $post_metas as $meta ) {
                if ( !empty( $meta->meta_value ) ) {
                    $robots_content .= "Disallow: " . $meta->meta_value . "\n";
                }
            }
            $robots_content .= "\n";
        }
        // end pro
        // Step 10 - personalize text for robots.txt
        if ( $this->check_option( 'personalize', $options ) ) {
            $personalize_text = $this->sanitize_robots_content( $this->get_option( 'personalize', $options ) );
            $personalize_text = str_replace( "\n", "\n# ", $personalize_text );
            $robots_content .= "# " . $personalize_text . "\n\n";
        }
        // Step 4 - Crawl-delay for robots.txt
        if ( $this->check_option( 'crawl_delay', $options ) ) {
            $robots_content .= "Crawl-delay: " . $this->get_option( 'crawl_delay', $options ) . "\n\n";
        }
        // Credit
        $robots_content .= "# This robots.txt file was created by Better Robots.txt (Index & Rank Booster by Pagup) Plugin. https://www.better-robots.com/";
        return $this->sanitize_robots_content( $robots_content );
    }

    /**
     * Helper method to get option value from provided options or live options
     * 
     * @param string $key Option key
     * @param array|null $options Optional options array
     * @return mixed Option value
     */
    private function get_option( $key, $options = null ) {
        if ( $options !== null ) {
            return ( isset( $options[$key] ) ? $options[$key] : '' );
        }
        return Option::get( $key );
    }

    /**
     * Helper method to check if option exists and is not empty
     * 
     * @param string $key Option key
     * @param array|null $options Optional options array
     * @return bool True if option exists and is not empty
     */
    private function check_option( $key, $options = null ) {
        if ( $options !== null ) {
            return isset( $options[$key] ) && !empty( $options[$key] );
        }
        return Option::check( $key );
    }

    /**
     * Sanitize robots.txt content to ensure compliance with robots.txt standards
     * Removes non-ASCII characters and ensures proper line endings
     */
    private function sanitize_robots_content( $content ) {
        // Remove non-ASCII characters that could break robots.txt parsing
        $content = preg_replace( '/[^\\x20-\\x7E\\r\\n]/', '', $content );
        // Normalize line endings to \n
        $content = str_replace( ["\r\n", "\r"], "\n", $content );
        // Remove multiple consecutive empty lines (more than 2)
        $content = preg_replace( '/\\n{3,}/', "\n\n", $content );
        // Trim whitespace from each line but preserve structure
        $lines = explode( "\n", $content );
        $lines = array_map( 'rtrim', $lines );
        $content = implode( "\n", $lines );
        return $content;
    }

}
