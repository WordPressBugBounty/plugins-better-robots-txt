<?php

/**
 * Robots.txt Controller (V3)
 *
 * Generates robots.txt content based on Master Plan V3 settings structure.
 *
 * @package Pagup\BetterRobots
 * @since 3.0.0
 */
namespace Pagup\BetterRobots\Controllers;

use Pagup\BetterRobots\Core\Option;
use Pagup\BetterRobots\Config\RobotsConfig;
class RobotsController {
    /**
     * Constructor - hooks into WordPress
     */
    public function __construct() {
        // Get v3 settings
        $settings = Option::all();
        $isPremium = false;
        $isPro = false;
        // Check if physical file option is enabled
        $create_physical = false;
        if ( $isPro && isset( $settings['mode_0']['global_settings']['robots_txt_type'] ) ) {
            $create_physical = $settings['mode_0']['global_settings']['robots_txt_type'] === 'physical';
        }
        // Only add virtual robots.txt if physical file is not enabled and site is public
        if ( get_option( 'blog_public' ) && !$create_physical ) {
            remove_action( 'do_robots', 'do_robots' );
            add_action( 'do_robots', [$this, 'robots_txt'] );
        }
        // Handle llms.txt virtual file
        add_action( 'template_redirect', [$this, 'serve_llms_txt'] );
        // Add SSA header links
        add_action( 'wp_head', [$this, 'add_ssa_header_links'] );
    }

    /**
     * Output robots.txt content
     */
    public function robots_txt() {
        if ( !is_robots() ) {
            return;
        }
        if ( ob_get_level() ) {
            ob_end_clean();
        }
        status_header( 200 );
        header( 'Content-Type: text/plain; charset=utf-8' );
        nocache_headers();
        // $error_reporting = error_reporting();
        // error_reporting(0);
        echo $this->generate();
        // error_reporting($error_reporting);
        exit;
    }

