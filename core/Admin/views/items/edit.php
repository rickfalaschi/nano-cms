<?php
/**
 * @var string $type
 * @var array $typeConfig
 * @var \Nano\Models\Item|null $item
 * @var array $fieldDefs
 * @var array $taxonomies
 * @var array $termOptions
 * @var array $itemTerms
 * @var array $customTemplates
 */
$isNew = $item === null;
$title = $item?->title ?? '';
$slug = $item?->slug ?? '';
$status = $item?->status ?? 'draft';
$template = $item?->template ?? '';
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $isNew ? 'Novo ' . e((string)($typeConfig['label_singular'] ?? $type)) : e($title) ?></h1>
        <p class="page-header__subtitle">Tipo <code><?= e($type) ?></code></p>
    </div>
    <?php if (!$isNew && $status === 'published'): ?>
        <div class="page-header__actions">
            <a class="button button--ghost" href="<?= e($item->url()) ?>" target="_blank" rel="noopener">Ver no site</a>
        </div>
    <?php endif; ?>
</div>

<form method="post" class="form">
    <?= csrf_field() ?>

    <div class="form-meta">
        <div class="form-row">
            <div class="field" style="flex:2">
                <label class="field__label" for="title">Título <span class="field__required">*</span></label>
                <div class="field__control">
                    <input class="input" type="text" name="title" id="title" value="<?= e($title) ?>" required data-slug-source>
                </div>
            </div>
            <div class="field" style="flex:1">
                <label class="field__label" for="slug">Slug</label>
                <div class="field__control">
                    <input class="input" type="text" name="slug" id="slug" value="<?= e($slug) ?>" data-slug-target placeholder="gerado-automaticamente">
                </div>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label class="field__label" for="status">Status</label>
                <div class="field__control">
                    <select class="input input--select" name="status" id="status">
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Rascunho</option>
                        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Publicado</option>
                    </select>
                </div>
            </div>
            <?php if (!empty($customTemplates)): ?>
                <div class="field">
                    <label class="field__label" for="template">Template</label>
                    <div class="field__control">
                        <select class="input input--select" name="template" id="template">
                            <option value="">Padrão (<?= e((string)($typeConfig['template'] ?? '')) ?>)</option>
                            <?php foreach ($customTemplates as $tpl): ?>
                                <option value="<?= e((string)($tpl['key'] ?? '')) ?>" <?= $template === ($tpl['key'] ?? '') ? 'selected' : '' ?>><?= e((string)($tpl['label'] ?? $tpl['key'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($fieldDefs)): ?>
        <?php foreach ($fieldDefs as $field): ?>
            <?php
            $name = (string) ($field['name'] ?? '');
            $value = $item?->field($name);
            echo \Nano\FieldRenderer::render($field, $value, 'fields');
            ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($taxonomies)): ?>
        <div class="section-header">
            <h2 class="section-header__title">Taxonomias</h2>
        </div>
        <?php foreach ($taxonomies as $taxonomy): ?>
            <?php $taxConfig = $config->taxonomy((string) $taxonomy); ?>
            <div class="field">
                <label class="field__label"><?= e((string)($taxConfig['label'] ?? $taxonomy)) ?></label>
                <div class="field__control">
                    <?php if (empty($termOptions[$taxonomy])): ?>
                        <p class="muted">Nenhum termo cadastrado. <a href="<?= e(admin_url('taxonomies/' . $taxonomy . '/new')) ?>">Criar primeiro termo</a>.</p>
                    <?php else: ?>
                        <div style="display:flex;flex-wrap:wrap;gap:8px">
                            <?php foreach ($termOptions[$taxonomy] as $term): ?>
                                <?php $checked = in_array($term->id, $itemTerms[$taxonomy] ?? [], true); ?>
                                <label class="boolean-field" style="border:1px solid var(--color-border);padding:6px 10px">
                                    <input type="checkbox" name="terms[<?= e((string)$taxonomy) ?>][]" value="<?= e((string)$term->id) ?>" <?= $checked ? 'checked' : '' ?>>
                                    <span><?= e($term->name) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="form-actions">
        <button class="button" type="submit"><?= $isNew ? 'Criar' : 'Salvar' ?></button>
        <a class="button button--ghost" href="<?= e(admin_url('items/' . $type)) ?>">Cancelar</a>
        <span class="spacer"></span>
        <?php if (!$isNew): ?>
            <span class="muted">Atualizado em <?= e($item->updatedAt) ?></span>
        <?php endif; ?>
    </div>
</form>
