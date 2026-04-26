<?php /** @var array $pages */ ?>
<div class="page-header">
    <div>
        <h1 class="page-header__title">Páginas</h1>
        <p class="page-header__subtitle">Páginas estruturais do site, definidas em <code>theme/site.json</code></p>
    </div>
</div>

<?php if (empty($pages)): ?>
    <div class="empty">Nenhuma página configurada em <code>site.json</code>.</div>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Página</th>
                <th>URL</th>
                <th>Template</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pages as $key => $page): ?>
                <tr>
                    <td class="table__title"><?= e((string)($page['label'] ?? $key)) ?></td>
                    <td class="muted"><?= e(\Nano\Models\Page::pageUrl((string) $key, $page)) ?></td>
                    <td class="muted"><code><?= e((string)($page['template'] ?? '—')) ?></code></td>
                    <td class="table__actions">
                        <a class="button button--small button--ghost" href="<?= e(admin_url('pages/' . $key)) ?>">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
