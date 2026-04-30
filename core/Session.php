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
                // Secure flag is conditional: only set on HTTPS requests.
                // Setting it unconditionally would break local-dev over
                // HTTP (the browser would refuse the cookie and auth
                // would never persist). The detection covers both the
                // direct-Apache/Nginx case (HTTPS server var) and the
                // reverse-proxy / Cloudflare case (X-Forwarded-Proto
                // header), since most production deploys terminate TLS
                // upstream and forward the original protocol info.
                'secure' => self::isHttps(),
            ]);
            session_start();
        }
    }

    /**
     * Best-effort HTTPS detection. Two signals:
     *   1. PHP's HTTPS server var — present and non-"off" when the web
     *      server itself terminated TLS (Apache mod_ssl, nginx ssl_*).
     *   2. X-Forwarded-Proto — set by reverse proxies, load balancers,
     *      and CDN/edge services (Cloudflare, AWS ALB, etc.) when they
     *      handle TLS upstream and pass plaintext to the origin.
     *
     * Either being true means the original request reached us over
     * HTTPS — safe to mark cookies Secure.
     */
    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return true;
        }
        return false;
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
