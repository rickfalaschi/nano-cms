<?php
/**
 * @var string $taxonomy
 * @var array $taxonomyConfig
 * @var list<\Nano\Models\Term> $terms
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= e((string)($taxonomyConfig['label'] ?? $taxonomy)) ?></h1>
        <p class="page-header__subtitle"><?= e(count($terms) === 1 ? '1 termo' : count($terms) . ' termos') ?> · taxonomia <code><?= e($taxonomy) ?></code></p>
    </div>
    <div class="page-header__actions">
        <a class="button" href="<?= e(admin_url('taxonomies/' . $taxonomy . '/new')) ?>">Novo <?= e((string)($taxonomyConfig['label_singular'] ?? 'Termo')) ?></a>
    </div>
</div>

<?php if (empty($terms)): ?>
    <div class="empty">
        Nenhum termo ainda. <a href="<?= e(admin_url('taxonomies/' . $taxonomy . '/new')) ?>">Criar o primeiro</a>.
    </div>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Slug</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($terms as $term): ?>
                <tr>
                    <td class="table__title"><?= e($term->name) ?></td>
                    <td class="muted"><?= e($term->slug) ?></td>
                    <td class="table__actions">
                        <a class="button button--small button--ghost" href="<?= e(admin_url('taxonomies/' . $taxonomy . '/' . $term->id)) ?>">Editar</a>
                        <form method="post" action="<?= e(admin_url('taxonomies/' . $taxonomy . '/' . $term->id . '/delete')) ?>" data-confirm="Tem certeza?" style="display:inline">
                            <?= csrf_field() ?>
                            <button class="button button--small button--danger" type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