    /**
     * Generate robots.txt content from v3 settings
     */
    public function generate( $options = null ) {
        $settings = $options ?? Option::all();
        if ( !isset( $settings['mode_0'] ) ) {
            return $this->getDefaultRobotsTxt();
        }
        $mode0 = $settings['mode_0'];
        // Default to free-plan behavior in the free build. Premium code restores
        // the paid tiers from a premium-only block during deployment.
        $isPremium = false;
        $isPro = false;
        // Strip Premium-only features if not Premium
        if ( !$isPremium ) {
            // Step 5: Archive Control
            if ( isset( $mode0['archive_module']['policy'] ) && $mode0['archive_module']['policy'] === 'block' ) {
                $mode0['archive_module']['policy'] = 'allow';
            }
            // Step 2: AI Module (Block All Training Bots)
            if ( isset( $mode0['ai_module']['block_all_training_bots'] ) ) {
                $mode0['ai_module']['block_all_training_bots'] = false;
            }
        }
        // Strip Pro features if not Pro (which means neither Pro nor Premium)
        if ( !$isPro ) {
            // Step 1: Search Engine Visibility (Pro: extended, custom)
            if ( isset( $mode0['search_engine_module']['preset_level'] ) && in_array( $mode0['search_engine_module']['preset_level'], ['extended', 'custom'] ) ) {
                $mode0['search_engine_module']['preset_level'] = 'recommended';
            }
            if ( isset( $mode0['global_settings']['sitemaps']['auto_detect_sitemap'] ) ) {
                $mode0['global_settings']['sitemaps']['auto_detect_sitemap'] = false;
            }
            // Step 2: AI Module (Content Signals, Custom Crawlers)
            if ( isset( $mode0['ai_module']['ai_search_policy'] ) ) {
                $mode0['ai_module']['ai_search_policy'] = 'block_all';
            }
            if ( isset( $mode0['ai_module']['content_signals']['enabled'] ) ) {
                $mode0['ai_module']['content_signals']['enabled'] = false;
            }
            if ( isset( $mode0['ai_module']['custom_ai_crawlers'] ) ) {
                $mode0['ai_module']['custom_ai_crawlers'] = '';
            }
            // Step 3: SEO Tools
            if ( isset( $mode0['seo_tools_module']['block_basic_tools'] ) ) {
                $mode0['seo_tools_module']['block_basic_tools'] = false;
            }
            if ( isset( $mode0['seo_tools_module']['block_extra_tools'] ) ) {
                $mode0['seo_tools_module']['block_extra_tools'] = false;
            }
            if ( isset( $mode0['seo_tools_module']['custom_bots'] ) ) {
                $mode0['seo_tools_module']['custom_bots'] = [];
            }
            // Step 4: Bad Bots
            if ( isset( $mode0['bad_bots_module']['use_full_list'] ) ) {
                $mode0['bad_bots_module']['use_full_list'] = false;
            }
            // Step 7: Spam Firewall / Crawl Traps
            if ( isset( $mode0['spam_feeds_module']['block_feeds_spam'] ) ) {
                $mode0['spam_feeds_module']['block_feeds_spam'] = false;
            }
            // Step 8: E-commerce
            if ( isset( $mode0['ecommerce_module']['cleanup_level'] ) && $mode0['ecommerce_module']['cleanup_level'] === 'advanced_cleanup' ) {
                $mode0['ecommerce_module']['cleanup_level'] = 'basic_cleanup';
            }
            // Step 12: LLMS.txt
            if ( isset( $mode0['ai_files_module']['llms_txt_enabled'] ) ) {
                $mode0['ai_files_module']['llms_txt_enabled'] = false;
            }
            // Step 13: Advanced Settings (Crawl Delay)
            if ( isset( $mode0['advanced_settings']['crawl_delay'] ) ) {
                $mode0['advanced_settings']['crawl_delay'] = 0;
            }
            if ( isset( $mode0['advanced_settings']['ensure_search_engine_visibility'] ) ) {
                $mode0['advanced_settings']['ensure_search_engine_visibility'] = false;
            }
        }
        $output = '';
        // Generate rules in the order defined by config
        foreach ( RobotsConfig::STEP_ORDER as $step ) {
            $method = "generate_{$step}";
            if ( method_exists( $this, $method ) ) {
                $stepSettings = $mode0[$step] ?? [];
                $output .= $this->{$method}( $stepSettings );
            }
        }
        // Add footer signature as a separate section.
        $enabled = $mode0['global_settings']['footer_signature']['enabled'] ?? true;
        $customFooterText = $mode0['global_settings']['footer_signature']['custom_text'] ?? '';
        $signature = RobotsConfig::getSignature( !$enabled, $customFooterText );
        if ( $signature !== '' ) {
            $output = rtrim( $output, "\n" ) . "\n\n" . ltrim( $signature, "\n" );
        }
        // Consolidate User-agents if enabled
        if ( isset( $mode0['advanced_settings']['consolidate_user_agents'] ) && $mode0['advanced_settings']['consolidate_user_agents'] ) {
            $output = $this->consolidateOutput( $output );
        }
        // Add SSA signature (Always enabled per requirements)
        // Use defined() check to prevent fatal errors if opcode cache is stale
        if ( defined( RobotsConfig::class . '::SSA_SIGNATURE' ) ) {
            $output = rtrim( $output, "\n" ) . "\n\n" . ltrim( RobotsConfig::SSA_SIGNATURE, "\n" );
        }
        return $this->sanitize( $output );
    }

    /**
     * Step 1: Global Settings (Sitemaps)
     */
    private function generate_global_settings( $settings ) {
        $output = '';
        $sitemaps = $settings['sitemaps'] ?? [];
        // Manual sitemap URL
        if ( !empty( $sitemaps['manual_sitemap_url'] ) ) {
            $output .= "Sitemap: {$sitemaps['manual_sitemap_url']}\n";
        }
        // Auto-detect sitemaps
        if ( $sitemaps['auto_detect_sitemap'] ?? false ) {
            foreach ( $this->detectSitemaps() as $url ) {
                $output .= "Sitemap: {$url}\n";
            }
        }
        if ( !empty( $output ) ) {
            $output .= "\n";
            // Add Global Base Rules immediately after sitemaps
            $output .= RobotsConfig::generateBaseRules( $settings['core_rules'] ?? [] );
        } else {
            // Even if no sitemaps, we want base rules
            $output .= RobotsConfig::generateBaseRules( $settings['core_rules'] ?? [] );
        }
        return $output;
    }

    /**
     * Step 2: Search Engine Visibility
     */
    private function generate_search_engine_module( $settings ) {
        $level = $settings['preset_level'] ?? '';
        $customBots = $settings['custom_bots'] ?? [];
        if ( empty( $level ) && empty( $customBots ) ) {
            return '';
        }
        $allowBots = [];
        $disallowBots = [];
        // Custom mode starts with an empty base list. Other modes inherit the preset.
        if ( !empty( $level ) && $level !== 'custom' ) {
            $configKey = $level . '_visibility';
            // e.g. 'minimal_visibility'
            foreach ( RobotsConfig::getVisibilityBots( $configKey ) as $bot ) {
                $allowBots[$bot] = $bot;
            }
        }
        $output = '';
        if ( !empty( $allowBots ) ) {
            $output .= RobotsConfig::generateAllowRules( array_values( $allowBots ), 'Search Engine Visibility' );
        }
        if ( !empty( $disallowBots ) ) {
            $output .= RobotsConfig::generateDisallowRules( array_values( $disallowBots ), 'Blocked Search Engine Overrides' );
        }
        return $output;
    }

