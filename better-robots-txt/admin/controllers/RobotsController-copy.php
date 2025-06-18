<?php

namespace Pagup\BetterRobots\Controllers;

use Pagup\BetterRobots\Core\Option;
use Pagup\BetterRobots\Traits\Sitemap;
use Pagup\BetterRobots\Traits\RobotsHelper;
class RobotsController {
    use RobotsHelper, Sitemap;
    protected $yoast_sitemap_url = '';

    protected $xml_sitemap_url = '';

    public function __construct() {
        // add default stuff to robots.txt if we're public
        if ( get_option( 'blog_public' ) ) {
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
            // Headers
            status_header( 200 );
            header( 'Content-Type: text/plain; charset=utf-8' );
            nocache_headers();
            // Disable error reporting for this output to prevent any PHP notices/warnings
            $error_reporting = error_reporting();
            error_reporting( 0 );
            // Build the robots.txt content in a variable
            $robots_content = '';
            // Step 4 - Custom rules / Default Rules
            if ( Option::check( 'user_agents' ) ) {
                $robots_content .= stripcslashes( Option::get( 'user_agents' ) ) . "\r\n\r\n";
            }
            $agents = $this->agents();
            foreach ( $agents as $key => $bot ) {
                if ( Option::check( $bot['slug'] ) ) {
                    if ( Option::get( $bot['slug'] ) == "allow" ) {
                        $robots_content .= 'User-agent: ' . $bot['agent'] . "\r\nAllow: " . $bot['path'] . "\r\n\r\n";
                    } elseif ( Option::get( $bot['slug'] ) == "disallow" ) {
                        $robots_content .= 'User-agent: ' . $bot['agent'] . "\r\nDisallow: " . $bot['path'] . "\r\n\r\n";
                    }
                }
            }
            if ( Option::check( 'chinese_bot' ) ) {
                $robots_content .= __( '# Popular chinese search engines', 'better-robots-txt' ) . "\r\n\r\n";
                if ( Option::get( 'chinese_bot' ) == "allow" ) {
                    $robots_content .= $this->chinese_bots( "Allow" );
                } elseif ( Option::get( 'chinese_bot' ) == "disallow" ) {
                    $robots_content .= $this->chinese_bots( "Disallow" );
                }
            }
            // Step 2 - Bad Bots - "AI recommended setting" by ChatGPT
            if ( Option::check( 'bad_bots_chatgpt' ) ) {
                $robots_content .= __( '# Block Bad Bots. "AI recommended setting" by ChatGPT', 'better-robots-txt' ) . "\r\n\r\n";
                foreach ( $this->bad_bots_chatgpt() as $badbot ) {
                    $robots_content .= "User-agent: " . $badbot . "\r\n" . "Disallow: /\r\n";
                }
                $robots_content .= "\r\n";
            }
            // Step 2 - ChatGPT Bot Blocker - Block ChatGPT Bot from scrapping your content
            if ( Option::check( 'block_chatgpt_bot' ) ) {
                $robots_content .= __( '# ChatGPT Bot Blocker - Block ChatGPT Bot from scrapping your content', 'better-robots-txt' ) . "\r\n\r\n";
                $robots_content .= "User-agent: GPTBot" . "\r\n" . "Disallow: /\r\n";
                $robots_content .= "\r\n";
            }
            // end pro
            // Step 8 - Ads.txt and Appads.txt
            if ( Option::check( 'ads-txt' ) ) {
                if ( Option::get( 'ads-txt' ) == "allow" ) {
                    $robots_content .= __( '# Allow/Disallow Ads.txt', 'better-robots-txt' ) . "\r\n\r\n";
                    $robots_content .= "User-agent: *\r\nAllow: /ads.txt\r\n\r\n";
                } elseif ( Option::get( 'ads-txt' ) == "disallow" ) {
                    $robots_content .= __( '# Allow/Disallow Ads.txt', 'better-robots-txt' ) . "\r\n\r\n";
                    $robots_content .= "User-agent: *\r\nDisallow: /ads.txt\r\n\r\n";
                }
            }
            if ( Option::check( 'app-ads-txt' ) ) {
                if ( Option::get( 'app-ads-txt' ) == "allow" ) {
                    $robots_content .= __( '# Allow/Disallow App-ads.txt', 'better-robots-txt' ) . "\r\n\r\n";
                    $robots_content .= "User-agent: *\r\nAllow: /app-ads.txt\r\n\r\n";
                } elseif ( Option::get( 'app-ads-txt' ) == "disallow" ) {
                    $robots_content .= __( '# Allow/Disallow App-ads.txt', 'better-robots-txt' ) . "\r\n\r\n";
                    $robots_content .= "User-agent: *\r\nDisallow: /app-ads.txt\r\n\r\n";
                }
            }
            // Post Meta Box
            $post_metas = $this->post_metas();
            if ( !empty( $post_metas ) && is_array( $post_metas ) ) {
                $robots_content .= __( '# Manual rules with Better Robots.txt Post Meta Box' ) . "\r\n\r\n";
                $robots_content .= "User-agent: *\r\n";
                foreach ( $post_metas as $meta ) {
                    if ( !empty( $meta->meta_value ) ) {
                        $robots_content .= "Disallow: " . $meta->meta_value . "\r\n";
                    }
                }
                $robots_content .= "\r\n";
            }
            // end pro
            // Step 10 - personalize text for robots.txt / corona virus message
            if ( Option::check( 'personalize' ) ) {
                $personalize_text = str_replace( "\r\n", "\r\n# ", Option::get( 'personalize' ) );
                $robots_content .= "# " . $personalize_text . "\r\n\r\n";
            }
            // Step 4 - Crawl-delay for robots.txt
            if ( Option::check( 'crawl_delay' ) ) {
                $robots_content .= "Crawl-delay: " . Option::get( 'crawl_delay' ) . "\r\n\r\n";
            }
            // Credit
            $robots_content .= __( '# This robots.txt file was created by', 'better-robots-txt' ) . ' Better Robots.txt (Index & Rank Booster by Pagup) Plugin. https://www.better-robots.com/';
            // Restore error reporting
            error_reporting( $error_reporting );
            // tell Lighthouse exactly how many bytes to expect
            header( 'Content-Length: ' . strlen( $robots_content ) );
            // Output the content and exit
            echo $robots_content;
            exit;
        }
    }

}

$RobotsController = new RobotsController();