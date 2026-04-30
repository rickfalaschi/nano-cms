<?php

declare(strict_types=1);

namespace Nano\Admin\Controllers;

use Nano\Models\User;
use Nano\Request;
use Nano\Response;

final class UserController extends Controller
{
    private const MIN_PASSWORD = 8;

    public function index(Request $request): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;

        return $this->render('users/index', [
            'users' => User::all(),
            'currentUserId' => $this->app->auth->user()?->id,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->editOrCreate($request, null);
    }

    public function edit(Request $request, array $params): Response
    {
        return $this->editOrCreate($request, (int) ($params['id'] ?? 0));
    }

    private function editOrCreate(Request $request, ?int $id): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;

        $user = $id !== null ? User::find($id) : null;
        if ($id !== null && $user === null) {
            return Response::notFound('Usuário não encontrado.');
        }

        $values = [
            'name'  => $user?->name ?? '',
            'email' => $user?->email ?? '',
            'role'  => $user?->role ?? 'admin',
        ];
        $errors = [];

        if ($request->isPost()) {
            $csrf = $this->verifyCsrfOrFail($request);
            if ($csrf !== null) return $csrf;

            $values['name']  = trim((string) ($request->post['name'] ?? ''));
            $values['email'] = trim((string) ($request->post['email'] ?? ''));
            $values['role']  = (string) ($request->post['role'] ?? 'admin');
            $password        = (string) ($request->post['password'] ?? '');

            if ($values['name'] === '') {
                $errors['name'] = 'Nome é obrigatório.';
            }
            if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Email inválido.';
            }

            $passwordRequired = $user === null;
            if ($passwordRequired && strlen($password) < self::MIN_PASSWORD) {
                $errors['password'] = 'Senha deve ter pelo menos ' . self::MIN_PASSWORD . ' caracteres.';
            } elseif (!$passwordRequired && $password !== '' && strlen($password) < self::MIN_PASSWORD) {
                $errors['password'] = 'Senha deve ter pelo menos ' . self::MIN_PASSWORD . ' caracteres.';
            }

            if (!isset($errors['email'])) {
                $existing = User::findByEmail($values['email']);
                if ($existing !== null && ($user === null || $existing->id !== $user->id)) {
                    $errors['email'] = 'Já existe um usuário com este email.';
                }
            }

            if ($errors === []) {
                if ($user === null) {
                    $user = User::create($values['email'], $password, $values['name'], $values['role']);
                } else {
                    $user->save([
                        'name'  => $values['name'],
                        'email' => $values['email'],
                        'role'  => $values['role'],
                    ]);
                    if ($password !== '') {
                        $user->setPassword($password);
                    }
                }
                $this->app->session->flash('success', 'Usuário salvo.');
                return Response::redirect(admin_url('users/' . $user->id));
            }
        }

        return $this->render('users/edit', [
            'user' => $user,
            'values' => $values,
            'errors' => $errors,
            'currentUser' => $this->app->auth->user(),
        ]);
    }

    public function delete(Request $request, array $params): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;
        $csrf = $this->verifyCsrfOrFail($request);
        if ($csrf !== null) return $csrf;

        $id = (int) ($params['id'] ?? 0);
        $user = User::find($id);
        $current = $this->app->auth->user();

        if ($user === null) {
            return Response::redirect(admin_url('users'));
        }

        if ($current !== null && $user->id === $current->id) {
            $this->app->session->flash('error', 'Você não pode excluir sua própria conta.');
            return Response::redirect(admin_url('users'));
        }

        if (User::count() <= 1) {
            $this->app->session->flash('error', 'Não é possível excluir o último usuário.');
            return Response::redirect(admin_url('users'));
        }

        $user->delete();
        $this->app->session->flash('success', 'Usuário excluído.');
        return Response::redirect(admin_url('users'));
    }
}
