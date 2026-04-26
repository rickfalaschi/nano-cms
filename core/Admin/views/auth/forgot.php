<?php /** @var string $email; @var string|null $error */ ?>
<div class="auth__panel">
    <div class="auth__brand">
        <span class="auth__brand-mark">N</span>
    </div>

    <div class="auth__heading">
        <h1 class="auth__title">Esqueceu a senha?</h1>
        <p class="auth__subtitle">Informe seu email e enviaremos um link para criar uma nova senha.</p>
    </div>

    <?php if ($error !== null): ?>
        <div class="auth__error"><?= e($error) ?></div>
    <?php endif; ?>

    <form class="auth__form" method="post" action="<?= e(admin_url('forgot-password')) ?>">
        <?= csrf_field() ?>

        <div class="field">
            <label class="field__label" for="email">Email</label>
            <div class="field__control">
                <input class="input" type="email" name="email" id="email" value="<?= e($email) ?>" autofocus required autocomplete="email" placeholder="voce@exemplo.com">
            </div>
        </div>

        <button class="button auth__submit" type="submit">Enviar link</button>
    </form>

    <p class="auth__footer">
        <a href="<?= e(admin_url('login')) ?>" style="color:inherit;text-decoration:underline;text-underline-offset:3px;">← Voltar para login</a>
    </p>
</div>
