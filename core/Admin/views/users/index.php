<?php
/**
 * @var list<\Nano\Models\User> $users
 * @var int|null $currentUserId
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title">Usuários</h1>
        <p class="page-header__subtitle"><?= e(count($users) === 1 ? '1 usuário' : count($users) . ' usuários') ?> com acesso ao painel</p>
    </div>
    <div class="page-header__actions">
        <a class="button" href="<?= e(admin_url('users/new')) ?>">Novo usuário</a>
    </div>
</div>

<?php if (empty($users)): ?>
    <div class="empty">
        Nenhum usuário ainda. <a href="<?= e(admin_url('users/new')) ?>">Criar o primeiro</a>.
    </div>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Email</th>
                <th>Função</th>
                <th>Adicionado em</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <?php $isCurrent = $currentUserId !== null && $u->id === $currentUserId; ?>
                <tr>
                    <td>
                        <div class="table__title">
                            <?= e($u->name) ?>
                            <?php if ($isCurrent): ?>
                                <span class="badge badge--published" style="margin-left:6px">Você</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="muted"><?= e($u->email) ?></td>
                    <td class="muted"><?= e($u->role) ?></td>
                    <td class="muted"><?= e(date('d/m/Y', strtotime($u->createdAt))) ?></td>
                    <td class="table__actions">
                        <a class="button button--small button--ghost" href="<?= e(admin_url('users/' . $u->id)) ?>">Editar</a>
                        <?php if (!$isCurrent): ?>
                            <form method="post" action="<?= e(admin_url('users/' . $u->id . '/delete')) ?>" data-confirm="Excluir este usuário? A ação não pode ser desfeita." style="display:inline">
                                <?= csrf_field() ?>
                                <button class="button button--small button--danger" type="submit">Excluir</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
