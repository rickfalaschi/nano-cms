<?php

declare(strict_types=1);

namespace Nano;

use Nano\Admin\AdminRouter;

final class App
{
    private static ?App $instance = null;

    public Config $config;
    public Database $db;
    public Session $session;
    public Auth $auth;
    public Router $router;
    public Request $request;

    /** URL prefix when Nano is mounted under a subdirectory (e.g., "/nano"). Empty when at host root. */
    public string $basePath = '';

    public function __construct(Config $config)
    {
        $this->config = $config;
        self::$instance = $this;
    }

    public static function instance(): App
    {
        if (self::$instance === null) {
            throw new \RuntimeException('App not booted yet.');
        }
        return self::$instance;
    }

    public function register(): void
    {
        $dbConfig = $this->config->get('database');

        // Step 0 — theme must be installed (it brings site.json with the schema).
        // CLI is allowed to boot without a theme so commands like `install` and
        // `migrate` still work for setup.
        if (!$this->config->themeInstalled() && PHP_SAPI !== 'cli') {
            throw new SetupException(
                'No theme installed at theme/. Install a theme to provide site.json.',
                Installer::diagnose($dbConfig, $this->config->rootPath())
            );
        }

        // Step 1 — connect to DB. If it doesn't exist, try to create it.
        try {
            $this->db = new Database($dbConfig);
        } catch (\PDOException $e) {
            if (!Installer::isUnknownDatabaseError($e)) {
                throw new SetupException(
                    'Cannot connect to database: ' . $e->getMessage(),
                    Installer::diagnose($dbConfig, $this->config->rootPath())
                );
            }
            try {
                Installer::ensureDatabase($dbConfig);
                $this->db = new Database($dbConfig);
            } catch (\Throwable $e2) {
                throw new SetupException(
                    'Database does not exist and could not be created: ' . $e2->getMessage(),
                    Installer::diagnose($dbConfig, $this->config->rootPath())
                );
            }
        }

        $this->session = new Session($this->config->get('session'));
        $this->auth = new Auth($this->db, $this->session);

        // Step 2 — auto-install if migrations or initial user are missing.
        // CLI is trusted: it never gets a SetupException — the operator is
        // expected to run `./bin/nano install` (or whichever command they need)
        // and handle errors directly.
        if (!Installer::isInstalled($this->db)) {
            $email = (string) Env::get('INITIAL_USER_EMAIL', '');
            $password = (string) Env::get('INITIAL_USER_PASSWORD', '');

            if ($email !== '' && $password !== '') {
                try {
                    Installer::install($this);
                } catch (\Throwable $e) {
                    if (PHP_SAPI === 'cli') {
                        throw $e;
                    }
                    throw new SetupException($e->getMessage(), Installer::diagnose($dbConfig, $this->config->rootPath()));
                }
            } elseif (PHP_SAPI !== 'cli') {
                throw new SetupException(
                    'Setup required — schema or initial user missing.',
                    Installer::diagnose($dbConfig, $this->config->rootPath())
                );
            }
        }

        $this->basePath = $this->resolveBasePath();
        $this->request = Request::capture($this->basePath);
        $this->router = new Router();

        $this->router->get('/uploads/{path:.+}', [UploadController::class, 'serve']);
        $this->router->get('/theme/{path:.+}', [ThemeAssetController::class, 'serve']);
        $this->router->post('/forms/{id}', [PublicFormController::class, 'submit']);

        AdminRouter::register($this->router, $this->config);
        FrontRouter::register($this->router, $this->config);
    }

    /**
     * Resolve the URL prefix when Nano is mounted under a subdirectory.
     * Precedence: explicit `app.base_path` config → autodetect from SCRIPT_NAME → "".
     */
    private function resolveBasePath(): string
    {
        $configured = $this->config->get('app.base_path');
        if (is_string($configured)) {
            return $configured === '' ? '' : '/' . trim($configured, '/');
        }

        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        // index.php lives at the project root (single-tier layout). The
        // legacy `/public/index.php` suffix is checked too in case someone
        // is running an older install that still has the public/ subdir.
        foreach (['/index.php', '/public/index.php'] as $suffix) {
            if (str_ends_with($script, $suffix)) {
                $base = substr($script, 0, -strlen($suffix));
                return $base === '' ? '' : '/' . trim($base, '/');
            }
        }

        return '';
    }

    public function run(): void
    {
        $response = $this->router->dispatch($this->request);
        $response->send();
    }
}
