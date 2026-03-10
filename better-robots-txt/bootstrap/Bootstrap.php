<?php

namespace Pagup\BetterRobots\Bootstrap;

use Pagup\BetterRobots\Bootstrap\Settings;
use Pagup\BetterRobots\Bootstrap\PluginManager;
use Pagup\BetterRobots\Bootstrap\FreemiusManager;
use Pagup\BetterRobots\Controllers\RobotsController;
use Pagup\BetterRobots\Controllers\RobotsControllerLegacy;
use Pagup\BetterRobots\Controllers\AISitemapController;
class Bootstrap {
    /**
     * Plugin configuration
     */
    private const PLUGIN_CONFIG = [
        'id'           => '2345',
        'slug'         => 'better-robots-txt',
        'public_key'   => 'pk_fc28da2ba58a7288429539266f4db',
        'error_prefix' => 'Better Robots.txt',
        'messages'     => [
            'connect' => [
                'title'        => 'Hey %1$s, %2$s Click on Allow & Continue to start optimizing your website with your robots.txt :)!  Create a powerful robots.txt with clear instructions for crawlers to get better results on search engines and improve your SEO. %2$s Never miss an important update -- opt-in to our security and feature updates notifications. %2$s See you on the other side. %2$s Looking for more Wp plugins?',
                'more_plugins' => [
                    'Meta Tags for SEO'           => 'meta-tags-for-seo',
                    'Auto internal links for SEO' => 'automatic-internal-links-for-seo',
                    'Bulk auto image Alt Text'    => 'bulk-image-alt-text-with-yoast',
                    'Bulk auto image Title Tag'   => 'bulk-image-title-attribute',
                    'Mobile view'                 => 'mobilook',
                    'Better-Robots.txt'           => 'better-robots-txt',
                    'Wp Google Street View'       => 'wp-google-street-view',
                    'VidSeo'                      => 'vidseo',
                ],
            ],
        ],
    ];

    /**
     * @var FreemiusManager
     */
    private static FreemiusManager $freemiusManager;

    /**
     * @var PluginManager
     */
    private static PluginManager $pluginManager;

    /**
     * Initialize the plugin
     */
    public static function init() : void {
        try {
            self::defineConstants();
            self::initializePlugin();
        } catch ( \Exception $e ) {
            error_log( sprintf( "%s Bootstrap Error: %s", self::PLUGIN_CONFIG['error_prefix'], $e->getMessage() ) );
        }
    }

    /**
     * Define plugin constants
     */
    private static function defineConstants() : void {
        $constants = [
            'ROBOTS_PLUGIN_BASE' => plugin_basename( ROBOTS_PLUGIN_ROOT . 'better-robots-txt.php' ),
            'ROBOTS_PLUGIN_DIR'  => plugins_url( '', ROBOTS_PLUGIN_ROOT . 'better-robots-txt.php' ),
            'ROBOTS_PLUGIN_MODE' => "prod",
        ];
        foreach ( $constants as $name => $value ) {
            if ( !defined( $name ) ) {
                define( $name, $value );
            }
        }
    }

    /**
     * Initialize plugin managers
     */
    private static function initializePlugin() : void {
        // Initialize Freemius customization
        self::$freemiusManager = new FreemiusManager(self::PLUGIN_CONFIG['id'], self::PLUGIN_CONFIG['slug'], self::PLUGIN_CONFIG['messages']);
        self::$freemiusManager->init();
        // Initialize plugin manager
        self::$pluginManager = new PluginManager(self::PLUGIN_CONFIG['slug']);
        self::$pluginManager->init();
        // Initialize admin functionality
        if ( is_admin() ) {
            new Settings();
        }
        // Initialize Robots.txt controller depending on whether the user has migrated to v3 settings
        $options = get_option( 'robots_txt' );
        if ( isset( $options['settings_version'] ) && $options['settings_version'] === '3.0' ) {
            new RobotsController();
        } else {
            new RobotsControllerLegacy();
            // Hook into admin_notices to let the user know they are running in legacy mode
            add_action( 'admin_notices', [self::class, 'displayLegacyAdminNotice'] );
            // Handle notice dismissal via AJAX
            add_action( 'wp_ajax_better_robots_dismiss_legacy_notice', [self::class, 'handleAjaxDismissNotice'] );
        }
    }

    /**
     * Get plugin configuration
     */
    public static function getConfig( string $key ) : string {
        return self::PLUGIN_CONFIG[$key] ?? '';
    }

    /**
     * Display a notice to users who are still using the pre-v3 database structure
     */
    public static function displayLegacyAdminNotice() : void {
        // Ensure the user has permission to manage options before showing the notice
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }
        $user_id = get_current_user_id();
        $dismissed_timestamp = get_user_meta( $user_id, 'better_robots_legacy_notice_dismissed', true );
        // Check if dismissed within the last 7 days (7 * 24 * 60 * 60 = 604800 seconds)
        if ( $dismissed_timestamp && time() - $dismissed_timestamp < 604800 ) {
            return;
        }
        $settings_url = admin_url( 'admin.php?page=better-robots-txt' );
        $nonce = wp_create_nonce( 'better_robots_dismiss_notice' );
        echo '<div class="notice notice-warning is-dismissible better-robots-legacy-notice" style="border-left-width: 4px; padding: 12px 15px; display: flex; align-items: center; justify-content: space-between; gap: 20px;">';
        echo '<div style="flex-grow: 1;">';
        echo '<p style="font-size: 16px; margin: 0 0 5px 0;"><strong>🚀 Better Robots.txt v3 is Here!</strong></p>';
        echo '<p style="font-size: 14.5px; margin: 0;">We\'ve completely rebuilt the plugin! Your current robots.txt rules are still working perfectly, but you are currently running in <strong>Legacy Mode</strong>. Please review and save your new configuration to unlock the full potential of v3.</p>';
        echo '</div>';
        echo '<div style="flex-shrink: 0; margin-right: 20px; display: flex; flex-direction: column; align-items: center; gap: 4px;">';
        echo '<a href="' . esc_url( $settings_url ) . '" class="button button-primary" style="font-size: 14px; padding: 4px 16px; white-space: nowrap;">Review & Save Settings</a>';
        echo '<a href="#" class="better-robots-dismiss-link" style="font-size: 12px; text-decoration: none;">Remind me in 7 days</a>';
        echo '</div>';
        echo '</div>';
        // Add inline script to handle 7-day dismissal
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $(document).on('click', '.better-robots-dismiss-link', function(e) {
                    e.preventDefault();
                    var $notice = $(this).closest('.better-robots-legacy-notice');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'better_robots_dismiss_legacy_notice',
                            nonce: '<?php 
        echo esc_js( $nonce );
        ?>'
                        },
                        success: function() {
                            $notice.fadeOut(300, function() {
                                $(this).remove();
                            });
                        }
                    });
                });
            });
        </script>
        <?php 
    }

    /**
     * Handle AJAX request to dismiss the legacy notice
     */
    public static function handleAjaxDismissNotice() : void {
        check_ajax_referer( 'better_robots_dismiss_notice', 'nonce' );
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die();
        }
        $user_id = get_current_user_id();
        update_user_meta( $user_id, 'better_robots_legacy_notice_dismissed', time() );
        wp_send_json_success();
    }

}
