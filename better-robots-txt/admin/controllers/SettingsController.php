<?php

namespace Pagup\BetterRobots\Controllers;

use Pagup\BetterRobots\Core\Option;
use Pagup\BetterRobots\Traits\Sitemap;
use Pagup\BetterRobots\Traits\RobotsHelper;
use Pagup\BetterRobots\Traits\SettingHelper;
class SettingsController {
    use RobotsHelper, SettingHelper, Sitemap;
    protected $yoast_sitemap_url = '';

    protected $xml_sitemap_url = '';

    public function __construct() {
        $this->yoast_sitemap_url = home_url() . '/sitemap_index.xml';
        $this->xml_sitemap_url = home_url() . '/sitemap.xml';
        // Schedule cron job for license verification
        $this->schedule_license_check_cron();
    }

    public function get_pro_link() {
        return sprintf( wp_kses( __( '<a href="%s">Get Pro version</a> to enable', "better-robots-txt" ), array(
            'a' => array(
                'href'   => array(),
                'target' => array(),
            ),
        ) ), esc_url( "admin.php?page=better-robots-txt-pricing" ) );
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
        $has_pro_access = false;
        $has_premium_access = false;
        wp_localize_script( 'robots__main', 'data', array(
            'assets'               => plugins_url( 'assets', dirname( __FILE__ ) ),
            'options'              => $options,
            'onboarding'           => get_option( 'robots_tour' ),
            'pro'                  => $has_pro_access,
            'premium'              => $has_premium_access,
            'blog_public'          => (bool) get_option( 'blog_public' ),
            'woocommerce_detected' => class_exists( 'WooCommerce' ),
            'plugins'              => $this->installable_plugins(),
            'language'             => get_locale(),
            'nonce'                => wp_create_nonce( 'rt__nonce' ),
            'purchase_url'         => rtf_fs()->get_upgrade_url(),
            'recommendations'      => $this->recommendations_list(),
            'robots_url'           => $this->robotsTxtURL(),
            'physical_file'        => $this->get_physical_file_status(),
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
        // Check if this is v3 settings format
        if ( isset( $_POST['settings'] ) && !empty( $_POST['settings'] ) ) {
            $this->save_v3_options();
            return;
        }
        // Legacy format handling
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

    /**
     * Save v3 settings format
     */
    private function save_v3_options() {
        // Get settings data - comes as array from Qs.stringify
        $settings_data = ( isset( $_POST['settings'] ) ? $_POST['settings'] : '' );
        if ( empty( $settings_data ) ) {
            wp_send_json_error( [
                'message' => 'No settings data provided',
            ] );
            wp_die();
        }
        // Qs.stringify sends as array, so we expect an array
        if ( !is_array( $settings_data ) ) {
            wp_send_json_error( [
                'message' => 'Invalid settings format',
            ] );
            wp_die();
        }
        // Normalize boolean values (Qs.stringify converts booleans to strings)
        $settings = $this->normalize_boolean_values( $settings_data );
        $settings = $this->normalize_v3_plan_restrictions( $settings );
        $settings = $this->sanitize_v3_text_fields( $settings );
        // Validate structure
        $validation_errors = $this->validate_v3_settings( $settings );
        if ( !empty( $validation_errors ) ) {
            wp_send_json_error( [
                'message' => implode( '; ', $validation_errors ),
            ] );
            wp_die();
        }
        if ( rtf_fs()->is__premium_only() && rtf_fs()->is_plan_or_trial( 'betterrobotstxtpro' ) && !empty( $settings['mode_0']['advanced_settings']['ensure_search_engine_visibility'] ) ) {
            update_option( 'blog_public', true );
        }
        // Save to database
        $result = update_option( 'robots_txt', $settings );
        // Handle physical robots.txt file if needed
        if ( isset( $settings['mode_0']['robotstxt_infrastructure']['file_mode'] ) || isset( $settings['mode_0']['global_settings']['robots_txt_type'] ) ) {
            $this->handle_physical_robots_file( $settings );
        }
        if ( $result ) {
            wp_send_json_success( [
                'settings'      => $settings,
                'message'       => 'Settings saved successfully',
                'physical_file' => $this->get_physical_file_status(),
                'blog_public'   => (bool) get_option( 'blog_public' ),
            ] );
        } else {
            wp_send_json_error( [
                'message'       => 'Failed to save settings',
                'physical_file' => $this->get_physical_file_status(),
                'blog_public'   => (bool) get_option( 'blog_public' ),
            ] );
        }
        wp_die();
    }

    /**
     * Preview v3 settings without saving
     */
    public function preview_options() {
        // Check nonce and permissions
        if ( check_ajax_referer( 'rt__nonce', 'nonce', false ) == false ) {
            wp_send_json_error( "Invalid nonce", 401 );
            wp_die();
        }
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( "Unauthorized user", 403 );
            wp_die();
        }
        // Get settings data
        $settings_data = ( isset( $_POST['settings'] ) ? $_POST['settings'] : '' );
        if ( empty( $settings_data ) || !is_array( $settings_data ) ) {
            wp_send_json_error( [
                'message' => 'Invalid settings data',
            ] );
            wp_die();
        }
        // Normalize and Validate
        $settings = $this->normalize_boolean_values( $settings_data );
        $settings = $this->normalize_v3_plan_restrictions( $settings );
        $settings = $this->sanitize_v3_text_fields( $settings );
        $validation_errors = $this->validate_v3_settings( $settings );
        if ( !empty( $validation_errors ) ) {
            wp_send_json_error( [
                'message' => implode( '; ', $validation_errors ),
            ] );
            wp_die();
        }
        // Generate robots.txt content
        $robots_controller = new \Pagup\BetterRobots\Controllers\RobotsController();
        $content = $robots_controller->generate( $settings );
        wp_send_json_success( [
            'content' => $content,
            'message' => 'Preview generated',
        ] );
        wp_die();
    }

    /**
     * Delete the physical robots.txt file from the site root.
     * This is available to any admin user because a physical file blocks virtual output.
     */
    public function delete_physical_file() {
        if ( check_ajax_referer( 'rt__nonce', 'nonce', false ) == false ) {
            wp_send_json_error( [
                'message' => 'Invalid nonce',
            ], 401 );
            wp_die();
        }
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [
                'message' => 'Unauthorized user',
            ], 403 );
            wp_die();
        }
        $robots_file_path = ABSPATH . 'robots.txt';
        if ( !file_exists( $robots_file_path ) ) {
            wp_send_json_error( [
                'message'       => 'No physical robots.txt file was found.',
                'physical_file' => $this->get_physical_file_status(),
            ] );
            wp_die();
        }
        if ( !$this->can_delete_physical_file( $robots_file_path ) ) {
            wp_send_json_error( [
                'message'       => 'The physical robots.txt file could not be deleted. Check file permissions and try again.',
                'physical_file' => $this->get_physical_file_status(),
            ] );
            wp_die();
        }
        $deleted = unlink( $robots_file_path );
        if ( $deleted ) {
            delete_option( 'robots_txt_physical_created_by_plugin' );
            delete_option( 'robots_txt_physical_file_hash' );
            wp_send_json_success( [
                'message'       => 'Physical robots.txt file deleted successfully.',
                'physical_file' => $this->get_physical_file_status(),
            ] );
        } else {
            wp_send_json_error( [
                'message'       => 'Failed to delete the physical robots.txt file.',
                'physical_file' => $this->get_physical_file_status(),
            ] );
        }
        wp_die();
    }

