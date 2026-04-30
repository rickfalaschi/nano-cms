<?php
/**
 * @var bool $invalid
 * @var string $token
 * @var \Nano\Models\User|null $user
 * @var string|null $error
 */
?>
<div class="auth__panel">
    <div class="auth__brand">
        <span class="auth__brand-mark">N</span>
    </div>

    <?php if ($invalid): ?>
        <div class="auth__heading">
            <h1 class="auth__title">Link inválido</h1>
            <p class="auth__subtitle">Este link de redefinição expirou ou já foi usado.</p>
        </div>

        <a class="button auth__submit" href="<?= e(admin_url('forgot-password')) ?>">
            Solicitar novo link
        </a>

        <p class="auth__footer">
            <a href="<?= e(admin_url('login')) ?>" style="color:inherit;text-decoration:underline;text-underline-offset:3px;">← Voltar para login</a>
        </p>
    <?php else: ?>
        <div class="auth__heading">
            <h1 class="auth__title">Nova senha</h1>
            <p class="auth__subtitle">Definindo nova senha para <strong><?= e($user->email) ?></strong></p>
        </div>

        <?php if ($error !== null): ?>
            <div class="auth__error"><?= e($error) ?></div>
        <?php endif; ?>

        <form class="auth__form" method="post" action="<?= e(admin_url('reset-password/' . $token)) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label class="field__label" for="password">Nova senha</label>
                <div class="field__control">
                    <input class="input" type="password" name="password" id="password" required autofocus minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                </div>
            </div>

            <div class="field">
                <label class="field__label" for="password_confirm">Confirmar senha</label>
                <div class="field__control">
                    <input class="input" type="password" name="password_confirm" id="password_confirm" required minlength="8" autocomplete="new-password" placeholder="Repita a senha">
                </div>
            </div>

            <button class="button auth__submit" type="submit">Redefinir senha</button>
        </form>

        <p class="auth__footer">
            <a href="<?= e(admin_url('login')) ?>" style="color:inherit;text-decoration:underline;text-underline-offset:3px;">← Voltar para login</a>
        </p>
    <?php endif; ?>
</div>
