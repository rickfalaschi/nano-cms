<?php

declare(strict_types=1);

use Nano\App;
use Nano\Config;
use Nano\Models\Item;
use Nano\Models\Term;
use Nano\Response;
use Nano\TemplateContext;

if (!function_exists('e')) {
    /**
     * Escape HTML — the default for ALL output in templates.
     */
    function e(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('app')) {
    function app(): App
    {
        return App::instance();
    }
}

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        $config = App::instance()->config;
        if ($key === null) {
            return $config;
        }
        return $config->get($key, $default);
    }
}

if (!function_exists('site')) {
    function site(?string $key = null, mixed $default = null): mixed
    {
        return App::instance()->config->site($key, $default);
    }
}

if (!function_exists('option')) {
    /**
     * Read a value from an options page (defined in `site.json → options`).
     * Supports dot paths into nested fields:
     *
     *   option('contact.email')                  // string
     *   option('contact.social.0.url')           // string from a repeater row
     *   option('footer.copyright', 'Default')    // with fallback
     */
    function option(string $path, mixed $default = null): mixed
    {
        return \Nano\Models\Option::getPath($path, $default);
    }
}

if (!function_exists('options')) {
    /**
     * Get the full values array for an options page key.
     * Useful when you want to iterate all fields:
     *
     *   foreach (options('footer') as $name => $value) { ... }
     */
    function options(string $key): array
    {
        return \Nano\Models\Option::getGroup($key);
    }
}

if (!function_exists('base_path')) {
    /**
     * URL prefix for subdirectory installations. Returns "" when Nano is at host root.
     */
    function base_path(): string
    {
        return App::instance()->basePath;
    }
}