    /**
     * Step 3: AI & LLM Governance
     */
    private function generate_ai_module( $settings ) {
        $output = '';
        $allowBots = [];
        $disallowBots = [];
        // Block AI training bots
        if ( $settings['ai_training_protection'] ?? true ) {
            foreach ( RobotsConfig::AI_TRAINING_BOTS as $bot ) {
                $disallowBots[$bot] = $bot;
            }
        }
        // AI search policy
        if ( ($settings['ai_search_policy'] ?? '') === 'block_all' ) {
            foreach ( RobotsConfig::AI_SEARCH_BOTS as $bot ) {
                $disallowBots[$bot] = $bot;
            }
        }
        if ( !empty( $disallowBots ) ) {
            $output .= RobotsConfig::generateDisallowRules( array_values( $disallowBots ), 'AI Bot Restrictions' );
        }
        if ( !empty( $allowBots ) ) {
            $output .= RobotsConfig::generateAllowRules( array_values( $allowBots ), 'AI Bot Allow Overrides' );
        }
        return $output;
    }

    /**
     * Step 4: SEO Tool Protection
     */
    private function generate_seo_tools_module( $settings ) {
        $output = '';
        $allowBots = [];
        $disallowBots = [];
        if ( !empty( $disallowBots ) ) {
            $output .= RobotsConfig::generateDisallowRules( array_values( $disallowBots ), 'SEO Tool Restrictions' );
        }
        if ( !empty( $allowBots ) ) {
            $output .= RobotsConfig::generateAllowRules( array_values( $allowBots ), 'SEO Tool Allow Overrides' );
        }
        return $output;
    }

    /**
     * Step 5: Bot & Scraper Protection
     */
    private function generate_bad_bots_module( $settings ) {
        if ( empty( $settings['enabled'] ) ) {
            return '';
        }
        $bots = RobotsConfig::MASTER_PLAN_BAD_BOTS_BASIC;
        $comment = 'Bot & Scraper Protection (Basic List)';
        return RobotsConfig::generateDisallowRules( $bots, $comment );
    }

    /**
     * Step 6: Archive & Wayback Control
     */
    private function generate_archive_module( $settings ) {
        if ( ($settings['policy'] ?? '') !== 'block' ) {
            return '';
        }
        return '';
    }

    /**
     * Step 7: Spam & Feed Protection
     */
    private function generate_spam_feeds_module( $settings ) {
        $output = '';
        if ( $settings['block_author_archives'] ?? false ) {
            $output .= RobotsConfig::generatePathDisallowRules( RobotsConfig::AUTHOR_PATHS, 'Block Author Archives' );
        }
        if ( $settings['block_comment_spam'] ?? false ) {
            $output .= "Disallow: *?replytocom=\n";
            $output .= "Disallow: *?replytocom\n\n";
        }
        return $output;
    }

    /**
     * Step 8: E-commerce Optimization
     */
    private function generate_ecommerce_module( $settings ) {
        if ( !class_exists( 'WooCommerce' ) ) {
            return '';
        }
        $level = $settings['cleanup_level'] ?? '';
        if ( empty( $level ) ) {
            return '';
        }
        $output = '';
        if ( $level === 'basic_cleanup' || $level === 'advanced_cleanup' ) {
            $output .= RobotsConfig::generatePathDisallowRules( RobotsConfig::ECOMMERCE_BASIC_PATHS, 'E-commerce Basic Cleanup' );
        }
        return $output;
    }

    /**
     * Step 9: Resources & Assets
     */
    private function generate_resources_module( $settings ) {
        $output = '';
        // Allow CSS & JS (Default: true)
        if ( $settings['allow_css_js'] ?? true ) {
            $output .= RobotsConfig::generatePathAllowRules( RobotsConfig::RESOURCE_CSS_JS, 'Allow CSS/JS' );
        }
        // Allow Images (Default: true)
        if ( $settings['allow_images'] ?? true ) {
            $output .= RobotsConfig::generatePathAllowRules( RobotsConfig::RESOURCE_IMAGES, 'Allow Images' );
        }
        return $output;
    }

