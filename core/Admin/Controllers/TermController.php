<?php

declare(strict_types=1);

namespace Nano\Admin\Controllers;

use Nano\Models\Term;
use Nano\Request;
use Nano\Response;

final class TermController extends Controller
{
    public function index(Request $request, array $params): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;

        $taxonomy = (string) ($params['taxonomy'] ?? '');
        $config = $this->app->config->taxonomy($taxonomy);
        if ($config === null) {
            return Response::notFound("Taxonomy '{$taxonomy}' not found.");
        }

        $terms = Term::all($taxonomy);
        return $this->render('terms/index', [
            'taxonomy' => $taxonomy,
            'taxonomyConfig' => $config,
            'terms' => $terms,
        ]);
    }

    public function create(Request $request, array $params): Response
    {
        return $this->editOrCreate($request, $params, null);
    }

    public function edit(Request $request, array $params): Response
    {
        return $this->editOrCreate($request, $params, (int) ($params['id'] ?? 0));
    }

    private function editOrCreate(Request $request, array $params, ?int $id): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;

        $taxonomy = (string) ($params['taxonomy'] ?? '');
        $config = $this->app->config->taxonomy($taxonomy);
        if ($config === null) {
            return Response::notFound("Taxonomy '{$taxonomy}' not found.");
        }

        $term = $id !== null ? Term::find($id) : null;
        if ($id !== null && $term === null) {
            return Response::notFound('Term not found.');
        }

        if ($request->isPost()) {
            $csrf = $this->verifyCsrfOrFail($request);
            if ($csrf !== null) return $csrf;

            $name = trim((string) ($request->post['name'] ?? ''));
            $slug = trim((string) ($request->post['slug'] ?? ''));
            $parentId = (int) ($request->post['parent_id'] ?? 0);

            if ($name === '') {
                $this->app->session->flash('error', 'O nome é obrigatório.');
                return Response::redirect($request->server['REQUEST_URI'] ?? admin_url("taxonomies/{$taxonomy}"));
            }
            $slug = slugify($slug !== '' ? $slug : $name);
            $slug = $this->ensureUniqueSlug($taxonomy, $slug, $term?->id);

            $payload = [
                'name' => $name,
                'slug' => $slug,
                'parent_id' => $parentId > 0 ? $parentId : null,
            ];

            if ($term === null) {
                $payload['taxonomy'] = $taxonomy;
                $term = Term::create($payload);
            } else {
                $term->save($payload);
            }

            $this->app->session->flash('success', 'Termo salvo.');
            return Response::redirect(admin_url("taxonomies/{$taxonomy}/{$term->id}"));
        }

        return $this->render('terms/edit', [
            'taxonomy' => $taxonomy,
            'taxonomyConfig' => $config,
            'term' => $term,
            'parents' => Term::all($taxonomy),
        ]);
    }

    public function delete(Request $request, array $params): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;
        $csrf = $this->verifyCsrfOrFail($request);
        if ($csrf !== null) return $csrf;

        $taxonomy = (string) ($params['taxonomy'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $term = Term::find($id);
        if ($term !== null && $term->taxonomy === $taxonomy) {
            $term->delete();
            $this->app->session->flash('success', 'Termo excluído.');
        }
        return Response::redirect(admin_url("taxonomies/{$taxonomy}"));
    }

    private function ensureUniqueSlug(string $taxonomy, string $slug, ?int $excludeId = null): string
    {
        $base = $slug;
        $counter = 1;
        while (true) {
            $sql = 'SELECT id FROM terms WHERE taxonomy = ? AND slug = ?';
            $params = [$taxonomy, $slug];
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
