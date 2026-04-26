<?php

declare(strict_types=1);

namespace Nano\Admin\Controllers;

use Nano\App;
use Nano\Request;
use Nano\Response;
use Nano\View;

abstract class Controller
{
    protected App $app;
    protected View $view;

    public function __construct()
    {
        $this->app = App::instance();
        $this->view = new View($this->app->config->path('core') . '/Admin/views');
        $this->view->share('config', $this->app->config);
        $this->view->share('app', $this->app);
        $this->view->share('user', $this->app->auth->user());
        $this->view->share('flash_success', $this->app->session->getFlash('success'));
        $this->view->share('flash_error', $this->app->session->getFlash('error'));
    }

    protected function render(string $template, array $data = [], string $layout = 'layout/main'): Response
    {
        $content = $this->view->render($template, $data);
        $body = $this->view->render($layout, array_merge($data, ['_content' => $content]));
        return Response::html($body);
    }

    protected function requireAuth(Request $request): ?Response
    {
        if (!$this->app->auth->check()) {
            $this->app->session->put('intended', $request->path);
            return Response::redirect(admin_url('login'));
        }
        return null;
    }

    protected function verifyCsrfOrFail(Request $request): ?Response
    {
        if (!$request->isPost()) {
            return null;
        }
        $token = (string) ($request->post['_csrf'] ?? '');
        if (!$this->app->session->verifyCsrf($token)) {
            return Response::html('CSRF token mismatch.', 419);
        }
        return null;
    }
}
