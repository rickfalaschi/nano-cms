<?php
/**
 * @var string $key
 * @var array $pageDef
 * @var array $fieldDefs
 * @var array $values
 */
$label = (string) ($pageDef['label'] ?? $key);
$description = (string) ($pageDef['description'] ?? '');
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= e($label) ?></h1>
        <p class="page-header__subtitle">
            <?php if ($description !== ''): ?>
                <?= e($description) ?>
            <?php else: ?>
                Opções do site · <code>options.<?= e($key) ?></code>
            <?php endif; ?>
        </p>
    </div>
</div>

<form method="post" class="form">
    <?= csrf_field() ?>

    <?php if (empty($fieldDefs)): ?>
        <div class="empty">Esta página de opções não tem campos definidos em <code>site.json</code>.</div>
    <?php endif; ?>

    <?php foreach ($fieldDefs as $field): ?>
        <?php
        $name = (string) ($field['name'] ?? '');
        $value = $values[$name] ?? null;
        echo \Nano\FieldRenderer::render($field, $value, 'fields');
        ?>
    <?php endforeach; ?>

    <?php if (!empty($fieldDefs)): ?>
        <div class="form-actions">
            <button class="button" type="submit">Salvar opções</button>
        </div>
    <?php endif; ?>
</form>
