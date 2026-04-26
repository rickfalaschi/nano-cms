<?php
/**
 * @var \Nano\Models\User $user
 * @var string $resetUrl
 * @var string $siteName
 * @var int $ttlMinutes
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Redefinir senha</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#0a0a0a;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f5f5f6;padding:32px 16px;">
  <tr>
    <td align="center">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="520" style="max-width:520px;width:100%;background:#ffffff;border:1px solid #e6e6e8;border-radius:12px;">

        <tr>
          <td style="padding:32px 32px 16px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="background:#0a0a0a;color:#ffffff;font-size:14px;font-weight:700;letter-spacing:-0.04em;width:32px;height:32px;text-align:center;border-radius:6px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;">N</td>
                <td style="padding-left:12px;font-size:14px;font-weight:600;letter-spacing:-0.015em;"><?= e($siteName) ?></td>
              </tr>
            </table>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px 8px;">
            <h1 style="margin:0;font-size:22px;font-weight:600;letter-spacing:-0.02em;line-height:1.2;color:#0a0a0a;">Redefinir sua senha</h1>
          </td>
        </tr>

        <tr>
          <td style="padding:8px 32px 16px;">
            <p style="margin:0;font-size:14px;line-height:1.55;color:#444;">
              Olá <?= e($user->name) ?>,
            </p>
            <p style="margin:12px 0 0;font-size:14px;line-height:1.55;color:#444;">
              Recebemos uma solicitação para redefinir a senha da sua conta no <?= e($siteName) ?>. Clique no botão abaixo para criar uma nova senha:
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:16px 32px 24px;">
            <a href="<?= e($resetUrl) ?>"
               style="display:inline-block;background:#0a0a0a;color:#ffffff;text-decoration:none;font-size:14px;font-weight:500;padding:12px 24px;border-radius:6px;letter-spacing:-0.005em;">
              Redefinir senha
            </a>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px 24px;">
            <p style="margin:0;font-size:13px;line-height:1.55;color:#666;">
              Ou cole este link no navegador:
            </p>
            <p style="margin:6px 0 0;font-size:12px;line-height:1.55;color:#0a0a0a;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;word-break:break-all;">
              <?= e($resetUrl) ?>
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:16px 32px 24px;border-top:1px solid #e6e6e8;">
            <p style="margin:0;font-size:12px;line-height:1.55;color:#888;">
              Este link expira em <strong><?= e((string) $ttlMinutes) ?> minutos</strong>. Se você não solicitou a redefinição, pode ignorar esta mensagem com segurança — sua senha atual continua válida.
            </p>
          </td>
        </tr>
      </table>

      <p style="margin:16px 0 0;font-size:11px;color:#999;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
        <?= e($siteName) ?> · Mensagem automática
      </p>
    </td>
  </tr>
</table>
</body>
</html>