    /**
     * Normalize boolean and numeric values from strings
     * Qs.stringify converts booleans and numbers to strings
     *
     * @param array $data Settings data
     * @return array Normalized settings
     */
    private function normalize_boolean_values( $data ) {
        // Normalize active_mode to integer
        if ( isset( $data['active_mode'] ) && is_string( $data['active_mode'] ) ) {
            $data['active_mode'] = (int) $data['active_mode'];
        }
        // Recursively normalize boolean values
        array_walk_recursive( $data, function ( &$value ) {
            if ( $value === 'true' ) {
                $value = true;
            } elseif ( $value === 'false' ) {
                $value = false;
            }
        } );
        return $data;
    }

    /**
     * Validate v3 settings structure
     *
     * @param array $data Settings data to validate
     * @return array Validation errors
     */
    private function validate_v3_settings( $data ) {
        $errors = [];
        // Validate settings_version
        if ( !isset( $data['settings_version'] ) || $data['settings_version'] !== '3.0' ) {
            $errors[] = 'Invalid settings version';
        }
        // Validate active_mode
        if ( !isset( $data['active_mode'] ) || !in_array( $data['active_mode'], [
            0,
            1,
            2,
            3
        ], true ) ) {
            $errors[] = 'Invalid active mode';
        }
        // Validate mode_0 structure if present
        if ( isset( $data['mode_0'] ) ) {
            $mode0_errors = $this->validate_mode_0( $data['mode_0'] );
            $errors = array_merge( $errors, $mode0_errors );
        }
        return $errors;
    }

