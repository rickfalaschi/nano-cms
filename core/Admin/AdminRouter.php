<?php

declare(strict_types=1);

namespace Nano\Admin;

use Nano\Admin\Controllers\AssetController;
use Nano\Admin\Controllers\AuthController;
use Nano\Admin\Controllers\DashboardController;
use Nano\Admin\Controllers\FormController;
use Nano\Admin\Controllers\ItemController;
use Nano\Admin\Controllers\MediaController;
use Nano\Admin\Controllers\OptionController;
use Nano\Admin\Controllers\PageController;
use Nano\Admin\Controllers\PasswordResetController;
use Nano\Admin\Controllers\TermController;
use Nano\Admin\Controllers\UserController;
use Nano\Config;
use Nano\Router;

final class AdminRouter
{
    public static function register(Router $router, Config $config): void
    {
        $prefix = rtrim((string) $config->get('admin.prefix', '/admin'), '/');

        $router->get($prefix . '/__static/css/{file}', [AssetController::class, 'css']);
        $router->get($prefix . '/__static/js/{file}', [AssetController::class, 'js']);
        $router->get($prefix . '/__static/fonts/{file}', [AssetController::class, 'font']);

        $router->any($prefix . '/login', [AuthController::class, 'login']);
        $router->post($prefix . '/logout', [AuthController::class, 'logout']);
        $router->any($prefix . '/forgot-password', [PasswordResetController::class, 'forgot']);
        $router->any($prefix . '/reset-password/{token}', [PasswordResetController::class, 'reset']);

        $router->get($prefix, [DashboardController::class, 'index']);
        $router->get($prefix . '/', [DashboardController::class, 'index']);

        $router->get($prefix . '/pages', [PageController::class, 'index']);
        $router->any($prefix . '/pages/{key}', [PageController::class, 'edit']);

        $router->get($prefix . '/items/{type}', [ItemController::class, 'index']);
        $router->any($prefix . '/items/{type}/new', [ItemController::class, 'create']);
        $router->any($prefix . '/items/{type}/{id}', [ItemController::class, 'edit']);
        $router->post($prefix . '/items/{type}/{id}/delete', [ItemController::class, 'delete']);

        $router->get($prefix . '/taxonomies/{taxonomy}', [TermController::class, 'index']);
        $router->any($prefix . '/taxonomies/{taxonomy}/new', [TermController::class, 'create']);
        $router->any($prefix . '/taxonomies/{taxonomy}/{id}', [TermController::class, 'edit']);
        $router->post($prefix . '/taxonomies/{taxonomy}/{id}/delete', [TermController::class, 'delete']);

        $router->get($prefix . '/media', [MediaController::class, 'index']);
        $router->post($prefix . '/media/upload', [MediaController::class, 'upload']);
        $router->get($prefix . '/media/{id}', [MediaController::class, 'show']);
        $router->post($prefix . '/media/{id}', [MediaController::class, 'update']);
        $router->post($prefix . '/media/{id}/delete', [MediaController::class, 'delete']);

        $router->get($prefix . '/users', [UserController::class, 'index']);
        $router->any($prefix . '/users/new', [UserController::class, 'create']);
        $router->any($prefix . '/users/{id}', [UserController::class, 'edit']);
        $router->post($prefix . '/users/{id}/delete', [UserController::class, 'delete']);

        $router->get($prefix . '/forms', [FormController::class, 'index']);
        $router->get($prefix . '/forms/{id}', [FormController::class, 'show']);
        $router->post($prefix . '/forms/{id}/recipients', [FormController::class, 'addRecipient']);
        $router->post($prefix . '/forms/{id}/recipients/{rid}/delete', [FormController::class, 'removeRecipient']);
        $router->post($prefix . '/forms/{id}/submissions/{sid}/delete', [FormController::class, 'deleteSubmission']);

        $router->any($prefix . '/options/{key}', [OptionController::class, 'edit']);
    }
}
