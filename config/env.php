<?php

declare(strict_types=1);

use Nano\Env;

/**
 * Application configuration.
 *
 * This file does NOT hold secrets. Real values come from the environment:
 *   1. Server-set variables (Apache SetEnv, hosting panel, Vercel, etc.)
 *   2. The `.env` file at the project root (gitignored)
 *   3. Defaults below
 *
 * To configure locally, copy `.env.example` to `.env` and edit it.
 */
return [
    'app' => [
        'name'      => Env::get('APP_NAME', 'Nano CMS'),
        'url'       => Env::get('APP_URL', ''),
        'debug'     => Env::get('APP_DEBUG', false),
        'timezone'  => Env::get('APP_TIMEZONE', 'UTC'),
        'locale'    => Env::get('APP_LOCALE', 'pt_BR'),
        // URL prefix for subdirectory installs (e.g. "/nano"). Null = autodetect.
        'base_path' => Env::get('APP_BASE_PATH', null),
    ],

    'database' => [
        'driver'    => Env::get('DB_DRIVER', 'mysql'),
        'host'      => Env::get('DB_HOST', 'localhost'),
        'port'      => (int) Env::get('DB_PORT', 3306),
        'database'  => Env::get('DB_DATABASE', 'nano'),
        'username'  => Env::get('DB_USERNAME', ''),
        'password'  => Env::get('DB_PASSWORD', ''),
        'charset'   => Env::get('DB_CHARSET', 'utf8mb4'),
        'collation' => Env::get('DB_COLLATION', 'utf8mb4_unicode_ci'),
    ],

    'session' => [
        'name'     => Env::get('SESSION_NAME', 'nano_session'),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', 60 * 60 * 24 * 7),
    ],

    'paths' => [
        'root'       => dirname(__DIR__),
        'core'       => dirname(__DIR__) . '/core',
        'theme'      => dirname(__DIR__) . '/theme',
        'config'     => dirname(__DIR__) . '/config',
        'storage'    => dirname(__DIR__) . '/storage',
        'uploads'    => dirname(__DIR__) . '/storage/uploads',
        'cache'      => dirname(__DIR__) . '/storage/cache',
        'public'     => dirname(__DIR__) . '/public',
        'migrations' => dirname(__DIR__) . '/migrations',
    ],

    'admin' => [
        'prefix' => Env::get('ADMIN_PREFIX', '/admin'),
    ],
];
