<?php
namespace Pagup\BetterRobots\Bootstrap;

use Pagup\BetterRobots\Core\Option;

class PluginManager {
    private string $pluginSlug;
    private const ERROR_PREFIX = 'Better Robots.txt';

    private const DEFAULT_OPTIONS = [
        'user_agents' => "User-agent: *\n"
            ."Allow: /wp-admin/admin-ajax.php\n"
            ."Allow: /*/*.css\n"
            ."Allow: /*/*.js\n"
            ."Disallow: /wp-admin/\n"
            ."Disallow: /wp-includes/\n"
            ."Disallow: /readme.html\n"
            ."Disallow: /license.txt\n"
            ."Disallow: /xmlrpc.php\n"
            ."Disallow: /wp-login.php\n"
            ."Disallow: /wp-register.php\n"
            ."Disallow: */disclaimer/*\n"
            ."Disallow: *?attachment_id=\n"
            ."Disallow: /privacy-policy\n",
        'remove_settings' => false
    ];

    public function __construct(string $pluginSlug) {
        $this->pluginSlug = $pluginSlug;
    }

    public function init(): void {
        try {
            $this->registerHooks();
        } catch (\Exception $e) {
            error_log(sprintf("%s Plugin Error: %s", self::ERROR_PREFIX, $e->getMessage()));
        }
    }

    private function registerHooks(): void {
        add_action('init', [$this, 'loadTextDomain']);
    }

    public function activate(): void {
        try {
            $options = get_option('robots_txt');

            if (!is_array($options)) {
                update_option('robots_txt', self::DEFAULT_OPTIONS);
            }
        } catch (\Exception $e) {
            error_log(sprintf("%s Activation Error: %s", self::ERROR_PREFIX, $e->getMessage()));
        }
    }

    public function deactivate(): void {
        try {
            if (Option::check('remove_settings')) {
                $this->cleanupPluginData();
            }
        } catch (\Exception $e) {
            error_log(sprintf("%s Deactivation Error: %s", self::ERROR_PREFIX, $e->getMessage()));
        }
    }

    private static function cleanupPluginData(): void {
        delete_option('robots_txt');
        delete_option('robots_tour');
    }

    public function loadTextDomain(): void {
        load_plugin_textdomain(
            'better-robots-txt',
            false,
            basename(ROBOTS_PLUGIN_ROOT) . '/languages'
        );
    }
}