    /**
     * Normalize v3 settings that depend on the active plan.
     *
     * This keeps invalid paid-only values from persisting when a request is
     * crafted manually or a site has been downgraded since the setting was saved.
     *
     * @param array $settings
     * @return array
     */
    private function normalize_v3_plan_restrictions( $settings ) {
        if ( !is_array( $settings ) || !isset( $settings['mode_0'] ) || !is_array( $settings['mode_0'] ) ) {
            return $settings;
        }
        $is_premium = false;
        $is_pro = false;
        if ( isset( $settings['active_mode'] ) ) {
            if ( !$is_pro ) {
                $settings['active_mode'] = 1;
            } elseif ( !$is_premium && (int) $settings['active_mode'] === 3 ) {
                $settings['active_mode'] = 2;
            }
        }
        if ( !$is_pro ) {
            if ( isset( $settings['mode_0']['global_settings']['robots_txt_type'] ) ) {
                $settings['mode_0']['global_settings']['robots_txt_type'] = 'virtual';
            }
            if ( isset( $settings['mode_0']['global_settings']['sitemaps']['auto_detect_sitemap'] ) ) {
                $settings['mode_0']['global_settings']['sitemaps']['auto_detect_sitemap'] = false;
            }
            if ( isset( $settings['mode_0']['robotstxt_infrastructure']['file_mode'] ) ) {
                $settings['mode_0']['robotstxt_infrastructure']['file_mode'] = 'virtual_robotstxt';
            }
            if ( isset( $settings['mode_0']['ai_module']['ai_search_policy'] ) ) {
                $settings['mode_0']['ai_module']['ai_search_policy'] = 'block_all';
            }
            if ( isset( $settings['mode_0']['seo_tools_module']['custom_bots'] ) ) {
                $settings['mode_0']['seo_tools_module']['custom_bots'] = [];
            }
            if ( isset( $settings['mode_0']['spam_feeds_module']['block_feeds_spam'] ) ) {
                $settings['mode_0']['spam_feeds_module']['block_feeds_spam'] = false;
            }
            if ( isset( $settings['mode_0']['ai_files_module']['llms_txt_enabled'] ) ) {
                $settings['mode_0']['ai_files_module']['llms_txt_enabled'] = false;
            }
            if ( isset( $settings['mode_0']['advanced_settings']['crawl_delay'] ) ) {
                $settings['mode_0']['advanced_settings']['crawl_delay'] = 0;
            }
            if ( isset( $settings['mode_0']['advanced_settings']['ensure_search_engine_visibility'] ) ) {
                $settings['mode_0']['advanced_settings']['ensure_search_engine_visibility'] = false;
            }
        }
        if ( isset( $settings['mode_0']['active_mode'] ) && isset( $settings['active_mode'] ) ) {
            $settings['mode_0']['active_mode'] = (int) $settings['active_mode'];
        }
        return $settings;
    }

    /**
     * Sanitize free-text values in the v3 settings payload before preview/save.
     *
     * @param array $settings
     * @return array
     */
    private function sanitize_v3_text_fields( $settings ) {
        if ( !is_array( $settings ) ) {
            return $settings;
        }
        if ( isset( $settings['mode_0']['advanced_settings']['custom_rules'] ) ) {
            $settings['mode_0']['advanced_settings']['custom_rules'] = $this->sanitize_multiline_plain_text( $settings['mode_0']['advanced_settings']['custom_rules'] );
        }
        if ( isset( $settings['mode_0']['ai_module']['custom_ai_crawlers'] ) ) {
            $settings['mode_0']['ai_module']['custom_ai_crawlers'] = $this->sanitize_multiline_plain_text( $settings['mode_0']['ai_module']['custom_ai_crawlers'] );
        }
        if ( isset( $settings['mode_0']['ai_files_module']['llms_txt_content'] ) ) {
            $settings['mode_0']['ai_files_module']['llms_txt_content'] = $this->sanitize_multiline_plain_text( $settings['mode_0']['ai_files_module']['llms_txt_content'] );
        }
        if ( isset( $settings['mode_0']['ai_files_module']['ai_policy_slug'] ) ) {
            $settings['mode_0']['ai_files_module']['ai_policy_slug'] = sanitize_title( (string) $settings['mode_0']['ai_files_module']['ai_policy_slug'] );
        }
        if ( isset( $settings['mode_0']['ai_files_module']['ai_policy_content'] ) ) {
            $settings['mode_0']['ai_files_module']['ai_policy_content'] = wp_kses_post( (string) $settings['mode_0']['ai_files_module']['ai_policy_content'] );
        }
        if ( isset( $settings['mode_0']['global_settings']['footer_signature']['custom_text'] ) ) {
            $settings['mode_0']['global_settings']['footer_signature']['custom_text'] = $this->sanitize_multiline_plain_text( $settings['mode_0']['global_settings']['footer_signature']['custom_text'] );
        }
        return $settings;
    }

