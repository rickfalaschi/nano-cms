<?php
/** @var array<string, array{label:string,count:int}> $stats */
/** @var array $pages */
/** @var array $taxonomies */
$siteName = (string) ($config->site('site.name') ?? 'Nano CMS');
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title">Painel</h1>
        <p class="page-header__subtitle">Visão geral do site <?= e($siteName) ?></p>
    </div>
    <div class="page-header__actions">
        <a class="button button--ghost" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">Ver site</a>
    </div>
</div>

<?php if (!empty($stats)): ?>
    <div class="section-header">
        <h2 class="section-header__title">Conteúdo</h2>
    </div>
    <div class="cards">
        <?php foreach ($stats as $type => $s): ?>
            <a class="card" href="<?= e(admin_url('items/' . $type)) ?>">
                <div class="card__label"><?= e($s['label']) ?></div>
                <div class="card__value"><?= e((string) $s['count']) ?></div>
                <div class="card__meta">
                    <span>Gerenciar</span>
                    <span class="card__meta-arrow">→</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($pages)): ?>
    <div class="section-header">
        <h2 class="section-header__title">Páginas</h2>
        <a class="muted" style="font-size:12px" href="<?= e(admin_url('pages')) ?>">Ver todas →</a>
    </div>
    <div class="cards">
        <?php foreach ($pages as $key => $page): ?>
            <a class="card" href="<?= e(admin_url('pages/' . $key)) ?>">
                <div class="card__label">Página</div>
                <h3 class="card__title"><?= e((string) ($page['label'] ?? $key)) ?></h3>
                <div class="card__meta">
                    <span>Editar</span>
                    <span class="card__meta-arrow">→</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($taxonomies)): ?>
    <div class="section-header">
        <h2 class="section-header__title">Taxonomias</h2>
    </div>
    <div class="cards">
        <?php foreach ($taxonomies as $tax => $def): ?>
            <a class="card" href="<?= e(admin_url('taxonomies/' . $tax)) ?>">
                <div class="card__label"><?= e((string) ($def['label'] ?? $tax)) ?></div>
                <h3 class="card__title">Termos</h3>
                <div class="card__meta">
                    <span>Gerenciar</span>
                    <span class="card__meta-arrow">→</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
