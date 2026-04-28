<?php
/**
 * @var string $formId
 * @var array $form
 * @var list<\Nano\Models\FormRecipient> $recipients
 * @var list<\Nano\Models\FormSubmission> $submissions
 * @var array $fields
 */
$label = (string) ($form['label'] ?? $formId);
?>
<div class="page-header">
    <div>
        <h1 class="page-header__title"><?= e($label) ?></h1>
        <p class="page-header__subtitle">Formulário <code><?= e($formId) ?></code> · ação <code>/forms/<?= e($formId) ?></code></p>
    </div>
    <div class="page-header__actions">
        <a class="button button--ghost" href="<?= e(admin_url('forms')) ?>">← Voltar</a>
    </div>
</div>

<div class="section-header">
    <h2 class="section-header__title">Destinatários</h2>
    <span class="muted" style="font-size:12px"><?= e(count($recipients) . (count($recipients) === 1 ? ' email' : ' emails') . ' recebem novos preenchimentos') ?></span>
</div>

<div class="form" style="max-width:none">
    <?php if (empty($recipients)): ?>
        <div class="flash flash--error">Nenhum destinatário cadastrado — preenchimentos não serão enviados por email.</div>
    <?php else: ?>
        <table class="table" style="margin-bottom:var(--s-4)">
            <thead>
                <tr><th>Email</th><th>Nome</th><th>Cadastrado em</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($recipients as $r): ?>
                    <tr>
                        <td><?= e($r->email) ?></td>
                        <td class="muted"><?= e($r->name ?? '—') ?></td>
                        <td class="muted"><?= e(date('d/m/Y H:i', strtotime($r->createdAt))) ?></td>
                        <td class="table__actions">
                            <form method="post" action="<?= e(admin_url('forms/' . $formId . '/recipients/' . $r->id . '/delete')) ?>" data-confirm="Remover este destinatário?" style="display:inline">
                                <?= csrf_field() ?>
                                <button class="button button--small button--danger" type="submit">Remover</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <form method="post" action="<?= e(admin_url('forms/' . $formId . '/recipients')) ?>">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="field" style="flex:2">
                <label class="field__label" for="email">Email <span class="field__required">*</span></label>
                <div class="field__control">
                    <input class="input" type="email" name="email" id="email" required placeholder="alguem@exemplo.com">
                </div>
            </div>
            <div class="field" style="flex:1">
                <label class="field__label" for="name">Nome (opcional)</label>
                <div class="field__control">
                    <input class="input" type="text" name="name" id="name" placeholder="Time de Vendas">
                </div>
            </div>
            <div class="field" style="flex:0 0 auto;align-self:flex-end">
                <button class="button" type="submit">Adicionar</button>
            </div>
        </div>
    </form>
</div>

<div class="section-header">
    <h2 class="section-header__title">Preenchimentos</h2>
    <span class="muted" style="font-size:12px"><?= e(count($submissions) . (count($submissions) === 1 ? ' registro' : ' registros')) ?></span>
</div>

<?php if (empty($submissions)): ?>
    <div class="empty">Nenhum preenchimento ainda.</div>
