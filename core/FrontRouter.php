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

        $path = '/' . trim($request->path, '/');
        if ($path === '/') {
            $path = '';
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
            $base = trim((string) ($def['slug'] ?? $type), '/');
            if ($segments[0] !== $base) {
                continue;
            }

            if (count($segments) === 1) {
                return self::renderArchive($type, $def, $config);
            }
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

    private static function renderArchive(string $type, array $def, Config $config): Response
    {
        $template = (string) ($def['archive_template'] ?? "archive-{$type}.php");
        $view = new View($config->path('theme') . '/templates');
        $view->share('site', $config->site('site'));
        $view->share('config', $config);

        $items = Item::published($type);
        $context = new TemplateContext(type: 'archive', key: $type, data: $def);
        $context->records = $items;

        return Response::html(self::renderWithFallback($view, $template, ['archive' => $context], 'archive.php'));
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
