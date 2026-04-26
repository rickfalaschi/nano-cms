<?php

declare(strict_types=1);

namespace Nano\Admin\Controllers;

use Nano\Models\FormRecipient;
use Nano\Models\FormSubmission;
use Nano\Request;
use Nano\Response;

final class FormController extends Controller
{
    public function index(Request $request): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;

        $forms = (array) $this->app->config->site('forms', []);
        $stats = [];
        foreach ($forms as $id => $def) {
            $stats[(string) $id] = [
                'def'             => $def,
                'submission_count' => FormSubmission::count((string) $id),
                'recipient_count'  => count(FormRecipient::forForm((string) $id)),
            ];
        }

        return $this->render('forms/index', [
            'forms' => $stats,
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;

        $id = (string) ($params['id'] ?? '');
        $form = $this->app->config->site('forms.' . $id);
        if (!is_array($form)) {
            return Response::notFound('Formulário não encontrado em theme/site.json.');
        }

        return $this->render('forms/show', [
            'formId'      => $id,
            'form'        => $form,
            'recipients'  => FormRecipient::forForm($id),
            'submissions' => FormSubmission::recent($id, 100),
            'fields'      => (array) ($form['fields'] ?? []),
        ]);
    }

    public function addRecipient(Request $request, array $params): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;
        $csrf = $this->verifyCsrfOrFail($request);
        if ($csrf !== null) return $csrf;

        $id = (string) ($params['id'] ?? '');
        $email = trim((string) ($request->post['email'] ?? ''));
        $name = trim((string) ($request->post['name'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->app->session->flash('error', 'Email inválido.');
            return Response::redirect(admin_url('forms/' . $id));
        }

        $existing = $this->app->db->fetch(
            'SELECT id FROM form_recipients WHERE form_id = ? AND email = ? LIMIT 1',
            [$id, strtolower($email)]
        );
        if ($existing !== null) {
            $this->app->session->flash('error', 'Esse email já está cadastrado.');
            return Response::redirect(admin_url('forms/' . $id));
        }

        FormRecipient::create($id, $email, $name === '' ? null : $name);
        $this->app->session->flash('success', 'Destinatário adicionado.');
        return Response::redirect(admin_url('forms/' . $id));
    }

    public function removeRecipient(Request $request, array $params): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;
        $csrf = $this->verifyCsrfOrFail($request);
        if ($csrf !== null) return $csrf;

        $id = (string) ($params['id'] ?? '');
        $rid = (int) ($params['rid'] ?? 0);
        $r = FormRecipient::find($rid);
        if ($r !== null && $r->formId === $id) {
            $r->delete();
            $this->app->session->flash('success', 'Destinatário removido.');
        }
        return Response::redirect(admin_url('forms/' . $id));
    }

    public function deleteSubmission(Request $request, array $params): Response
    {
        $auth = $this->requireAuth($request);
        if ($auth !== null) return $auth;
        $csrf = $this->verifyCsrfOrFail($request);
        if ($csrf !== null) return $csrf;

        $id = (string) ($params['id'] ?? '');
        $sid = (int) ($params['sid'] ?? 0);
        $s = FormSubmission::find($sid);
        if ($s !== null && $s->formId === $id) {
            $s->delete();
            $this->app->session->flash('success', 'Preenchimento excluído.');
        }
        return Response::redirect(admin_url('forms/' . $id));
    }
}