<?php else: ?>
    <div style="display:flex;flex-direction:column;gap:var(--s-3)">
        <?php foreach ($submissions as $s): ?>
            <details class="card" style="display:block;padding:0">
                <summary style="display:flex;justify-content:space-between;align-items:center;padding:var(--s-4) var(--s-5);cursor:pointer;list-style:none">
                    <div>
                        <div style="font-weight:500;font-size:14px">
                            <?php
                            // Show first non-empty value as preview (commonly name or email)
                            $preview = '';
                            foreach ($s->data as $val) {
                                if (is_string($val) && trim($val) !== '') {
                                    $preview = mb_substr(trim($val), 0, 60);
                                    break;
                                }
                            }
                            echo e($preview ?: '#' . $s->id);
                            ?>
                        </div>
                        <div class="muted" style="font-size:12px;margin-top:2px">
                            <?= e(date('d/m/Y H:i', strtotime($s->createdAt))) ?>
                            <?php if ($s->ip): ?> · IP <?= e($s->ip) ?><?php endif; ?>
                            <?php if ($s->emailStatus): ?>
                                · email
                                <?php if ($s->emailStatus === 'sent'): ?>
                                    <span class="badge badge--published">enviado</span>
                                <?php elseif ($s->emailStatus === 'partial'): ?>
                                    <span class="badge" style="border-color:rgba(196,41,43,0.4);color:#c4292b">parcial</span>
                                <?php elseif ($s->emailStatus === 'skipped'): ?>
                                    <span class="badge badge--draft">sem destinatários</span>
                                <?php else: ?>
                                    <span class="badge" style="border-color:rgba(196,41,43,0.4);color:#c4292b">falha</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="muted" style="font-size:12px">▾</span>
                </summary>

                <div style="padding:0 var(--s-5) var(--s-5);border-top:1px solid var(--line)">
                    <table class="table" style="border:0;border-radius:0;margin-top:var(--s-3);background:transparent">
                        <tbody>
                            <?php foreach ($fields as $field):
                                $name = (string) ($field['name'] ?? '');
                                if ($name === '') continue;
                                $value = $s->data[$name] ?? null;
                            ?>
                                <tr>
                                    <td style="width:160px;color:var(--fg-muted);vertical-align:top"><?= e((string)($field['label'] ?? $name)) ?></td>
                                    <td style="white-space:pre-wrap;word-break:break-word"><?= e((string) ($value ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if ($s->referer): ?>
                                <tr><td style="color:var(--fg-subtle)">Página de origem</td><td style="font-family:var(--font-mono);font-size:11px;word-break:break-all"><?= e($s->referer) ?></td></tr>
                            <?php endif; ?>

                            <?php
                            // UTM attribution — only rendered when at least one is set
                            $utmRows = [
                                'Campanha (utm_campaign)' => $s->utmCampaign,
                                'Origem (utm_source)'     => $s->utmSource,
                                'Mídia (utm_medium)'      => $s->utmMedium,
                                'Conteúdo (utm_content)'  => $s->utmContent,
                                'Termo (utm_term)'        => $s->utmTerm,
                            ];
                            $utmRows = array_filter($utmRows, fn($v) => $v !== null && $v !== '');
                            ?>
                            <?php if ($utmRows !== []): ?>
                                <tr><td colspan="2" style="padding-top:var(--s-3);border-top:1px solid var(--line);color:var(--ink-3);font-size:11.5px;text-transform:uppercase;letter-spacing:0.06em;font-weight:500">UTM</td></tr>
                                <?php foreach ($utmRows as $label => $val): ?>
                                    <tr>
                                        <td style="color:var(--fg-subtle)"><?= e($label) ?></td>
                                        <td style="font-family:var(--font-mono);font-size:11px;word-break:break-all"><?= e((string) $val) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <?php if ($s->sentTo): ?>
                                <tr><td style="color:var(--fg-subtle)">Enviado para</td><td style="font-family:var(--font-mono);font-size:11px"><?= e($s->sentTo) ?></td></tr>
                            <?php endif; ?>
                            <?php if ($s->emailError): ?>
                                <tr><td style="color:var(--fg-subtle)">Erro de envio</td><td style="font-family:var(--font-mono);font-size:11px;color:var(--danger)"><?= e($s->emailError) ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div style="display:flex;gap:var(--s-2);margin-top:var(--s-4);justify-content:flex-end">
                        <form method="post" action="<?= e(admin_url('forms/' . $formId . '/submissions/' . $s->id . '/delete')) ?>" data-confirm="Excluir este preenchimento?" style="margin:0">
                            <?= csrf_field() ?>
                            <button class="button button--small button--danger" type="submit">Excluir</button>
                        </form>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
