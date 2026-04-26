<?php

declare(strict_types=1);

namespace Nano\Admin\Controllers;

use Nano\FieldRenderer;
use Nano\Models\Option;
use Nano\Request;
use Nano\Response;

final class OptionController extends Controller
{
    public function edit(Request $request, array $params): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;

        $key = (string) ($params['key'] ?? '');
        $pageDef = $this->app->config->site('options.' . $key);
        if (!is_array($pageDef)) {
            return Response::notFound("Options page '{$key}' não definida em theme/site.json.");
        }

        $fieldDefs = $this->app->config->resolveFields((array) ($pageDef['fields'] ?? []));
        $values = Option::getGroup($key);

        if ($request->isPost()) {
            $csrf = $this->verifyCsrfOrFail($request);
            if ($csrf !== null) return $csrf;

            $newValues = FieldRenderer::collect($fieldDefs, $request->post['fields'] ?? []);
            Option::setGroup($key, $newValues);

            $this->app->session->flash('success', 'Opções salvas.');
            return Response::redirect(admin_url('options/' . $key));
        }

        return $this->render('options/edit', [
            'key'       => $key,
            'pageDef'   => $pageDef,
            'fieldDefs' => $fieldDefs,
            'values'    => $values,
        ]);
    }
}
