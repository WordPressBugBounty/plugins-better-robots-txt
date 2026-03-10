<?php

namespace Pagup\BetterRobots\Bootstrap;

use Pagup\BetterRobots\Controllers\SettingsController;
use Pagup\BetterRobots\Controllers\MetaboxController;
use Pagup\BetterRobots\Core\Asset;
use Pagup\BetterRobots\Traits\ErrorHandler;
class Settings {
    use ErrorHandler;
    private const PLUGIN_PAGE = 'better-robots-txt';

    private const ERROR_PREFIX = 'Better Robots.txt';

    private SettingsController $settingsController;

    /**
     * Premium-only controller. The free build strips both the file and the hooks.
     *
     * @var MetaboxController|null
     */
    private $metaboxController = null;

    /**
     * Initialize the plugin settings
     */
    public function __construct() {
        $this->initializeControllers();
        $this->registerHooks();
        $this->registerAjaxHandlers();
    }

    /**
     * Initialize controller instances
     */
    private function initializeControllers() : void {
        $this->settingsController = new SettingsController();
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks() : void {
        // Menu and Assets
        add_action( 'admin_menu', [$this->settingsController, 'add_settings'] );
        add_action( 'admin_enqueue_scripts', [$this, 'assets'], 999 );
        // Plugin Settings
        add_filter( "plugin_action_links_" . ROBOTS_PLUGIN_BASE, [$this, 'setting_link'] );
        add_filter(
            'script_loader_tag',
            [$this, 'add_module_to_script'],
            10,
            3
        );
    }

    /**
     * Register AJAX handlers
     */
    private function registerAjaxHandlers() : void {
        // Settings Actions
        $this->addAjaxAction( 'rt__options', [$this->settingsController, 'save_options'] );
        $this->addAjaxAction( 'rt__preview', [$this->settingsController, 'preview_options'] );
        $this->addAjaxAction( 'rt__onboarding', [$this->settingsController, 'onboarding'] );
        $this->addAjaxAction( 'rt__delete_physical_file', [$this->settingsController, 'delete_physical_file'] );
    }

    /**
     * Add an AJAX action with error handling
     *
     * @param string $action The AJAX action name
     * @param callable $callback The callback function
     * @param boolean $nopriv Whether to add nopriv action
     */
    private function addAjaxAction( string $action, callable $callback, bool $nopriv = false ) : void {
        $hook = "wp_ajax_{$action}";
        add_action( $hook, function () use($action, $callback) {
            try {
                call_user_func( $callback );
            } catch ( \Exception $e ) {
                error_log( "Error in action wp_ajax_{$action}: " . $e->getMessage() );
                wp_send_json_error( $e->getMessage() );
            }
        } );
        if ( $nopriv ) {
            add_action( "wp_ajax_nopriv_{$action}", $callback );
        }
    }

    /**
     * Add settings link to plugins page
     */
    public function setting_link( array $links ) : array {
        array_unshift( $links, sprintf( '<a href="admin.php?page=%s">Settings</a>', self::PLUGIN_PAGE ) );
        return $links;
    }

    /**
     * Enqueue assets for the plugin
     */
    public function assets() : void {
        // Vue UI assets (only on plugin page)
        if ( $this->is_plugin_page() ) {
            if ( ROBOTS_PLUGIN_MODE === "prod" ) {
                $this->enqueue_production_assets();
            } else {
                $this->enqueue_development_assets();
            }
        }
        // Global admin assets (metabox etc.)
        Asset::style( 'rt_styles', 'admin/assets/app.css' );
        Asset::script(
            'rt_script',
            'admin/assets/app.js',
            [],
            true
        );
    }

    /**
     * Check if current page is plugin page
     */
    private function is_plugin_page() : bool {
        return isset( $_GET['page'] ) && !empty( $_GET['page'] ) && $_GET['page'] === self::PLUGIN_PAGE;
    }

    /**
     * Enqueue production assets
     */
    private function enqueue_production_assets() : void {
        Asset::style( 'robots__styles', 'admin/ui/index.css' );
        Asset::script(
            'robots__main',
            'admin/ui/index.js',
            [],
            true
        );
    }

    /**
     * Enqueue development assets
     */
    private function enqueue_development_assets() : void {
        Asset::script_remote(
            'robots__client',
            'http://172.31.36.77:3213/@vite/client',
            [],
            true,
            true
        );
        Asset::script_remote(
            'robots__main',
            'http://172.31.36.77:3213/src/main.ts',
            [],
            true,
            true
        );
    }

    /**
     * Add module type to script tags for ES6 modules
     */
    public function add_module_to_script( string $tag, string $handle, string $src ) : string {
        if ( ROBOTS_PLUGIN_MODE === "prod" ) {
            if ( $handle === 'robots__main' ) {
                return '<script type="module" src="' . esc_url( $src ) . '"></script>';
            }
        } else {
            if ( in_array( $handle, ['robots__client', 'robots__main'] ) ) {
                return '<script type="module" src="' . esc_url( $src ) . '"></script>';
            }
        }
        return $tag;
    }

}
