<?php

declare(strict_types=1);

namespace Nano\Admin\Controllers;

use Nano\Mailer;
use Nano\Models\PasswordReset;
use Nano\Models\User;
use Nano\Request;
use Nano\Response;

final class PasswordResetController extends Controller
{
    private const MIN_PASSWORD = 6;

    public function forgot(Request $request): Response
    {
        if ($this->app->auth->check()) {
            return Response::redirect(admin_url(''));
        }

        $email = '';
        $error = null;

        if ($request->isPost()) {
            $csrf = $this->verifyCsrfOrFail($request);
            if ($csrf !== null) return $csrf;

            $email = trim((string) ($request->post['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email inválido.';
            } else {
                // Always show the same success message, regardless of whether
                // the email exists — prevents user enumeration.
                PasswordReset::purge();
                $user = User::findByEmail($email);
                if ($user !== null) {
                    try {
                        $token = PasswordReset::issueFor(
                            $user,
                            $request->ip(),
                            $request->userAgent()
                        );
                        $this->sendResetEmail($user, $token);
                    } catch (\Throwable $e) {
                        // Log internally; never expose to the user.
                        error_log('[Nano] Password reset email failed: ' . $e->getMessage());
                    }
                }

                return $this->render('auth/forgot-sent', [
                    'email' => $email,
                ], 'layout/auth');
            }
        }

        return $this->render('auth/forgot', [
            'email' => $email,
            'error' => $error,
        ], 'layout/auth');
    }

    public function reset(Request $request, array $params): Response
    {
        if ($this->app->auth->check()) {
            return Response::redirect(admin_url(''));
        }

        $token = (string) ($params['token'] ?? '');
        $reset = PasswordReset::findValidByToken($token);

        if ($reset === null) {
            return $this->render('auth/reset', [
                'invalid' => true,
                'token' => $token,
                'error' => null,
            ], 'layout/auth');
        }

        $user = $reset->user();
        if ($user === null) {
            return $this->render('auth/reset', [
                'invalid' => true,
                'token' => $token,
                'error' => null,
            ], 'layout/auth');
        }

        $error = null;

        if ($request->isPost()) {
            $csrf = $this->verifyCsrfOrFail($request);
            if ($csrf !== null) return $csrf;

            $password = (string) ($request->post['password'] ?? '');
            $confirm = (string) ($request->post['password_confirm'] ?? '');

            if (strlen($password) < self::MIN_PASSWORD) {
                $error = 'Senha deve ter pelo menos ' . self::MIN_PASSWORD . ' caracteres.';
            } elseif ($password !== $confirm) {
                $error = 'As senhas não conferem.';
            } else {
                $user->setPassword($password);
                $reset->consume();
                $this->app->session->flash('success', 'Senha redefinida. Faça login com a nova senha.');
                return Response::redirect(admin_url('login'));
            }
        }

        return $this->render('auth/reset', [
            'invalid' => false,
            'token' => $token,
            'user' => $user,
            'error' => $error,
        ], 'layout/auth');
    }

    private function sendResetEmail(User $user, string $token): void
    {
        $resetUrl = absolute_url('/admin/reset-password/' . $token);
        $siteName = (string) ($this->app->config->site('site.name') ?? 'Nano CMS');

        $emailView = new \Nano\View($this->app->config->path('core') . '/Admin/views');
        $html = $emailView->render('emails/reset-password', [
            'user'     => $user,
            'resetUrl' => $resetUrl,
            'siteName' => $siteName,
            'ttlMinutes' => (int) (PasswordReset::TTL_SECONDS / 60),
        ]);

        $mailer = Mailer::fromEnv();
        $mailer->send(
            to:        $user->email,
            subject:   'Redefinição de senha — ' . $siteName,
            htmlBody:  $html
        );
    }
}