    /**
     * Step 10: Social Media Module
     */
    private function generate_social_media_module( $settings ) {
        // Block Social Media (Default: false)
        if ( empty( $settings['enabled'] ) ) {
            return '';
        }
        return RobotsConfig::generateDisallowRules( RobotsConfig::SOCIAL_MEDIA_BOTS, 'Block Social Media Crawlers' );
    }

    /**
     * Crawl Traps (Backend for Step 7 UI — SpamFirewallModule)
     */
    private function generate_crawl_traps_module( $settings ) {
        $output = '';
        // Block WordPress Search URLs
        if ( $settings['block_search'] ?? false ) {
            $output .= RobotsConfig::generatePathDisallowRules( RobotsConfig::SEARCH_PAGINATION_PATHS, 'Block Search & Pagination' );
        }
        // Block Common Trap Parameters
        if ( $settings['block_trap_params'] ?? false ) {
            $output .= "# Block common crawl trap parameters\n";
            $output .= "User-agent: *\n";
            $output .= "Disallow: /*?p=*\n";
            $output .= "Disallow: /*&p=*\n";
            $output .= "Disallow: /*?preview=*\n\n";
        }
        return $output;
    }

    /**
     * Step 11: Ads & Revenue
     */
    private function generate_ads_module( $settings ) {
        $output = '';
        // Allow ads.txt (Default: true)
        if ( $settings['allow_ads_txt'] ?? true ) {
            $output .= "User-agent: *\nAllow: /ads.txt\n\n";
        }
        // Allow app-ads.txt (Default: true)
        if ( $settings['allow_app_ads_txt'] ?? true ) {
            $output .= "User-agent: *\nAllow: /app-ads.txt\n\n";
        }
        return $output;
    }

    /**
     * Step 12: Advanced Settings
     */
    private function generate_advanced_settings( $settings ) {
        $output = '';
        // Custom Rules
        if ( !empty( $settings['custom_rules'] ) ) {
            $customRules = $this->sanitizeCustomRules( $settings['custom_rules'] );
            if ( $customRules !== '' ) {
                $output .= "# Custom Rules\n";
                $output .= $customRules . "\n\n";
            }
        }
        return $output;
    }

    /**
     * Detect available sitemaps
     */
    private function detectSitemaps() {
        if ( class_exists( 'WPSEO_Sitemaps' ) ) {
            return [home_url( '/sitemap_index.xml' )];
        }
        if ( class_exists( 'RankMath' ) ) {
            return [home_url( '/sitemap_index.xml' )];
        }
        if ( defined( 'AIOSEO_VERSION' ) ) {
            return [home_url( '/sitemap.xml' )];
        }
        if ( function_exists( 'wp_sitemaps_get_server' ) ) {
            return [home_url( '/wp-sitemap.xml' )];
        }
        $url = home_url( '/sitemap.xml' );
        $response = wp_remote_head( $url, [
            'timeout' => 3,
        ] );
        if ( !is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            return [$url];
        }
        return [];
    }

    private function sanitize( $content ) {
        $content = preg_replace( '/[^\\x20-\\x7E\\r\\n]/', '', $content );
        $content = str_replace( ["\r\n", "\r"], "\n", $content );
        $content = preg_replace( '/\\n{3,}/', "\n\n", $content );
        $lines = array_map( 'rtrim', explode( "\n", $content ) );
        return implode( "\n", $lines );
    }

    /**
     * Parse newline-separated User-Agent strings from textarea input.
     *
     * @param string $value
     * @return array
     */
    private function parseCustomUserAgents( $value ) {
        if ( !is_string( $value ) || trim( $value ) === '' ) {
            return [];
        }
        $agents = preg_split( '/\\r\\n|\\r|\\n/', $value );
        $cleaned = [];
        foreach ( $agents as $agent ) {
            $agent = trim( $agent );
            if ( $agent === '' ) {
                continue;
            }
            // Keep User-Agent strings printable and single-line.
            $agent = preg_replace( '/[^\\x20-\\x7E]/', '', $agent );
            if ( $agent === '' ) {
                continue;
            }
            $cleaned[$agent] = $agent;
        }
        return array_values( $cleaned );
    }

    private function getDefaultRobotsTxt() {
        $output = RobotsConfig::generateBaseRules();
        $output .= RobotsConfig::getSignature();
        return $output;
    }

