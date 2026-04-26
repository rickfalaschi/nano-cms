<?php
/**
 * @var array<string, array{def:array, submission_count:int, recipient_count:int}> $forms
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title">Formulários</h1>
        <p class="page-header__subtitle">Definidos em <code>theme/site.json → forms</code></p>
    </div>
</div>

<?php if (empty($forms)): ?>
    <div class="empty">
        Nenhum formulário definido. Adicione uma seção <code>forms</code> em <code>theme/site.json</code>.
    </div>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Formulário</th>
                <th>Preenchimentos</th>
                <th>Destinatários</th>
                <th>Campos</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($forms as $id => $f): ?>
                <tr>
                    <td>
                        <div class="table__title"><?= e((string)($f['def']['label'] ?? $id)) ?></div>
                        <div class="muted" style="font-family:var(--font-mono);font-size:11px"><?= e($id) ?></div>
                    </td>
                    <td><?= e((string) $f['submission_count']) ?></td>
                    <td>
                        <?php if ($f['recipient_count'] === 0): ?>
                            <span class="badge badge--draft">nenhum</span>
                        <?php else: ?>
                            <?= e((string) $f['recipient_count']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="muted"><?= e((string) count((array)($f['def']['fields'] ?? []))) ?></td>
                    <td class="table__actions">
                        <a class="button button--small button--ghost" href="<?= e(admin_url('forms/' . $id)) ?>">Abrir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
