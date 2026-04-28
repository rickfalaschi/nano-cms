<?php
/**
 * @var array $form
 * @var string $formId
 * @var string $siteName
 * @var \Nano\Models\FormSubmission $submission
 * @var array $values
 * @var array $fields
 */
$label = (string) ($form['label'] ?? $formId);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Novo envio — <?= e($label) ?></title>
</head>
<body style="margin:0;padding:0;background:#f5f5f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#0a0a0a;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f5f5f6;padding:32px 16px;">
  <tr>
    <td align="center">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e6e6e8;border-radius:12px;">

        <tr>
          <td style="padding:24px 32px 8px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="background:#0a0a0a;color:#ffffff;font-size:14px;font-weight:700;letter-spacing:-0.04em;width:32px;height:32px;text-align:center;border-radius:6px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;">N</td>
                <td style="padding-left:12px;font-size:14px;font-weight:600;letter-spacing:-0.015em;"><?= e($siteName) ?></td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="padding:8px 32px 4px;">
            <p style="margin:0;font-size:12px;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.06em;font-weight:500;">Novo preenchimento</p>
            <h1 style="margin:6px 0 0;font-size:22px;font-weight:600;letter-spacing:-0.02em;line-height:1.2;color:#0a0a0a;"><?= e($label) ?></h1>
          </td>
        </tr>

        <tr>
          <td style="padding:24px 32px 8px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
              <?php foreach ($fields as $field):
                  $name = (string) ($field['name'] ?? '');
                  if ($name === '') continue;
                  $fieldLabel = (string) ($field['label'] ?? $name);
                  $value = (string) ($values[$name] ?? '');
              ?>
                <tr>
                  <td style="padding:10px 0;border-top:1px solid #e6e6e8;width:140px;vertical-align:top;font-size:12px;color:#6b6b6b;">
                    <?= e($fieldLabel) ?>
                  </td>
                  <td style="padding:10px 0;border-top:1px solid #e6e6e8;font-size:14px;color:#0a0a0a;white-space:pre-wrap;word-break:break-word;">
                    <?= $value === '' ? '<span style="color:#a3a3a3">—</span>' : e($value) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </table>
          </td>
        </tr>

        <?php
        // UTM block — only rendered when at least one is set. Mirrors the
        // admin panel layout: separator + small "ORIGEM" header + key/value
        // rows. Helps the recipient gauge lead source right from the inbox.
        $utmRows = [
            'Campanha' => $submission->utmCampaign,
            'Origem'   => $submission->utmSource,
            'Mídia'    => $submission->utmMedium,
            'Conteúdo' => $submission->utmContent,
            'Termo'    => $submission->utmTerm,
        ];
        $utmRows = array_filter($utmRows, fn($v) => $v !== null && $v !== '');
        ?>
        <?php if ($utmRows !== []): ?>
          <tr>
            <td style="padding:8px 32px 0;">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;border-top:1px solid #e6e6e8;">
                <tr>
                  <td colspan="2" style="padding:14px 0 6px;font-size:11px;color:#6b6b6b;text-transform:uppercase;letter-spacing:0.06em;font-weight:500;">
                    Origem · UTM
                  </td>
                </tr>
                <?php foreach ($utmRows as $label => $val): ?>
                  <tr>
                    <td style="padding:6px 0;width:140px;vertical-align:top;font-size:12px;color:#6b6b6b;">
                      <?= e($label) ?>
                    </td>
                    <td style="padding:6px 0;font-size:13px;color:#0a0a0a;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all;">
                      <?= e((string) $val) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </table>
            </td>
          </tr>
        <?php endif; ?>

        <tr>
          <td style="padding:16px 32px 24px;">
            <p style="margin:0;font-size:11px;color:#888;line-height:1.5;">
              Recebido em <?= e(date('d/m/Y H:i:s', strtotime($submission->createdAt))) ?>
              <?php if ($submission->ip): ?> · IP <?= e($submission->ip) ?><?php endif; ?>
              <?php if ($submission->referer): ?>
                · de <a href="<?= e($submission->referer) ?>" style="color:#0a0a0a;"><?= e($submission->referer) ?></a>
              <?php endif; ?>
            </p>
          </td>
        </tr>
      </table>

      <p style="margin:16px 0 0;font-size:11px;color:#999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
        <?= e($siteName) ?> · Envio automático pelo formulário <code style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:#fff;padding:1px 4px;border-radius:3px;border:1px solid #e6e6e8;"><?= e($formId) ?></code>
      </p>
    </td>
  </tr>
</table>
</body>
</html>
