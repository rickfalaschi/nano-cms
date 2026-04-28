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
        // Look up theme-defined options first, fall back to built-ins
        // (e.g. the global tracking/scripts page that ships with core).
        $pageDef = $this->app->config->optionGroup($key);
        if ($pageDef === null) {
            return Response::notFound("Options page '{$key}' não definida.");
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
