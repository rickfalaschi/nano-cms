<?php
/**
 * @var \Nano\Models\User|null $user
 * @var array{name:string,email:string,role:string} $values
 * @var array<string,string> $errors
 * @var \Nano\Models\User|null $currentUser
 */
$isNew = $user === null;
$isSelf = $user !== null && $currentUser !== null && $user->id === $currentUser->id;
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $isNew ? 'Novo usuário' : e($user->name) ?></h1>
        <p class="page-header__subtitle">
            <?php if ($isNew): ?>
                Crie um novo usuário com acesso ao painel
            <?php else: ?>
                <?= $isSelf ? 'Sua conta' : 'Editar usuário' ?> · adicionado em <?= e(date('d/m/Y', strtotime($user->createdAt))) ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<form method="post" class="form">
    <?= csrf_field() ?>

    <div class="form-row">
        <div class="field">
            <label class="field__label" for="name">Nome <span class="field__required">*</span></label>
            <div class="field__control">
                <input class="input" type="text" name="name" id="name" value="<?= e($values['name']) ?>" required autofocus>
            </div>
            <?php if (!empty($errors['name'])): ?>
                <p class="field__help" style="color:var(--danger)"><?= e($errors['name']) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="email">Email <span class="field__required">*</span></label>
            <div class="field__control">
                <input class="input" type="email" name="email" id="email" value="<?= e($values['email']) ?>" required autocomplete="email">
            </div>
            <?php if (!empty($errors['email'])): ?>
                <p class="field__help" style="color:var(--danger)"><?= e($errors['email']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-row">
        <div class="field">
            <label class="field__label" for="password">
                Senha
                <?php if ($isNew): ?>
                    <span class="field__required">*</span>
                <?php else: ?>
                    <span class="muted" style="font-weight:400;font-size:11px">opcional · deixe em branco para manter</span>
                <?php endif; ?>
            </label>
            <div class="field__control">
                <input class="input" type="password" name="password" id="password" <?= $isNew ? 'required' : '' ?> minlength="8" autocomplete="new-password" placeholder="<?= $isNew ? 'Mínimo 8 caracteres' : '••••••••' ?>">
            </div>
            <?php if (!empty($errors['password'])): ?>
                <p class="field__help" style="color:var(--danger)"><?= e($errors['password']) ?></p>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field__label" for="role">Função</label>
            <div class="field__control">
                <select class="input input--select" name="role" id="role">
                    <option value="admin" <?= $values['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                    <option value="editor" <?= $values['role'] === 'editor' ? 'selected' : '' ?>>Editor</option>
                </select>
            </div>
            <p class="field__help">Atualmente todas as funções têm o mesmo acesso. Reservado para granularidade futura.</p>
        </div>
    </div>

    <div class="form-actions">
        <button class="button" type="submit"><?= $isNew ? 'Criar usuário' : 'Salvar alterações' ?></button>
        <a class="button button--ghost" href="<?= e(admin_url('users')) ?>">Cancelar</a>
        <span class="spacer"></span>
        <?php if (!$isNew && !$isSelf): ?>
            <form method="post" action="<?= e(admin_url('users/' . $user->id . '/delete')) ?>" data-confirm="Excluir este usuário? A ação não pode ser desfeita." style="margin:0">
                <?= csrf_field() ?>
                <button class="button button--small button--danger" type="submit">Excluir</button>
            </form>
        <?php endif; ?>
    </div>
</form>
