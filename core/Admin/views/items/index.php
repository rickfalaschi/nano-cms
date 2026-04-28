<?php
/**
 * @var string $type
 * @var array $typeConfig
 * @var list<\Nano\Models\Item> $items
 * @var string $search
 * @var string $statusFilter
 */
$hasFilters = $search !== '' || $statusFilter !== '';
$singularLabel = (string) ($typeConfig['label_singular'] ?? $type);
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= e((string)($typeConfig['label'] ?? $type)) ?></h1>
        <p class="page-header__subtitle"><?= e(count($items) === 1 ? '1 item' : count($items) . ' itens') ?> · tipo <code><?= e($type) ?></code></p>
    </div>
    <div class="page-header__actions">
        <a class="button" href="<?= e(admin_url('items/' . $type . '/new')) ?>">Novo <?= e($singularLabel) ?></a>
    </div>
</div>

<form class="tbl-toolbar" method="get" action="<?= e(admin_url('items/' . $type)) ?>">
    <label class="tbl-search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="8"/>
            <path d="M21 21l-4.35-4.35"/>
        </svg>
        <input
            type="text"
            name="q"
            value="<?= e($search) ?>"
            placeholder="Buscar por título ou slug…"
            autocomplete="off">
    </label>

    <div class="tbl-filters">
        <label class="tbl-pill">
            <span class="tbl-pill__label">Status</span>
            <select name="status" class="tbl-pill__select" data-autosubmit>
                <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Todos</option>
                <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Publicados</option>
                <option value="draft"     <?= $statusFilter === 'draft'     ? 'selected' : '' ?>>Rascunhos</option>
            </select>
        </label>

        <?php if ($hasFilters): ?>
            <a class="tbl-clear" href="<?= e(admin_url('items/' . $type)) ?>" title="Limpar filtros">
                Limpar
            </a>
        <?php endif; ?>
    </div>
</form>

<?php if (empty($items)): ?>
    <div class="empty">
        <?php if ($hasFilters): ?>
            Nenhum item encontrado para esses filtros. <a href="<?= e(admin_url('items/' . $type)) ?>">Limpar filtros</a>.
        <?php else: ?>
            Nenhum item ainda. <a href="<?= e(admin_url('items/' . $type . '/new')) ?>">Criar o primeiro</a>.
        <?php endif; ?>
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
                        <?php if (($typeConfig['has_page'] ?? true) !== false): ?>
                            <div class="muted">/<?= e((string)($typeConfig['slug'] ?? $type)) ?>/<?= e($item->slug) ?></div>
                        <?php endif; ?>
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
