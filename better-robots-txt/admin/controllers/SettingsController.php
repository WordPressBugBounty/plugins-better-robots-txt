<?php

namespace Pagup\BetterRobots\Controllers;

use Pagup\BetterRobots\Core\Option;
use Pagup\BetterRobots\Traits\Sitemap;
use Pagup\BetterRobots\Traits\RobotsHelper;
use Pagup\BetterRobots\Traits\SettingHelper;
class SettingsController {
    use RobotsHelper, SettingHelper, Sitemap;
    protected $get_pro = '';

    protected $yoast_sitemap_url = '';

    protected $xml_sitemap_url = '';

    public function __construct() {
        $this->get_pro = sprintf( wp_kses( __( '<a href="%s">Get Pro version</a> to enable', "better-robots-txt" ), array(
            'a' => array(
                'href'   => array(),
                'target' => array(),
            ),
        ) ), esc_url( "admin.php?page=better-robots-txt-pricing" ) );
        $this->yoast_sitemap_url = home_url() . '/sitemap_index.xml';
        $this->xml_sitemap_url = home_url() . '/sitemap.xml';
        // Schedule cron job for license verification
        $this->schedule_license_check_cron();
    }

    public function add_settings() {
        add_menu_page(
            __( 'Better Robots.txt Settings', 'better-robots-txt' ),
            __( 'Better Robots.txt', 'better-robots-txt' ),
            'manage_options',
            'better-robots-txt',
            array(&$this, 'page'),
            'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB2aWV3Qm94PSIwIDAgNjQwIDUxMiI+ICAgIDxwYXRoIGQ9Ik0zMiAyMjRoMzJ2MTkySDMyYTMxLjk2MiAzMS45NjIgMCAwIDEtMzItMzJWMjU2YTMxLjk2MiAzMS45NjIgMCAwIDEgMzItMzJ6bTUxMi00OHYyNzJhNjQuMDYzIDY0LjA2MyAwIDAgMS02NCA2NEgxNjBhNjQuMDYzIDY0LjA2MyAwIDAgMS02NC02NFYxNzZhNzkuOTc0IDc5Ljk3NCAwIDAgMSA4MC04MGgxMTJWMzJhMzIgMzIgMCAwIDEgNjQgMHY2NGgxMTJhNzkuOTc0IDc5Ljk3NCAwIDAgMSA4MCA4MHptLTI4MCA4MGE0MCA0MCAwIDEgMC00MCA0MGEzOS45OTcgMzkuOTk3IDAgMCAwIDQwLTQwem0tOCAxMjhoLTY0djMyaDY0em05NiAwaC02NHYzMmg2NHptMTA0LTEyOGE0MCA0MCAwIDEgMC00MCA0MGEzOS45OTcgMzkuOTk3IDAgMCAwIDQwLTQwem0tOCAxMjhoLTY0djMyaDY0em0xOTItMTI4djEyOGEzMS45NjIgMzEuOTYyIDAgMCAxLTMyIDMyaC0zMlYyMjRoMzJhMzEuOTYyIDMxLjk2MiAwIDAgMSAzMiAzMnoiIGZpbGw9ImN1cnJlbnRDb2xvciI+PC9wYXRoPjwvc3ZnPg=='
        );
    }

