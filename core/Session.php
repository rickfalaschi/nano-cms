<?php

declare(strict_types=1);

namespace Nano;

final class Session
{
    public function __construct(array $config = [])
    {
        // Sessions are a no-op in CLI / unit tests.
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            $name = $config['name'] ?? 'nano_session';
            $lifetime = (int) ($config['lifetime'] ?? 60 * 60 * 24 * 7);

            session_name($name);
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public function csrfToken(): string
    {
        if (!$this->has('_csrf')) {
            $this->put('_csrf', bin2hex(random_bytes(32)));
        }
        return (string) $this->get('_csrf');
    }

    public function verifyCsrf(?string $token): bool
    {
        if ($token === null) {
            return false;
        }
        $current = $this->get('_csrf');
        return is_string($current) && hash_equals($current, $token);
    }
}
