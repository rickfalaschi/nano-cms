<?php

declare(strict_types=1);

namespace Nano\Admin\Controllers;

use Nano\FieldRenderer;
use Nano\Models\Page;
use Nano\Request;
use Nano\Response;

final class PageController extends Controller
{
    public function index(Request $request): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;

        return $this->render('pages/index', [
            'pages' => $this->app->config->pages(),
        ]);
    }

    public function edit(Request $request, array $params): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;

        $key = (string) ($params['key'] ?? '');
        $config = $this->app->config->page($key);
        if ($config === null) {
            return Response::notFound("Page '{$key}' not found in site.json");
        }

        $page = Page::findByKey($key);
        if ($page === null) {
            $page = Page::upsert($key, (string) ($config['label'] ?? $key), []);
        }

        $fieldDefs = $this->app->config->resolveFields($config['fields'] ?? []);
        // Pages always have URLs, so SEO is built-in for every page.
        $seoFields = $this->app->config->seoFields();

        if ($request->isPost()) {
            $csrf = $this->verifyCsrfOrFail($request);
            if ($csrf !== null) return $csrf;

            // Merge defs so FieldRenderer collects values for both content
            // and SEO into the same `fields` JSON blob.
            $allDefs = array_merge($fieldDefs, $seoFields);
            $values = FieldRenderer::collect($allDefs, $request->post['fields'] ?? []);
            Page::upsert($key, (string) ($config['label'] ?? $key), $values);

            $this->app->session->flash('success', 'Página atualizada.');
            return Response::redirect(admin_url('pages/' . $key));
        }

        return $this->render('pages/edit', [
            'pageKey' => $key,
            'pageConfig' => $config,
            'page' => $page,
            'fieldDefs' => $fieldDefs,
            'seoFields' => $seoFields,
        ]);
    }
}
