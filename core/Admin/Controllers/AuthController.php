<?php

declare(strict_types=1);

namespace Nano\Admin\Controllers;

use Nano\Request;
use Nano\Response;

final class AuthController extends Controller
{
    public function login(Request $request): Response
    {
        if ($this->app->auth->check()) {
            return Response::redirect(admin_url(''));
        }

        $error = null;
        $email = '';

        if ($request->isPost()) {
            $csrf = $this->verifyCsrfOrFail($request);
            if ($csrf !== null) {
                return $csrf;
            }
            $email = (string) ($request->post['email'] ?? '');
            $password = (string) ($request->post['password'] ?? '');

            if ($this->app->auth->attempt($email, $password)) {
                $intended = $this->app->session->get('intended');
                $this->app->session->forget('intended');
                $target = is_string($intended) && $intended !== '' ? url($intended) : admin_url('');
                return Response::redirect($target);
            }
            $error = 'Email ou senha inválidos.';
        }

        return $this->render('auth/login', [
            'email' => $email,
            'error' => $error,
        ], 'layout/auth');
    }

    public function logout(Request $request): Response
    {
        $this->verifyCsrfOrFail($request);
        $this->app->auth->logout();
        return Response::redirect(admin_url('login'));
    }
}
