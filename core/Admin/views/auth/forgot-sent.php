<?php /** @var string $email */ ?>
<div class="auth__panel">
    <div class="auth__brand">
        <span class="auth__brand-mark">N</span>
    </div>

    <div class="auth__heading">
        <h1 class="auth__title">Verifique seu email</h1>
        <p class="auth__subtitle">
            Se uma conta com <strong><?= e($email) ?></strong> existir, enviamos um link para redefinir a senha. O link expira em 60 minutos.
        </p>
    </div>

    <div style="font-size:12px;color:var(--fg-subtle);background:var(--bg-alt);border:1px solid var(--line);padding:12px 14px;border-radius:6px;line-height:1.55">
        Não recebeu? Verifique a pasta de spam, confirme se digitou o email correto, ou
        <a href="<?= e(admin_url('forgot-password')) ?>" style="color:var(--fg);text-decoration:underline">tente novamente</a>.
    </div>

    <p class="auth__footer">
        <a href="<?= e(admin_url('login')) ?>" style="color:inherit;text-decoration:underline;text-underline-offset:3px;">← Voltar para login</a>
    </p>
</div>
