<?php
/**
 * @var list<\Nano\Models\Media> $media
 * @var int $total
 * @var int $page
 * @var int $totalPages
 * @var bool $picker
 */
$uploadUrl = admin_url('media/upload');
$baseUrl = admin_url('media');
?>
<?php if (!$picker): ?>
    <div class="page-header">
        <div>
            <h1 class="page-header__title">Mídia</h1>
            <p class="page-header__subtitle"><?= e($total === 1 ? '1 arquivo' : $total . ' arquivos') ?> · imagens, SVG e PDF</p>
        </div>
    </div>
<?php else: ?>
    <div class="picker-header">
        <h2 class="picker-header__title">Selecionar mídia</h2>
        <p class="picker-header__subtitle">Faça upload ou escolha um arquivo existente.</p>
    </div>
<?php endif; ?>

<div class="media-uploader" data-media-uploader data-upload-url="<?= e($uploadUrl) ?>" data-csrf="<?= e(csrf_token()) ?>">
    <input type="file" multiple accept="image/*,application/pdf" data-media-uploader-input hidden>
    <div class="media-uploader__drop" data-media-uploader-drop>
        <strong>Solte arquivos aqui</strong>
        <span>ou</span>
        <button type="button" class="button button--small" data-media-uploader-trigger>Selecionar arquivos</button>
        <p class="muted" style="margin-top:8px">Imagens, SVG e PDF até 25 MB</p>
    </div>
    <ul class="media-uploader__queue" data-media-uploader-queue></ul>
</div>

<?php if (empty($media)): ?>
    <div class="empty" data-media-empty>Nenhum arquivo na biblioteca ainda.</div>
<?php endif; ?>

<ul class="media-grid" data-media-grid data-picker="<?= $picker ? '1' : '0' ?>">
    <?php foreach ($media as $m): ?>
        <li class="media-grid__item"
            data-media-id="<?= e((string) $m->id) ?>"
            data-media-url="<?= e($m->url('full')) ?>"
            data-media-thumb="<?= e($m->isImage() ? $m->url('thumb') : '') ?>"
            data-media-name="<?= e($m->originalName) ?>"
            data-media-mime="<?= e($m->mime) ?>"
            data-media-size="<?= e($m->humanSize()) ?>"
            data-media-dimensions="<?= e($m->width && $m->height ? $m->width . '×' . $m->height : '') ?>"
            data-media-alt="<?= e($m->alt ?? '') ?>"
            data-media-is-image="<?= $m->isImage() ? '1' : '0' ?>"
            data-media-created-at="<?= e($m->createdAt) ?>">
            <button type="button" class="media-grid__tile" data-media-open>
                <?php if ($m->isImage()): ?>
                    <img src="<?= e($m->url('thumb')) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <div class="media-grid__doc">
                        <span><?= e(strtoupper(pathinfo($m->filename, PATHINFO_EXTENSION))) ?></span>
                    </div>
                <?php endif; ?>
            </button>
            <span class="media-grid__name" title="<?= e($m->originalName) ?>"><?= e($m->originalName) ?></span>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($totalPages > 1): ?>
    <nav class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="pagination__link <?= $i === $page ? 'pagination__link--active' : '' ?>"
               href="<?= e($baseUrl . '?page=' . $i . ($picker ? '&picker=1' : '')) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<!-- Detail panel (full library only) -->
<?php if (!$picker): ?>
    <div class="media-panel" data-media-panel hidden>
        <div class="media-panel__overlay" data-media-panel-close></div>
        <aside class="media-panel__body">
            <header class="media-panel__header">
                <h2 class="media-panel__title" data-media-panel-name></h2>
                <button type="button" class="media-panel__close" data-media-panel-close aria-label="Fechar">×</button>
            </header>

            <div class="media-panel__preview" data-media-panel-preview></div>

            <dl class="media-panel__meta">
                <dt>Tipo</dt><dd data-media-panel-mime></dd>
                <dt>Tamanho</dt><dd data-media-panel-size></dd>
                <dt>Dimensões</dt><dd data-media-panel-dimensions></dd>
                <dt>Adicionado</dt><dd data-media-panel-created></dd>
                <dt>URL</dt>
                <dd><input type="text" class="input" data-media-panel-url readonly></dd>
            </dl>

            <form class="media-panel__form" data-media-panel-alt-form>
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="media-alt">Texto alternativo (alt)</label>
                    <div class="field__control">
                        <input class="input" type="text" id="media-alt" name="alt" data-media-panel-alt>
                    </div>
                </div>
                <button type="submit" class="button button--small">Salvar alt</button>
                <span class="muted" data-media-panel-alt-status></span>
            </form>

            <footer class="media-panel__actions">
                <form data-media-panel-delete-form data-confirm="Excluir este arquivo? A ação não pode ser desfeita.">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button--small button--danger">Excluir arquivo</button>
                </form>
            </footer>
        </aside>
    </div>
<?php endif; ?>