if (!function_exists('asset')) {
    /**
     * Root-relative URL for a file in `public/` or served by the engine
     * (e.g., `/theme/...`, `/uploads/...`). Adds the install base path.
     */
    function asset(string $path): string
    {
        return base_path() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Root-relative URL for an internal route. Always includes the install
     * base path. Use `absolute_url()` when you need scheme + host included.
     */
    function url(string $path = '/'): string
    {
        $base = base_path();
        $clean = '/' . ltrim($path, '/');
        if ($clean === '/') {
            return $base === '' ? '/' : $base . '/';
        }
        return $base . $clean;
    }
}

if (!function_exists('absolute_url')) {
    /**
     * Absolute URL (scheme + host) for an internal route. Reads `app.url`
     * from env. Falls back to a relative URL when `app.url` is empty.
     */
    function absolute_url(string $path = '/'): string
    {
        $host = rtrim((string) App::instance()->config->get('app.url', ''), '/');
        return $host . url($path);
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = '/'): string
    {
        $prefix = '/' . trim((string) App::instance()->config->get('admin.prefix', '/admin'), '/');
        return base_path() . $prefix . '/' . ltrim($path, '/');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return App::instance()->session->csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('form_url')) {
    /**
     * Action URL for a public form. Use in templates:
     *   <form action="<?= e(form_url('contato')) ?>" method="post">
     */
    function form_url(string $id): string
    {
        return base_path() . '/forms/' . rawurlencode($id);
    }
}

if (!function_exists('form_status')) {
    /**
     * Read and clear the flash status for a form. Returns null when there's
     * no recent submission to report on.
     *
     * Shape:
     *   ['status' => 'success'|'error', 'message' => string,
     *    'errors' => array<string,string>, 'values' => array<string,string>]
     */
    function form_status(string $id): ?array
    {
        $key = '_form_status_' . $id;
        $app = \Nano\App::instance();
        $value = $app->session->get($key);
        if (!is_array($value)) return null;
        $app->session->forget($key);
        return $value;
    }
}

if (!function_exists('form_field')) {
    /**
     * Convenience helper that emits a hidden honeypot input. Bots tend to
     * fill in every field; humans can't see this one.
     */
    function form_honeypot(): string
    {
        return '<input type="text" name="_hp" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;height:0;width:0;opacity:0">';
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }
}

if (!function_exists('field')) {
    /**
     * Get a custom field value from the current template context.
     * Pass an explicit context to override the inferred one.
     */
    function field(string $name, mixed $default = null, ?TemplateContext $context = null): mixed
    {
        $context = $context ?? current_context();
        if ($context === null || $context->record === null) {
            return $default;
        }

        $record = $context->record;
        if (is_object($record) && method_exists($record, 'field')) {
            $value = $record->field($name);
            return $value ?? $default;
        }
        return $default;
    }
}

if (!function_exists('the_title')) {
    function the_title(?TemplateContext $context = null): string
    {
        $context = $context ?? current_context();
        if ($context === null || $context->record === null) {
            return '';
        }
        $record = $context->record;
        if (is_object($record) && property_exists($record, 'title')) {
            return (string) $record->title;
        }
        return '';
    }
}

if (!function_exists('the_slug')) {
    function the_slug(?TemplateContext $context = null): string
    {
        $context = $context ?? current_context();
        if ($context === null || $context->record === null) {
            return '';
        }
        $record = $context->record;
        if (is_object($record) && property_exists($record, 'slug')) {
            return (string) $record->slug;
        }
        return '';
    }
}

if (!function_exists('the_url')) {
    function the_url(?TemplateContext $context = null): string
    {
        $context = $context ?? current_context();
        if ($context === null || $context->record === null) {
            return '/';
        }
        $record = $context->record;
        if ($record instanceof Item) {
            return $record->url();
        }
        if ($record instanceof Term) {
            return $record->url();
        }
        return '/';
    }
}

if (!function_exists('items')) {
    /**
     * Query items for a given type. Returns array of Item objects.
     *
     * @param array{limit?:int, offset?:int, status?:string, term?:int, order?:string} $args
     * @return list<Item>
     */
    function items(string $type, array $args = []): array
    {
        return Item::query($type, $args);
    }
}

if (!function_exists('terms')) {
    /**
     * Get all terms for a taxonomy.
     *
     * @return list<Term>
     */
    function terms(string $taxonomy): array
    {
        return Term::all($taxonomy);
    }
}

if (!function_exists('the_terms')) {
    /**
     * Get terms for the current item (or a given item).
     *
     * @return list<Term>
     */
    function the_terms(string $taxonomy, ?TemplateContext $context = null): array
    {
        $context = $context ?? current_context();
        if ($context === null || !($context->record instanceof Item)) {
            return [];
        }
        return $context->record->terms($taxonomy);
    }
}

if (!function_exists('current_context')) {
    function current_context(): ?TemplateContext
    {
        $stack = $GLOBALS['_NANO_CONTEXT_STACK'] ?? [];
        return end($stack) ?: null;
    }
}

if (!function_exists('with_context')) {
    /**
     * Push a template context for the duration of a callable.
     * Used internally by the front renderer when looping over items.
     */
    function with_context(TemplateContext $context, callable $callback): mixed
    {
        $GLOBALS['_NANO_CONTEXT_STACK'][] = $context;
        try {
            return $callback();
        } finally {
            array_pop($GLOBALS['_NANO_CONTEXT_STACK']);
        }
    }
}

if (!function_exists('partial')) {
    function partial(string $name, array $data = []): void
    {
        $config = App::instance()->config;
        $base = $config->path('theme') . '/partials';
        $file = $base . '/' . ltrim($name, '/');
        if (!str_ends_with($file, '.php')) {
            $file .= '.php';
        }
        if (!is_file($file)) {
            throw new \RuntimeException("Partial not found: {$name}");
        }
        $merged = $data;
        (function (array $__data, string $__file): void {
            extract($__data, EXTR_SKIP);
            include $__file;
        })($merged, $file);
    }
}

if (!function_exists('get_header')) {
    function get_header(): void
    {
        $themePath = App::instance()->config->path('theme');
        $file = $themePath . '/partials/header.php';
        if (is_file($file)) {
            include $file;
        }
    }
}

if (!function_exists('get_footer')) {
    function get_footer(): void
    {
        $themePath = App::instance()->config->path('theme');
        $file = $themePath . '/partials/footer.php';
        if (is_file($file)) {
            include $file;
        }
    }
}

if (!function_exists('image_url')) {
    /**
     * Resolve any image-field value to a URL.
     *
     * Accepts:
     *   - integer / numeric string → media ID, looked up in the media table
     *   - absolute URL or root-relative path → returned as-is
     *   - bare filename → prefixed with /uploads/
     *   - array with 'url' or 'filename' key → legacy fallback
     *   - null/empty → ''
     */
    function image_url(mixed $value, string $size = 'full'): string
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return '';
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $media = \Nano\Models\Media::find((int) $value);
            return $media === null ? '' : $media->url($size);
        }

        if (is_string($value)) {
            if (
                str_starts_with($value, 'http://')
                || str_starts_with($value, 'https://')
                || str_starts_with($value, 'data:')
            ) {
                return $value;
            }
            if (str_starts_with($value, '/')) {
                $base = base_path();
                if ($base !== '' && !str_starts_with($value, $base . '/') && $value !== $base) {
                    return $base . $value;
                }
                return $value;
            }
            return base_path() . '/uploads/' . ltrim($value, '/');
        }

        if (is_array($value)) {
            if (isset($value['id']) && is_numeric($value['id'])) {
                return image_url((int) $value['id'], $size);
            }
            if (isset($value['url']) && is_string($value['url'])) {
                return $value['url'];
            }
            if (isset($value['filename']) && is_string($value['filename'])) {
                return '/uploads/' . ltrim($value['filename'], '/');
            }
        }

        return '';
    }
}

if (!function_exists('media')) {
    /**
     * Look up a Media record by id. Returns null when not found.
     */
    function media(mixed $id): ?\Nano\Models\Media
    {
        if ($id === null || $id === '' || $id === 0 || $id === '0') return null;
        if (!is_numeric($id)) return null;
        return \Nano\Models\Media::find((int) $id);
    }
}

if (!function_exists('image_alt')) {
    /**
     * Resolve an image-field value to its stored alt text. Empty string when
     * the value is not a media id or no alt was saved.
     */
    function image_alt(mixed $value, string $fallback = ''): string
    {
        $m = media($value);
        return $m !== null && $m->alt !== null ? $m->alt : $fallback;
    }
}

if (!function_exists('slugify')) {
    function slugify(string $value): string
    {
        $value = trim($value);
        $value = mb_strtolower($value, 'UTF-8');
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
        $value = trim($value, '-');
        return $value === '' ? 'item' : $value;
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$values): never
    {
        echo '<pre style="background:#000;color:#0f0;padding:1rem;font-family:monospace">';
        foreach ($values as $value) {
            var_dump($value);
        }
        echo '</pre>';
        exit;
    }
}
