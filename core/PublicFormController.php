<?php

declare(strict_types=1);

namespace Nano;

use Nano\Models\FormRecipient;
use Nano\Models\FormSubmission;

/**
 * Receives public form submissions at POST /forms/{id}.
 *
 * Validates against the form definition declared in `theme/site.json → forms`,
 * persists the data as JSON in `form_submissions`, emails the configured
 * recipients, and redirects back to the referrer with a flash status the
 * theme can pick up via `form_status($id)`.
 */
final class PublicFormController
{
    public function submit(Request $request, array $params): Response
    {
        $app = App::instance();
        $formId = (string) ($params['id'] ?? '');
        $form = self::findForm($app->config, $formId);

        // Always redirect back to where the form was submitted from
        $back = $this->backUrl($request);

        if ($form === null) {
            $app->session->put('_form_status_' . $formId, [
                'status' => 'error',
                'message' => 'Formulário não encontrado.',
            ]);
            return Response::redirect($back);
        }

        // CSRF (theme template MUST include csrf_field()).
        $token = (string) ($request->post['_csrf'] ?? '');
        if (!$app->session->verifyCsrf($token)) {
            $app->session->put('_form_status_' . $formId, [
                'status' => 'error',
                'message' => 'Sessão expirada. Recarregue a página e tente novamente.',
            ]);
            return Response::redirect($back);
        }

        // Honeypot — silently treat as success when the hidden field is filled
        if (!empty($request->post['_hp'])) {
            $app->session->put('_form_status_' . $formId, [
                'status' => 'success',
                'message' => 'Mensagem enviada.',
            ]);
            return Response::redirect($back);
        }

        $fields = (array) ($form['fields'] ?? []);
        [$values, $errors] = $this->validate($fields, $request->post);

        if ($errors !== []) {
            $app->session->put('_form_status_' . $formId, [
                'status' => 'error',
                'message' => 'Confira os campos destacados.',
                'errors'  => $errors,
                'values'  => $values,
            ]);
            return Response::redirect($back);
        }

        // Persist submission first — even if email fails, we keep the data.
        $submission = FormSubmission::create([
            'form_id'    => $formId,
            'data'       => $values,
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer'    => $request->server['HTTP_REFERER'] ?? null,
        ]);

        // Email the recipients configured in the admin panel.
        $this->dispatchEmail($formId, $form, $submission, $values);

        $app->session->put('_form_status_' . $formId, [
            'status' => 'success',
            'message' => (string) ($form['success_message'] ?? 'Mensagem enviada com sucesso.'),
        ]);

        return Response::redirect($back);
    }

    private function validate(array $fields, array $post): array
    {
        $values = [];
        $errors = [];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '') continue;

            $type = (string) ($field['type'] ?? 'text');
            $required = !empty($field['required']);
            $maxlength = (int) ($field['maxlength'] ?? ($type === 'textarea' ? 5000 : 1000));

            $raw = $post[$name] ?? null;
            $value = is_string($raw) ? trim($raw) : '';

            if ($required && $value === '') {
                $errors[$name] = 'Campo obrigatório.';
                $values[$name] = $value;
                continue;
            }

            if ($value !== '' && mb_strlen($value) > $maxlength) {
                $errors[$name] = "Máximo {$maxlength} caracteres.";
                $values[$name] = mb_substr($value, 0, $maxlength);
                continue;
            }

            if ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = 'Email inválido.';
            }

            if ($type === 'url' && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                $errors[$name] = 'URL inválida.';
            }

            if ($type === 'number' && $value !== '' && !is_numeric($value)) {
                $errors[$name] = 'Número inválido.';
            }

            if ($type === 'checkbox') {
                $value = !empty($raw) && $raw !== '0' ? '1' : '';
                if ($required && $value !== '1') {
                    $errors[$name] = 'Campo obrigatório.';
                }
            }

            $values[$name] = $value;
        }

        return [$values, $errors];
    }

    private function dispatchEmail(string $formId, array $form, FormSubmission $submission, array $values): void
    {
        $recipients = FormRecipient::forForm($formId);
        if ($recipients === []) {
            $submission->markEmail('skipped', 'no recipients configured');
            return;
        }

        $siteName = (string) (App::instance()->config->site('site.name') ?? 'Nano CMS');
        $subjectTpl = (string) ($form['subject'] ?? sprintf('[%s] Novo envio em %s', $siteName, (string) ($form['label'] ?? $formId)));
        $subject = self::interpolate($subjectTpl, $values);

        $emailView = new View(App::instance()->config->path('core') . '/Admin/views');
        $html = $emailView->render('emails/form-submission', [
            'form'       => $form,
            'formId'     => $formId,
            'siteName'   => $siteName,
            'submission' => $submission,
            'values'     => $values,
            'fields'     => (array) ($form['fields'] ?? []),
        ]);

        $mailer = Mailer::fromEnv();
        $sentTo = [];
        $errors = [];

        foreach ($recipients as $r) {
            try {
                $mailer->send($r->email, $subject, $html);
                $sentTo[] = $r->email;
            } catch (\Throwable $e) {
                $errors[] = $r->email . ': ' . $e->getMessage();
            }
        }

        if ($sentTo === []) {
            $submission->markEmail('failed', implode(' | ', $errors), null);
            error_log('[Nano] Form email failed: ' . implode(' | ', $errors));
        } else {
            $submission->markEmail(
                $errors === [] ? 'sent' : 'partial',
                $errors === [] ? null : implode(' | ', $errors),
                implode(', ', $sentTo)
            );
        }
    }

    private function backUrl(Request $request): string
    {
        $referer = (string) ($request->server['HTTP_REFERER'] ?? '');
        if ($referer !== '') {
            return $referer;
        }
        return base_path() . '/';
    }

    private static function findForm(Config $config, string $id): ?array
    {
        $forms = (array) $config->site('forms', []);
        return $forms[$id] ?? null;
    }

    private static function interpolate(string $template, array $values): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($values) {
            $key = $m[1];
            return isset($values[$key]) ? (string) $values[$key] : $m[0];
        }, $template) ?? $template;
    }
}