    public function page() {
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Sorry, you are not allowed to access this page.', "better-robots-txt" ) );
        }
        // only users with `unfiltered_html` can edit scripts.
        if ( !current_user_can( 'unfiltered_html' ) ) {
            wp_die( __( 'Sorry, you are not allowed to edit this page. Ask your administrator for assistance.', "better-robots-txt" ) );
        }
        // Get Options
        $get_options = new Option();
        $options = $get_options::all();
        // Unserialize 'backlinks_bots' array if it's set.
        if ( isset( $options['backlinks_bots'] ) && !empty( $options['backlinks_bots'] ) ) {
            $options['backlinks_bots'] = maybe_unserialize( $options['backlinks_bots'] );
        }
        wp_localize_script( 'robots__main', 'data', array(
            'assets'          => plugins_url( 'assets', dirname( __FILE__ ) ),
            'options'         => $options,
            'onboarding'      => get_option( 'robots_tour' ),
            'pro'             => rtf_fs()->can_use_premium_code__premium_only(),
            'plugins'         => $this->installable_plugins(),
            'language'        => get_locale(),
            'nonce'           => wp_create_nonce( 'rt__nonce' ),
            'purchase_url'    => rtf_fs()->get_upgrade_url(),
            'recommendations' => $this->recommendations_list(),
            'robots_url'      => $this->robotsTxtURL(),
        ) );
        if ( ROBOTS_PLUGIN_MODE !== "prod" ) {
            echo $this->devNotification();
        }
        echo '<div id="rt__app"></div>';
    }

    public function save_options() {
        // check the nonce
        if ( check_ajax_referer( 'rt__nonce', 'nonce', false ) == false ) {
            wp_send_json_error( "Invalid nonce", 401 );
            wp_die();
        }
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( "Unauthorized user", 403 );
            wp_die();
        }
        $safe = [
            "allow",
            "disallow",
            "yes",
            "no",
            "remove_settings",
            "wordpress",
            "yoast",
            "aioseo",
            "custom"
        ];
        $options = $this->sanitize_options( $_POST['options'], $safe );
        $result = update_option( 'robots_txt', $options );
        // Handle physical robots.txt file creation/deletion
        $this->handle_physical_robots_file( $options );
        if ( $result ) {
            wp_send_json_success( [
                'options' => $options,
                'message' => "Saved Successfully",
            ] );
        } else {
            wp_send_json_error( [
                'options' => $options,
                'message' => "Error Saving Options",
            ] );
        }
        wp_die();
    }

    public function onboarding() {
        // check the nonce
        if ( check_ajax_referer( 'rt__nonce', 'nonce', false ) == false ) {
            wp_send_json_error( "Invalid nonce", 401 );
            wp_die();
        }
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( "Unauthorized user", 403 );
            wp_die();
        }
        $closed = ( isset( $_POST['closed'] ) ? $_POST['closed'] === 'true' || $_POST['closed'] === true : false );
        $result = update_option( 'robots_tour', $closed );
        if ( $result ) {
            wp_send_json_success( [
                'robots_tour' => get_option( 'robots_tour' ),
                'message'     => "Tour closed value saved successfully",
            ] );
        } else {
            wp_send_json_error( [
                'robots_tour' => get_option( 'robots_tour' ),
                'message'     => "Error Saving Tour closed value",
            ] );
        }
    }

    /**
     * Get the fields, including both free and premium fields if applicable.
     *
     * @param array $safe The array of safe values used for validation.
     * @return array The merged array of free and premium fields, if premium is available.
     */
    public function getFields( array $safe ) : array {
        $fields = [
            'feed_protector'  => $safe,
            'user_agents'     => 'textarea',
            'crawl_delay'     => 'text',
            'personalize'     => 'textarea',
            'boost-alt'       => $safe,
            'ads-txt'         => $safe,
            'app-ads-txt'     => $safe,
            'remove_settings' => $safe,
        ];
        $premium_fields = [];
        return array_merge( $fields, $premium_fields );
    }

    /**
     * Handle creation/deletion of physical robots.txt file
     *
     * @param array $options The saved options array
     */
    private function handle_physical_robots_file( $options ) {
        return;
        $robots_file_path = ABSPATH . 'robots.txt';
        $create_physical = isset( $options['create_physical_file'] ) && $options['create_physical_file'] === 'yes';
        if ( $create_physical ) {
            // Use RobotsController to generate content - avoiding duplication
            $robots_controller = new \Pagup\BetterRobots\Controllers\RobotsController();
            $robots_content = $robots_controller->generate_robots_content( $options );
            // Create/update the physical file
            $result = file_put_contents( $robots_file_path, $robots_content );
            if ( $result === false ) {
                error_log( 'Better Robots.txt: Failed to create physical robots.txt file' );
            } else {
                // Track that this file was created by our plugin
                update_option( 'robots_txt_physical_created_by_plugin', current_time( 'timestamp' ) );
                update_option( 'robots_txt_physical_file_hash', md5_file( $robots_file_path ) );
                error_log( 'Better Robots.txt: Physical robots.txt file created and tracked' );
            }
        } else {
            // Delete physical robots.txt file if it exists and was created by us
            if ( file_exists( $robots_file_path ) && $this->verify_file_ownership( $robots_file_path ) ) {
                $deleted = unlink( $robots_file_path );
                if ( $deleted ) {
                    // Clean up tracking options
                    delete_option( 'robots_txt_physical_created_by_plugin' );
                    delete_option( 'robots_txt_physical_file_hash' );
                    error_log( 'Better Robots.txt: Physical robots.txt file deleted and tracking removed' );
                } else {
                    error_log( 'Better Robots.txt: Failed to delete physical robots.txt file' );
                }
            }
        }
    }

    /**
     * Verify if the physical robots.txt file was created by our plugin
     *
     * @param string $file_path Path to robots.txt file
     * @return bool True if file was created by plugin
     */
    private function verify_file_ownership( $file_path ) {
        // Check if tracking option exists
        if ( !get_option( 'robots_txt_physical_created_by_plugin' ) ) {
            return false;
        }
        // Verify file contains our signature OR matches stored hash
        $file_content = file_get_contents( $file_path );
        $has_signature = strpos( $file_content, '# This robots.txt file was created by Better Robots.txt' ) !== false;
        $stored_hash = get_option( 'robots_txt_physical_file_hash' );
        $current_hash = md5_file( $file_path );
        $hash_matches = $stored_hash && $stored_hash === $current_hash;
        return $has_signature || $hash_matches;
    }

    /**
     * Cleanup physical robots.txt file when premium access is lost
     * Called by Freemius hook when license changes
     *
     * @param object $license License object from Freemius
     */
    public static function cleanup_on_license_change( $license = null ) {
        $robots_file_path = ABSPATH . 'robots.txt';
        // Check if tracking option exists
        if ( !get_option( 'robots_txt_physical_created_by_plugin' ) ) {
            // File wasn't created by us, don't touch it
            error_log( 'Better Robots.txt: No tracking found, skipping cleanup' );
            return;
        }
        // Verify file exists
        if ( !file_exists( $robots_file_path ) ) {
            // File doesn't exist, just clean up tracking
            delete_option( 'robots_txt_physical_created_by_plugin' );
            delete_option( 'robots_txt_physical_file_hash' );
            error_log( 'Better Robots.txt: File not found, tracking cleaned up' );
            return;
        }
        // Verify file ownership before deletion
        $file_content = file_get_contents( $robots_file_path );
        $has_signature = strpos( $file_content, '# This robots.txt file was created by Better Robots.txt' ) !== false;
        $stored_hash = get_option( 'robots_txt_physical_file_hash' );
        $current_hash = md5_file( $robots_file_path );
        $hash_matches = $stored_hash && $stored_hash === $current_hash;
        if ( $has_signature || $hash_matches ) {
            // File was created by us, safe to delete
            $deleted = unlink( $robots_file_path );
            if ( $deleted ) {
                delete_option( 'robots_txt_physical_created_by_plugin' );
                delete_option( 'robots_txt_physical_file_hash' );
                error_log( 'Better Robots.txt: Physical file deleted due to license change. License ID: ' . (( $license && !empty( $license->id ) ? $license->id : 'N/A' )) );
            } else {
                error_log( 'Better Robots.txt: Failed to delete physical robots.txt file on license change' );
            }
        } else {
            // File was modified by user, don't delete but clean up tracking
            delete_option( 'robots_txt_physical_created_by_plugin' );
            delete_option( 'robots_txt_physical_file_hash' );
            error_log( 'Better Robots.txt: File was modified by user, tracking removed but file preserved' );
        }
    }

    /**
     * Cleanup physical robots.txt file during plugin uninstall
     * Fallback cleanup method
     */
    public static function cleanup_on_uninstall() {
        $robots_file_path = ABSPATH . 'robots.txt';
        // Only cleanup if tracking exists
        if ( get_option( 'robots_txt_physical_created_by_plugin' ) && file_exists( $robots_file_path ) ) {
            // Verify ownership before deletion
            $file_content = file_get_contents( $robots_file_path );
            $has_signature = strpos( $file_content, '# This robots.txt file was created by Better Robots.txt' ) !== false;
            if ( $has_signature ) {
                unlink( $robots_file_path );
                error_log( 'Better Robots.txt: Physical file deleted during uninstall' );
            }
        }
        // Always clean up tracking options
        delete_option( 'robots_txt_physical_created_by_plugin' );
        delete_option( 'robots_txt_physical_file_hash' );
    }

    /**
     * Schedule the license check cron job
     */
    private function schedule_license_check_cron() {
        // Check if already scheduled
        if ( !wp_next_scheduled( 'robots_txt_check_license_status' ) ) {
            // Schedule to run every hour
            wp_schedule_event( time(), 'hourly', 'robots_txt_check_license_status' );
        }
    }

    /**
     * Cron job: Check license status and cleanup if needed
     * This runs hourly as a fallback for when hooks don't fire
     */
    public static function cron_check_license_status() {
        $robots_file_path = ABSPATH . 'robots.txt';
        // Check if we have tracking data
        if ( !get_option( 'robots_txt_physical_created_by_plugin' ) ) {
            // No tracking data, nothing to do
            return;
        }
        // Check if file exists
        if ( !file_exists( $robots_file_path ) ) {
            // File doesn't exist, clean up tracking only
            delete_option( 'robots_txt_physical_created_by_plugin' );
            delete_option( 'robots_txt_physical_file_hash' );
            return;
        }
        // Verify file ownership before deletion
        $file_content = file_get_contents( $robots_file_path );
        $has_signature = strpos( $file_content, '# This robots.txt file was created by Better Robots.txt' ) !== false;
        $stored_hash = get_option( 'robots_txt_physical_file_hash' );
        $current_hash = md5_file( $robots_file_path );
        $hash_matches = $stored_hash && $stored_hash === $current_hash;
        if ( $has_signature || $hash_matches ) {
            // File was created by us, safe to delete
            $deleted = unlink( $robots_file_path );
            if ( $deleted ) {
                delete_option( 'robots_txt_physical_created_by_plugin' );
                delete_option( 'robots_txt_physical_file_hash' );
                error_log( 'Better Robots.txt: Physical file deleted by cron job due to missing premium access' );
            }
        } else {
            // File was modified by user, don't delete but clean up tracking
            delete_option( 'robots_txt_physical_created_by_plugin' );
            delete_option( 'robots_txt_physical_file_hash' );
            error_log( 'Better Robots.txt: File was modified by user, tracking removed by cron but file preserved' );
        }
    }

}

$settings = new SettingsController();