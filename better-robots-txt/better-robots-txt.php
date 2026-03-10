<?php

/*
* Plugin Name: Better Robots.txt - AI-Ready Crawl Control & Bot Governance
* Description: Better-Robots.txt plugin helps you boosting your website indexation and your ranking by adding specific instructions in your robots.txt
* Author: Pagup
* Version: 3.0.0
* Author URI: https://pagup.com/
* Text Domain: better-robots-txt
* Domain Path: /languages/
*
*/
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Define plugin root directory
if ( !defined( 'ROBOTS_PLUGIN_ROOT' ) ) {
    define( 'ROBOTS_PLUGIN_ROOT', plugin_dir_path( __FILE__ ) );
}
/******************************************
                Freemius Init
*******************************************/
if ( function_exists( 'rtf_fs' ) ) {
    rtf_fs()->set_basename( false, __FILE__ );
} else {
    if ( !function_exists( 'rtf_fs' ) ) {
        require ROBOTS_PLUGIN_ROOT . 'vendor/autoload.php';
        // Create a helper function for easy SDK access.
        function rtf_fs() {
            global $rtf_fs;
            if ( !isset( $rtf_fs ) ) {
                // Include Freemius SDK.
                require_once ROBOTS_PLUGIN_ROOT . 'vendor/freemius/wordpress-sdk/start.php';
                $rtf_fs = fs_dynamic_init( array(
                    'id'               => '2345',
                    'slug'             => 'better-robots-txt',
                    'type'             => 'plugin',
                    'public_key'       => 'pk_fc28da2ba58a7288429539266f4db',
                    'is_premium'       => false,
                    'has_addons'       => false,
                    'has_paid_plans'   => true,
                    'trial'            => array(
                        'days'               => 14,
                        'is_require_payment' => true,
                    ),
                    'has_affiliation'  => 'all',
                    'menu'             => array(
                        'slug'       => 'better-robots-txt',
                        'first-path' => 'admin.php?page=better-robots-txt',
                        'support'    => false,
                    ),
                    'is_live'          => true,
                    'is_org_compliant' => true,
                ) );
            }
            return $rtf_fs;
        }

        // Init Freemius.
        rtf_fs();
        // Signal that SDK was initiated.
        do_action( 'rtf_fs_loaded' );
        // Hook for license changes (cancellation, expiration, downgrade, etc.)
        rtf_fs()->add_action( 'after_license_change', array('Pagup\\BetterRobots\\Controllers\\SettingsController', 'cleanup_on_license_change') );
        // Hook for license deactivation
        rtf_fs()->add_action( 'after_license_deactivation', array('Pagup\\BetterRobots\\Controllers\\SettingsController', 'cleanup_on_license_change') );
        // Hook for account deletion (covers license deletion scenarios)
        rtf_fs()->add_action( 'after_account_delete', array('Pagup\\BetterRobots\\Controllers\\SettingsController', 'cleanup_on_license_change') );
        // Fallback hook for plugin uninstall
        rtf_fs()->add_action( 'after_uninstall', array('Pagup\\BetterRobots\\Controllers\\SettingsController', 'cleanup_on_uninstall') );
        // Register cron action hook for WP-CLI compatibility
        add_action( 'robots_txt_check_license_status', array('Pagup\\BetterRobots\\Controllers\\SettingsController', 'cron_check_license_status') );
    }
    // Create plugin manager instance
    $pluginManager = new Pagup\BetterRobots\Bootstrap\PluginManager('better-robots-txt');
    // Register activation and deactivation hooks
    register_activation_hook( __FILE__, array($pluginManager, 'activate') );
    register_deactivation_hook( __FILE__, array($pluginManager, 'deactivate') );
    // Initialize the plugin via Bootstrap
    add_action( 'plugins_loaded', function () {
        try {
            Pagup\BetterRobots\Bootstrap\Bootstrap::init();
        } catch ( \Exception $e ) {
            add_action( 'admin_notices', function () use($e) {
                ?>
                <div class="notice notice-error">
                    <p>Better Robots.txt Error: <?php 
                echo esc_html( $e->getMessage() );
                ?></p>
                </div>
                <?php 
            } );
        }
    } );
}