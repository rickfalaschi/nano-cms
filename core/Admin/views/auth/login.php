<?php /** @var string $email; @var string|null $error */ ?>
<div class="auth__panel">
    <div class="auth__brand">
        <span class="auth__brand-mark">N</span>
    </div>

    <div class="auth__heading">
        <h1 class="auth__title">Bem-vindo ao Nano</h1>
        <p class="auth__subtitle">Acesse o painel para gerenciar o site</p>
    </div>

    <?php if ($error !== null): ?>
        <div class="auth__error"><?= e($error) ?></div>
    <?php endif; ?>

    <form class="auth__form" method="post" action="<?= e(admin_url('login')) ?>">
        <?= csrf_field() ?>

        <div class="field">
            <label class="field__label" for="email">Email</label>
            <div class="field__control">
                <input class="input" type="email" name="email" id="email" value="<?= e($email) ?>" autofocus required autocomplete="email" placeholder="voce@exemplo.com">
            </div>
        </div>

        <div class="field">
            <label class="field__label" for="password">Senha</label>
            <div class="field__control">
                <input class="input" type="password" name="password" id="password" required autocomplete="current-password" placeholder="••••••••">
            </div>
        </div>

        <button class="button auth__submit" type="submit">Entrar</button>
    </form>

    <p class="auth__footer">
        <a href="<?= e(admin_url('forgot-password')) ?>" style="color:inherit;text-decoration:underline;text-underline-offset:3px;">Esqueci minha senha</a>
    </p>
</div>
