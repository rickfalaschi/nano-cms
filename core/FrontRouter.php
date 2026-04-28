<?php

declare(strict_types=1);

namespace Nano;

use Nano\Models\Item;
use Nano\Models\Page;
use Nano\Models\Term;

final class FrontRouter
{
    public static function register(Router $router, Config $config): void
    {
        $router->fallback(function (Request $request) use ($config) {
            return self::resolve($request, $config);
        });
    }

    public static function resolve(Request $request, Config $config): Response
    {
        if ($request->method !== 'GET') {
            return Response::notFound();
        }

        // UTM attribution: persist any utm_* query params to the session
        // so the next form submission can be attributed to the campaign
        // that brought the visitor in. No-op when no utm_* are present.
        Utm::captureFromRequest($request, App::instance()->session);

        $path = '/' . trim($request->path, '/');
        if ($path === '/') {
            $path = '';
        }

        // Built-in /sitemap.xml — covers pages, paged item types, and
        // term archives for taxonomies referenced by paged types. Checked
        // before page resolution so a page configured with url:/sitemap.xml
        // can't accidentally shadow it.
        if ($path === '/sitemap.xml') {
            return Response::xml(Sitemap::generate($config));
        }

        $page = Page::resolveByUrl($path === '' ? '/' : $path, $config);
        if ($page !== null) {
            return self::renderPage($page, $config);
        }

        $resolved = self::resolveContent($path, $config);
        if ($resolved !== null) {
            return $resolved;
        }

        return Response::notFound();
    }

    private static function renderPage(array $resolved, Config $config): Response
    {
        $page = $resolved['page'];
        $key = $resolved['key'];
        $template = (string) ($page['template'] ?? 'page.php');

        $view = new View($config->path('theme') . '/templates');
        $view->share('site', $config->site('site'));
        $view->share('config', $config);

        $context = new TemplateContext(
            type: 'page',
            key: $key,
            data: $page,
        );
        $context->record = Page::findByKey($key);

        return Response::html(self::renderWithFallback($view, $template, ['page' => $context], 'page.php'));
    }

    private static function resolveContent(string $path, Config $config): ?Response
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn($s) => $s !== ''));
        if (count($segments) === 0) {
            return null;
        }

        foreach ($config->itemTypes() as $type => $def) {
            // Item types with `has_page: false` are not publicly routable —
            // they're embed-only content (e.g. services rendered inside
            // /servicos). Skip them so URLs like /servico/foo cleanly 404
            // instead of falling back to a generic template.
            if (($def['has_page'] ?? true) === false) {
                continue;
            }

            $base = trim((string) ($def['slug'] ?? $type), '/');
            if ($segments[0] !== $base) {
                continue;
            }

            // Item types only route singles. To get an "archive" view (e.g.
            // /blog listing all posts), configure a regular page in
            // site.json — that's what handles /blog → page-blog.php today.
            // A bare /{slug} segment falls through here and either matches
            // a configured page or returns 404.
            if (count($segments) === 2) {
                return self::renderSingle($type, $def, $segments[1], $config);
            }
        }

        foreach ($config->taxonomies() as $taxonomy => $def) {
            $base = trim((string) ($def['slug'] ?? $taxonomy), '/');
            if ($segments[0] !== $base) {
                continue;
            }
            if (count($segments) === 2) {
                return self::renderTaxonomy($taxonomy, $def, $segments[1], $config);
            }
        }

        return null;
    }

    private static function renderSingle(string $type, array $def, string $slug, Config $config): ?Response
    {
        $item = Item::findBySlug($type, $slug);
        if ($item === null) {
            return null;
        }

        // Drafts are private. Anyone visiting the public URL of a draft gets
        // a 404 — same as if it didn't exist — UNLESS they're authenticated
        // in the admin, in which case the draft renders normally so editors
        // can preview their work before publishing.
        if ($item->status !== 'published' && !App::instance()->auth->check()) {
            return null;
        }

        $template = (string) ($def['template'] ?? "single-{$type}.php");
        if (!empty($item->template) && is_array($def['templates'] ?? null)) {
            foreach ($def['templates'] as $custom) {
                if (($custom['key'] ?? null) === $item->template && isset($custom['file'])) {
                    $template = (string) $custom['file'];
                    break;
                }
            }
        }

        $view = new View($config->path('theme') . '/templates');
        $view->share('site', $config->site('site'));
        $view->share('config', $config);

        $context = new TemplateContext(type: 'single', key: $type, data: $def);
        $context->record = $item;

        return Response::html(self::renderWithFallback($view, $template, ['item' => $context], 'single.php'));
    }

    private static function renderTaxonomy(string $taxonomy, array $def, string $slug, Config $config): ?Response
    {
        $term = Term::findBySlug($taxonomy, $slug);
        if ($term === null) {
            return null;
        }

        $template = (string) ($def['template'] ?? "taxonomy-{$taxonomy}.php");
        $view = new View($config->path('theme') . '/templates');
        $view->share('site', $config->site('site'));
        $view->share('config', $config);

        $context = new TemplateContext(type: 'taxonomy', key: $taxonomy, data: $def);
        $context->record = $term;
        $context->records = Item::byTerm($term->id);

        return Response::html(self::renderWithFallback($view, $template, ['term' => $context], 'taxonomy.php'));
    }

    private static function renderWithFallback(View $view, string $template, array $data, string $fallback): string
    {
        $primary = $view->basePath() . '/' . ltrim($template, '/');
        if (!str_ends_with($primary, '.php')) {
            $primary .= '.php';
        }
        if (is_file($primary)) {
            return $view->renderFile($primary, $data);
        }
        return $view->render($fallback, $data);
    }
}
