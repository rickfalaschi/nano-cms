<?php

declare(strict_types=1);

namespace Nano\Admin\Controllers;

use Nano\FieldRenderer;
use Nano\Models\Item;
use Nano\Models\Term;
use Nano\Request;
use Nano\Response;

final class ItemController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;

        $type = (string) ($params['type'] ?? '');
        $config = $this->app->config->itemType($type);
        if ($config === null) {
            return Response::notFound("Item type '{$type}' not found.");
        }

        // Admin filters — read from query string so URLs are shareable
        // and persist across edit/delete redirects (when we add that).
        $search = trim((string) ($request->query['q'] ?? ''));
        $statusFilter = (string) ($request->query['status'] ?? '');
        $allowedStatuses = ['', 'published', 'draft'];
        if (!in_array($statusFilter, $allowedStatuses, true)) {
            $statusFilter = '';
        }

        $items = Item::listAdmin(
            $type,
            $statusFilter !== '' ? $statusFilter : null,
            $search !== '' ? $search : null
        );

        return $this->render('items/index', [
            'type' => $type,
            'typeConfig' => $config,
            'items' => $items,
            'search' => $search,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function create(Request $request, array $params): Response
    {
        return $this->editOrCreate($request, $params, null);
    }

    public function edit(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        return $this->editOrCreate($request, $params, $id);
    }

    private function editOrCreate(Request $request, array $params, ?int $id): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;

        $type = (string) ($params['type'] ?? '');
        $config = $this->app->config->itemType($type);
        if ($config === null) {
            return Response::notFound("Item type '{$type}' not found.");
        }

        $item = $id !== null ? Item::find($id) : null;
        if ($id !== null && $item === null) {
            return Response::notFound('Item not found.');
        }
        if ($item !== null && $item->type !== $type) {
            return Response::notFound('Item type mismatch.');
        }

        $fieldDefs = $this->app->config->resolveFields($config['fields'] ?? []);
        $taxonomies = (array) ($config['taxonomies'] ?? []);
        $customTemplates = (array) ($config['templates'] ?? []);
        // SEO is built-in only for item types that have a public page.
        // Embed-only types (has_page: false) don't need meta tags.
        $hasPage = ($config['has_page'] ?? true) !== false;
        $seoFields = $hasPage ? $this->app->config->seoFields() : [];

        if ($request->isPost()) {
            $csrf = $this->verifyCsrfOrFail($request);
            if ($csrf !== null) return $csrf;

            $title = trim((string) ($request->post['title'] ?? ''));
            $slug = trim((string) ($request->post['slug'] ?? ''));
            $template = (string) ($request->post['template'] ?? '');
            $status = ($request->post['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

            if ($title === '') {
                $this->app->session->flash('error', 'O título é obrigatório.');
                return Response::redirect($request->server['REQUEST_URI'] ?? admin_url("items/{$type}"));
            }
            if ($slug === '') {
                $slug = slugify($title);
            }
            $slug = slugify($slug);

            // Merge SEO defs so its values are persisted alongside content.
            $allDefs = array_merge($fieldDefs, $seoFields);
            $values = FieldRenderer::collect($allDefs, $request->post['fields'] ?? []);

            $publishedAt = $item?->publishedAt;
            if ($status === 'published' && $publishedAt === null) {
                $publishedAt = date('Y-m-d H:i:s');
            }

            $payload = [
                'title' => $title,
                'slug' => $this->ensureUniqueSlug($type, $slug, $item?->id),
                'template' => $template !== '' ? $template : null,
                'status' => $status,
                'fields' => $values,
                'published_at' => $publishedAt,
            ];

            if ($item === null) {
                $payload['type'] = $type;
                $item = Item::create($payload);
            } else {
                $item->save($payload);
            }

            foreach ($taxonomies as $taxonomy) {
                $termIds = array_map('intval', (array) ($request->post['terms'][$taxonomy] ?? []));
                $item->setTerms((string) $taxonomy, $termIds);
            }

            $this->app->session->flash('success', 'Item salvo.');
            return Response::redirect(admin_url("items/{$type}/{$item->id}"));
        }

        $termOptions = [];
        foreach ($taxonomies as $taxonomy) {
            $termOptions[(string) $taxonomy] = Term::all((string) $taxonomy);
        }
        $itemTerms = [];
        if ($item !== null) {
            foreach ($taxonomies as $taxonomy) {
                $itemTerms[(string) $taxonomy] = array_column(
                    array_map(fn(Term $t) => ['id' => $t->id], $item->terms((string) $taxonomy)),
                    'id'
                );
            }
        }

        return $this->render('items/edit', [
            'type' => $type,
            'typeConfig' => $config,
            'item' => $item,
            'fieldDefs' => $fieldDefs,
            'seoFields' => $seoFields,
            'taxonomies' => $taxonomies,
            'termOptions' => $termOptions,
            'itemTerms' => $itemTerms,
            'customTemplates' => $customTemplates,
        ]);
    }

    public function delete(Request $request, array $params): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;
        $csrf = $this->verifyCsrfOrFail($request);
        if ($csrf !== null) return $csrf;

        $type = (string) ($params['type'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $item = Item::find($id);
        if ($item !== null && $item->type === $type) {
            $item->delete();
            $this->app->session->flash('success', 'Item excluído.');
        }
        return Response::redirect(admin_url("items/{$type}"));
    }

    private function ensureUniqueSlug(string $type, string $slug, ?int $excludeId = null): string
    {
        $base = $slug;
        $counter = 1;
        while (true) {
            $sql = 'SELECT id FROM items WHERE type = ? AND slug = ?';
            $params = [$type, $slug];
            if ($excludeId !== null) {
                $sql .= ' AND id <> ?';
                $params[] = $excludeId;
            }
            $existing = $this->app->db->fetch($sql . ' LIMIT 1', $params);
            if ($existing === null) {
                return $slug;
            }
            $counter++;
            $slug = $base . '-' . $counter;
        }
    }
}
