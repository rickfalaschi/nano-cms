<?php

declare(strict_types=1);

namespace Nano;

final class Autoloader
{
    private static string $rootPath = '';

    public static function register(string $rootPath): void
    {
        self::$rootPath = $rootPath;
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): void
    {
        if (!str_starts_with($class, 'Nano\\')) {
            return;
        }

        $relative = substr($class, strlen('Nano\\'));
        $path = self::$rootPath . '/core/' . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require_once $path;
        }
    }
}
