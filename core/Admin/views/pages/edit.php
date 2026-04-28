<?php
/**
 * @var string $pageKey
 * @var array $pageConfig
 * @var \Nano\Models\Page $page
 * @var array $fieldDefs
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= e((string)($pageConfig['label'] ?? $pageKey)) ?></h1>
        <p class="page-header__subtitle">Template <code><?= e((string)($pageConfig['template'] ?? '')) ?></code></p>
    </div>
    <div class="page-header__actions">
        <a class="button button--ghost" href="<?= e(\Nano\Models\Page::pageUrl($pageKey, $pageConfig)) ?>" target="_blank" rel="noopener">Ver no site</a>
    </div>
</div>

<form method="post" class="form">
    <?= csrf_field() ?>

    <?php if (empty($fieldDefs)): ?>
        <div class="empty">Esta página não possui campos personalizados em <code>site.json</code>.</div>
    <?php endif; ?>

    <?php foreach ($fieldDefs as $field): ?>
        <?php
        $name = (string) ($field['name'] ?? '');
        $value = $page->field($name);
        echo \Nano\FieldRenderer::render($field, $value, 'fields');
        ?>
    <?php endforeach; ?>

    <?php if (!empty($seoFields)): ?>
        <div class="form-seo">
            <div class="form-seo__head">
                <h2 class="form-seo__title">SEO &amp; compartilhamento</h2>
                <p class="form-seo__sub">Como esta página aparece em buscas, na aba do navegador e em previews compartilhados.</p>
            </div>
            <?php foreach ($seoFields as $field): ?>
                <?php
                $name = (string) ($field['name'] ?? '');
                $value = $page->field($name);
                echo \Nano\FieldRenderer::render($field, $value, 'fields');
                ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="form-actions">
        <button class="button" type="submit">Salvar</button>
        <span class="muted">Última atualização: <?= e($page->updatedAt ?? '—') ?></span>
    </div>
</form>
