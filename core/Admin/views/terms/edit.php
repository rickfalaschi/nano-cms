<?php
/**
 * @var string $taxonomy
 * @var array $taxonomyConfig
 * @var \Nano\Models\Term|null $term
 * @var list<\Nano\Models\Term> $parents
 */
$isNew = $term === null;
$hierarchical = !empty($taxonomyConfig['hierarchical']);
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= $isNew ? 'Novo termo' : e($term->name) ?></h1>
        <p class="page-header__subtitle">Taxonomia <code><?= e($taxonomy) ?></code></p>
    </div>
</div>

<form method="post" class="form">
    <?= csrf_field() ?>

    <div class="form-row">
        <div class="field" style="flex:2">
            <label class="field__label" for="name">Nome <span class="field__required">*</span></label>
            <div class="field__control">
                <input class="input" type="text" name="name" id="name" value="<?= e($term?->name ?? '') ?>" required data-slug-source>
            </div>
        </div>
        <div class="field" style="flex:1">
            <label class="field__label" for="slug">Slug</label>
            <div class="field__control">
                <input class="input" type="text" name="slug" id="slug" value="<?= e($term?->slug ?? '') ?>" data-slug-target>
            </div>
        </div>
    </div>

    <?php if ($hierarchical): ?>
        <div class="field">
            <label class="field__label" for="parent_id">Termo pai</label>
            <div class="field__control">
                <select class="input input--select" name="parent_id" id="parent_id">
                    <option value="">— sem pai —</option>
                    <?php foreach ($parents as $p): ?>
                        <?php if ($term !== null && $p->id === $term->id) continue; ?>
                        <option value="<?= e((string)$p->id) ?>" <?= $term && $term->parentId === $p->id ? 'selected' : '' ?>><?= e($p->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    <?php endif; ?>

    <div class="form-actions">
        <button class="button" type="submit"><?= $isNew ? 'Criar' : 'Salvar' ?></button>
        <a class="button button--ghost" href="<?= e(admin_url('taxonomies/' . $taxonomy)) ?>">Cancelar</a>
    </div>
</form>