    /**
     * Sanitize textarea-like settings while preserving line breaks.
     *
     * @param mixed $value
     * @return string
     */
    private function sanitize_multiline_plain_text( $value ) {
        if ( !is_string( $value ) ) {
            return '';
        }
        $value = wp_unslash( $value );
        $value = preg_replace( '/[^\\x20-\\x7E\\r\\n\\t]/', '', $value );
        return trim( (string) $value );
    }

    /**
     * Validate Mode 0 settings
     *
     * @param array $mode0 Mode 0 settings
     * @return array Validation errors
     */
    private function validate_mode_0( $mode0 ) {
        $errors = [];
        // Validate search_engine_visibility
        if ( isset( $mode0['search_engine_visibility'] ) ) {
            $visibility = $mode0['search_engine_visibility'];
            $valid_levels = [
                '',
                'minimal_visibility',
                'recommended_visibility',
                'extended_visibility',
                'advanced_custom'
            ];
            if ( !in_array( $visibility['visibility_level'] ?? '', $valid_levels, true ) ) {
                $errors[] = 'Invalid visibility level';
            }
        }
        // Validate ai_content_usage
        if ( isset( $mode0['ai_content_usage'] ) ) {
            $ai = $mode0['ai_content_usage'];
            // block_ai_training must always be true
            if ( !isset( $ai['block_ai_training'] ) || $ai['block_ai_training'] !== true ) {
                $errors[] = 'AI training protection must be enabled';
            }
            $valid_policies = [
                '',
                'block_all_ai_search',
                'block_all',
                'allow_ai_search',
                'allow_all'
            ];
            if ( !in_array( $ai['ai_search_policy'] ?? '', $valid_policies, true ) ) {
                $errors[] = 'Invalid AI search policy';
            }
        }
        // Validate bot_scraper_protection
        if ( isset( $mode0['bot_scraper_protection'] ) ) {
            $protection = $mode0['bot_scraper_protection'];
            $valid_levels = [
                '',
                'basic_protection',
                'advanced_protection',
                'maximum_protection'
            ];
            if ( !in_array( $protection['protection_level'] ?? '', $valid_levels, true ) ) {
                $errors[] = 'Invalid protection level';
            }
        }
        // Validate seo_tool_protection
        if ( isset( $mode0['seo_tool_protection'] ) ) {
            $seo = $mode0['seo_tool_protection'];
            if ( !is_bool( $seo['block_seo_tools'] ?? false ) ) {
                $errors[] = 'Invalid SEO tools blocking setting';
            }
        }
        // Validate spam_feeds_traps
        if ( isset( $mode0['spam_feeds_traps'] ) ) {
            $spam = $mode0['spam_feeds_traps'];
            foreach ( ['block_feeds_spam', 'block_author_archives', 'block_search_pagination'] as $field ) {
                if ( isset( $spam[$field] ) && !is_bool( $spam[$field] ) ) {
                    $errors[] = "Invalid {$field} setting";
                }
            }
        }
        // Validate ecommerce_optimization
        if ( isset( $mode0['ecommerce_optimization'] ) ) {
            $ecommerce = $mode0['ecommerce_optimization'];
            $valid_levels = ['', 'basic_cleanup', 'advanced_cleanup'];
            if ( !in_array( $ecommerce['cleanup_level'] ?? '', $valid_levels, true ) ) {
                $errors[] = 'Invalid cleanup level';
            }
        }
        // Validate archive_control
        if ( isset( $mode0['archive_control'] ) ) {
            $archive = $mode0['archive_control'];
            $valid_policies = ['', 'allow_archiving', 'block_archiving'];
            if ( !in_array( $archive['archive_policy'] ?? '', $valid_policies, true ) ) {
                $errors[] = 'Invalid archive policy';
            }
        }
        // Validate robotstxt_infrastructure
        if ( isset( $mode0['robotstxt_infrastructure'] ) ) {
            $infra = $mode0['robotstxt_infrastructure'];
            $valid_modes = ['virtual_robotstxt', 'physical_robotstxt'];
            if ( !in_array( $infra['file_mode'] ?? '', $valid_modes, true ) ) {
                $errors[] = 'Invalid file mode';
            }
            if ( isset( $infra['remove_signature'] ) && !is_bool( $infra['remove_signature'] ) ) {
                $errors[] = 'Invalid signature removal setting';
            }
        }
        // Validate sitemaps
        if ( isset( $mode0['sitemaps'] ) ) {
            $sitemaps = $mode0['sitemaps'];
            // Validate manual sitemap URL if provided
            if ( !empty( $sitemaps['manual_sitemap_url'] ) ) {
                if ( !filter_var( $sitemaps['manual_sitemap_url'], FILTER_VALIDATE_URL ) ) {
                    $errors[] = 'Invalid sitemap URL';
                }
            }
            if ( isset( $sitemaps['auto_detect_sitemap'] ) && !is_bool( $sitemaps['auto_detect_sitemap'] ) ) {
                $errors[] = 'Invalid auto-detect sitemap setting';
            }
        }
        // Validate global_settings
        if ( isset( $mode0['global_settings'] ) ) {
            $global = $mode0['global_settings'];
            if ( isset( $global['robots_txt_type'] ) ) {
                $valid_types = ['virtual', 'physical'];
                if ( !in_array( $global['robots_txt_type'], $valid_types, true ) ) {
                    $errors[] = 'Invalid robots txt type';
                }
            }
            if ( isset( $global['ssa_header_links'] ) && isset( $global['ssa_header_links']['enabled'] ) && !is_bool( $global['ssa_header_links']['enabled'] ) ) {
                $errors[] = 'Invalid SSA header links enabling setting';
            }
        }
        return $errors;
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
        $robots_file_path = ABSPATH . 'robots.txt';
        // Support both legacy and v3 formats
        $create_physical = false;
        // v3 format: mode_0.robotstxt_infrastructure.file_mode
        if ( isset( $options['mode_0']['robotstxt_infrastructure']['file_mode'] ) ) {
            $create_physical = $options['mode_0']['robotstxt_infrastructure']['file_mode'] === 'physical_robotstxt';
        } elseif ( isset( $options['mode_0']['global_settings']['robots_txt_type'] ) ) {
            $create_physical = $options['mode_0']['global_settings']['robots_txt_type'] === 'physical';
        } elseif ( isset( $options['create_physical_file'] ) ) {
            $create_physical = $options['create_physical_file'] === 'yes';
        }
        // Delete physical robots.txt file if it exists and was created by us
        if ( file_exists( $robots_file_path ) && $this->verify_file_ownership( $robots_file_path ) ) {
            $deleted = unlink( $robots_file_path );
            if ( $deleted ) {
                // Clean up tracking options
                delete_option( 'robots_txt_physical_created_by_plugin' );
                delete_option( 'robots_txt_physical_file_hash' );
                $this->debug_log( 'Better Robots.txt: Physical robots.txt file deleted and tracking removed' );
            } else {
                $this->debug_log( 'Better Robots.txt: Failed to delete physical robots.txt file' );
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
        $has_signature = strpos( $file_content, '# This robots.txt file was created by Better Robots.txt' ) !== false || strpos( $file_content, '# Generated by Better Robots.txt' ) !== false;
        $stored_hash = get_option( 'robots_txt_physical_file_hash' );
        $current_hash = md5_file( $file_path );
        $hash_matches = $stored_hash && $stored_hash === $current_hash;
        return $has_signature || $hash_matches;
    }

    /**
     * Return the current physical robots.txt file status for the admin UI.
     *
     * @return array<string, bool>
     */
    private function get_physical_file_status() {
        $robots_file_path = ABSPATH . 'robots.txt';
        $exists = file_exists( $robots_file_path );
        return [
            'exists'          => $exists,
            'is_plugin_owned' => ( $exists ? $this->verify_file_ownership( $robots_file_path ) : false ),
            'is_writable'     => ( $exists ? $this->can_delete_physical_file( $robots_file_path ) : false ),
        ];
    }

    /**
     * Check whether the physical file can be deleted by PHP.
     *
     * @param string $file_path
     * @return bool
     */
    private function can_delete_physical_file( $file_path ) {
        return file_exists( $file_path ) && (is_writable( $file_path ) || is_writable( dirname( $file_path ) ));
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
            self::debug_log( 'Better Robots.txt: No tracking found, skipping cleanup' );
            return;
        }
        // Verify file exists
        if ( !file_exists( $robots_file_path ) ) {
            // File doesn't exist, just clean up tracking
            delete_option( 'robots_txt_physical_created_by_plugin' );
            delete_option( 'robots_txt_physical_file_hash' );
            self::debug_log( 'Better Robots.txt: File not found, tracking cleaned up' );
            return;
        }
        // Verify file ownership before deletion
        $file_content = file_get_contents( $robots_file_path );
        $has_signature = strpos( $file_content, '# This robots.txt file was created by Better Robots.txt' ) !== false || strpos( $file_content, '# Generated by Better Robots.txt' ) !== false;
        $stored_hash = get_option( 'robots_txt_physical_file_hash' );
        $current_hash = md5_file( $robots_file_path );
        $hash_matches = $stored_hash && $stored_hash === $current_hash;
        if ( $has_signature || $hash_matches ) {
            // File was created by us, safe to delete
            $deleted = unlink( $robots_file_path );
            if ( $deleted ) {
                delete_option( 'robots_txt_physical_created_by_plugin' );
                delete_option( 'robots_txt_physical_file_hash' );
                self::debug_log( 'Better Robots.txt: Physical file deleted due to license change. License ID: ' . (( $license && !empty( $license->id ) ? $license->id : 'N/A' )) );
            } else {
                self::debug_log( 'Better Robots.txt: Failed to delete physical robots.txt file on license change' );
            }
        } else {
            // File was modified by user, don't delete but clean up tracking
            delete_option( 'robots_txt_physical_created_by_plugin' );
            delete_option( 'robots_txt_physical_file_hash' );
            self::debug_log( 'Better Robots.txt: File was modified by user, tracking removed but file preserved' );
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
            $has_signature = strpos( $file_content, '# This robots.txt file was created by Better Robots.txt' ) !== false || strpos( $file_content, '# Generated by Better Robots.txt' ) !== false;
            $stored_hash = get_option( 'robots_txt_physical_file_hash' );
            $current_hash = md5_file( $robots_file_path );
            $hash_matches = $stored_hash && $stored_hash === $current_hash;
            if ( $has_signature || $hash_matches ) {
                unlink( $robots_file_path );
                self::debug_log( 'Better Robots.txt: Physical file deleted during uninstall' );
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
        $has_signature = strpos( $file_content, '# This robots.txt file was created by Better Robots.txt' ) !== false || strpos( $file_content, '# Generated by Better Robots.txt' ) !== false;
        $stored_hash = get_option( 'robots_txt_physical_file_hash' );
        $current_hash = md5_file( $robots_file_path );
        $hash_matches = $stored_hash && $stored_hash === $current_hash;
        if ( $has_signature || $hash_matches ) {
            // File was created by us, safe to delete
            $deleted = unlink( $robots_file_path );
            if ( $deleted ) {
                delete_option( 'robots_txt_physical_created_by_plugin' );
                delete_option( 'robots_txt_physical_file_hash' );
                self::debug_log( 'Better Robots.txt: Physical file deleted by cron job due to missing premium access' );
            }
        } else {
            // File was modified by user, don't delete but clean up tracking
            delete_option( 'robots_txt_physical_created_by_plugin' );
            delete_option( 'robots_txt_physical_file_hash' );
            self::debug_log( 'Better Robots.txt: File was modified by user, tracking removed by cron but file preserved' );
        }
    }

    /**
     * Log plugin debug messages only when WP_DEBUG is enabled.
     *
     * @param string $message
     * @return void
     */
    private static function debug_log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( $message );
        }
    }

}
