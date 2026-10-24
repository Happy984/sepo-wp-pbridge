<?php
namespace SepoWB;

defined('ABSPATH') || exit;

final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register([__CLASS__, 'load']);
    }

    public static function load(string $class): void
    {
        if (strncmp($class, 'SepoWB\\', 7) !== 0) {
            return;
        }
        $relative = substr($class, 7);
        $file     = SEPO_WB_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
}