    public function serve_llms_txt() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $parsed_url = parse_url( $request_uri );
        $path = trim( $parsed_url['path'] ?? '', '/' );
        if ( $path !== 'llms.txt' ) {
            return;
        }
    }

    /**
     * Add interpretive-governance header links if enabled
     */
    public function add_ssa_header_links() {
        // Do not inject in admin
        if ( is_admin() ) {
            return;
        }
        $settings = Option::all();
        $enabled = $settings['mode_0']['global_settings']['ssa_header_links']['enabled'] ?? false;
        if ( $enabled ) {
            echo '<link rel="alternate" type="text/plain" href="/robots.txt" title="Site crawl policy (robots.txt)" />' . "\n";
            echo '<link rel="help" href="https://interpretive-governance.org/" title="Interpretive Governance reference (SSA-E) for Better Robots.txt" />' . "\n";
        }
    }

    /**
     * Consolidate robots.txt output by User-agent
     */
    private function consolidateOutput( $content ) {
        $lines = explode( "\n", $content );
        $sitemaps = [];
        $rules = [];
        $currentAgents = [];
        $blockDirectives = [];
        foreach ( $lines as $line ) {
            $trimmed = trim( $line );
            if ( $trimmed === '' || strpos( $trimmed, '#' ) === 0 ) {
                continue;
            }
            if ( stripos( $trimmed, 'Sitemap:' ) === 0 ) {
                $sitemaps[] = $trimmed;
                continue;
            }
            if ( stripos( $trimmed, 'User-agent:' ) === 0 ) {
                if ( !empty( $currentAgents ) && !empty( $blockDirectives ) ) {
                    foreach ( $currentAgents as $agent ) {
                        foreach ( $blockDirectives as $directive ) {
                            $rules[$agent][$directive] = $directive;
                        }
                    }
                    $currentAgents = [];
                    $blockDirectives = [];
                }
                $agent = trim( substr( $trimmed, 11 ) );
                if ( !empty( $agent ) ) {
                    $currentAgents[] = $agent;
                }
                continue;
            }
            if ( !empty( $currentAgents ) ) {
                $blockDirectives[] = $trimmed;
            }
        }
        if ( !empty( $currentAgents ) && !empty( $blockDirectives ) ) {
            foreach ( $currentAgents as $agent ) {
                foreach ( $blockDirectives as $directive ) {
                    $rules[$agent][$directive] = $directive;
                }
            }
        }
        $output = "";
        // Output Sitemaps first
        if ( !empty( $sitemaps ) ) {
            $output .= implode( "\n", array_unique( $sitemaps ) ) . "\n\n";
        }
        // Sort agents: specific first, '*' last
        uksort( $rules, function ( $a, $b ) {
            if ( $a === '*' ) {
                return 1;
            }
            if ( $b === '*' ) {
                return -1;
            }
            return strcasecmp( $a, $b );
        } );
        foreach ( $rules as $agent => $lines ) {
            $orderedLines = [];
            foreach ( $lines as $line ) {
                if ( stripos( $line, 'Content-signal:' ) === 0 ) {
                    $orderedLines[] = $line;
                }
            }
            foreach ( $lines as $line ) {
                if ( stripos( $line, 'Content-signal:' ) !== 0 ) {
                    $orderedLines[] = $line;
                }
            }
            $output .= "User-agent: {$agent}\n";
            $output .= implode( "\n", $orderedLines ) . "\n\n";
        }
        return trim( $output );
    }

    /**
     * Sanitize plain-text file content while preserving line breaks.
     *
     * @param mixed $content
     * @return string
     */
    private function sanitizePlainTextContent( $content ) {
        if ( !is_string( $content ) ) {
            return '';
        }
        $content = preg_replace( '/[^\\x20-\\x7E\\r\\n\\t]/', '', wp_unslash( $content ) );
        $content = str_replace( ["\r\n", "\r"], "\n", $content );
        return trim( (string) $content );
    }

    /**
     * Sanitize custom robots directives while preserving supported directive syntax.
     *
     * @param mixed $rules
     * @return string
     */
    private function sanitizeCustomRules( $rules ) {
        $rules = $this->sanitizePlainTextContent( $rules );
        if ( $rules === '' ) {
            return '';
        }
        return implode( "\n", array_filter( explode( "\n", $rules ), static function ( $line ) {
            $line = trim( $line );
            if ( $line === '' ) {
                return false;
            }
            return preg_match( '/^(#|User-agent:|Allow:|Disallow:|Crawl-delay:|Sitemap:|Content-signal:)/i', $line ) === 1;
        } ) );
    }

}
