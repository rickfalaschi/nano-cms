<?php
/**
 * @var string $type
 * @var array $typeConfig
 * @var list<\Nano\Models\Item> $items
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= e((string)($typeConfig['label'] ?? $type)) ?></h1>
        <p class="page-header__subtitle"><?= e(count($items) === 1 ? '1 item' : count($items) . ' itens') ?> · tipo <code><?= e($type) ?></code></p>
    </div>
    <div class="page-header__actions">
        <a class="button" href="<?= e(admin_url('items/' . $type . '/new')) ?>">Novo <?= e((string)($typeConfig['label_singular'] ?? $type)) ?></a>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="empty">
        Nenhum item ainda. <a href="<?= e(admin_url('items/' . $type . '/new')) ?>">Criar o primeiro</a>.
    </div>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Título</th>
                <th>Status</th>
                <th>Atualizado em</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <div class="table__title"><?= e($item->title) ?></div>
                        <div class="muted">/<?= e((string)($typeConfig['slug'] ?? $type)) ?>/<?= e($item->slug) ?></div>
                    </td>
                    <td>
                        <span class="badge badge--<?= e($item->status) ?>"><?= e($item->status === 'published' ? 'Publicado' : 'Rascunho') ?></span>
                    </td>
                    <td class="muted"><?= e($item->updatedAt) ?></td>
                    <td class="table__actions">
                        <a class="button button--small button--ghost" href="<?= e(admin_url('items/' . $type . '/' . $item->id)) ?>">Editar</a>
                        <form method="post" action="<?= e(admin_url('items/' . $type . '/' . $item->id . '/delete')) ?>" data-confirm="Tem certeza que deseja excluir este item?" style="display:inline">
                            <?= csrf_field() ?>
                            <button class="button button--small button--danger" type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
