<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit04e98c36e78e8c4dc39021769985089e
{
    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Pagup\\BetterRobots\\Controllers\\MetaboxController' => __DIR__ . '/../..' . '/admin/controllers/MetaboxController.php',
        'Pagup\\BetterRobots\\Controllers\\NotificationController' => __DIR__ . '/../..' . '/admin/controllers/NotificationController.php',
        'Pagup\\BetterRobots\\Controllers\\RobotsController' => __DIR__ . '/../..' . '/admin/controllers/RobotsController.php',
        'Pagup\\BetterRobots\\Controllers\\SettingsController' => __DIR__ . '/../..' . '/admin/controllers/SettingsController.php',
        'Pagup\\BetterRobots\\Core\\Asset' => __DIR__ . '/../..' . '/core/Asset.php',
        'Pagup\\BetterRobots\\Core\\Option' => __DIR__ . '/../..' . '/core/Option.php',
        'Pagup\\BetterRobots\\Core\\Plugin' => __DIR__ . '/../..' . '/core/Plugin.php',
        'Pagup\\BetterRobots\\Core\\Request' => __DIR__ . '/../..' . '/core/Request.php',
        'Pagup\\BetterRobots\\Settings' => __DIR__ . '/../..' . '/admin/Settings.php',
        'Pagup\\BetterRobots\\Traits\\RobotsHelper' => __DIR__ . '/../..' . '/admin/traits/RobotsHelper.php',
        'Pagup\\BetterRobots\\Traits\\SettingHelper' => __DIR__ . '/../..' . '/admin/traits/SettingHelper.php',
        'Pagup\\BetterRobots\\Traits\\Sitemap' => __DIR__ . '/../..' . '/admin/traits/Sitemap.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->classMap = ComposerStaticInit04e98c36e78e8c4dc39021769985089e::$classMap;

        }, null, ClassLoader::class);
    }
}
