<?php
/** @var array $opportunities */
/** @var array $totals */
/** @var string $segmentFilter */
/** @var array|null $searchJob */
?>
<div class="page-header">
    <div>
        <h1>CRM</h1>
        <p>Funil de oportunidades comerciais — arraste os cards entre as colunas pra mudar o status.</p>
    </div>
    <a href="<?= h(url('/crm/new')) ?>" class="btn btn-primary">+ Nova oportunidade</a>
</div>

<div class="card" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap;">
    <div>
        <strong>Buscar leads automaticamente</strong>
        <p class="form-hint" style="margin:4px 0 0;">
            A IA pesquisa na web empresas/marcas com alta atividade comercial e sem site
            próprio verificável, e já cadastra com pontuação honesta. Roda em segundo plano.
        </p>
        <?php if ($searchJob): ?>
            <?php if ($searchJob['status'] === 'fila'): ?>
                <p class="form-hint" style="margin:8px 0 0; color:var(--blue-600);">Na fila, aguardando o próximo ciclo do cron...</p>
            <?php elseif ($searchJob['status'] === 'buscando'): ?>
                <p class="form-hint" style="margin:8px 0 0; color:var(--blue-600);">Buscando agora (desde <?= h(date('H:i', strtotime((string) $searchJob['started_at']))) ?>)... atualize a página em alguns minutos.</p>
            <?php elseif ($searchJob['status'] === 'concluido'): ?>
                <p class="form-hint" style="margin:8px 0 0; color:var(--ink-soft);">Última busca (<?= h(date('d/m H:i', strtotime((string) $searchJob['finished_at']))) ?>): <?= (int) $searchJob['found_count'] ?> oportunidade(s) nova(s).</p>
            <?php elseif ($searchJob['status'] === 'erro'): ?>
                <p class="form-hint" style="margin:8px 0 0; color:#c0392b;">Última busca falhou: <?= h((string) $searchJob['error_message']) ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <form method="post" action="<?= h(url('/crm/auto-search')) ?>" style="display:flex; gap:8px; align-items:center;">
        <?= csrf_field() ?>
        <select name="requested_count">
            <?php foreach ([3, 5, 7, 9] as $n): ?>
                <option value="<?= $n ?>" <?= $n === 5 ? 'selected' : '' ?>><?= $n ?> leads</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary"
            <?= $searchJob && in_array($searchJob['status'], ['fila', 'buscando'], true) ? 'disabled' : '' ?>>
            <?= icon('content') ?> Buscar automaticamente
        </button>
    </form>
</div>

<div class="card" style="margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px;">
        <div>
            <div class="form-hint" style="margin:0 0 4px;">Valor vendido (ganho)</div>
            <strong style="font-size:20px;">R$ <?= h(number_format((float) $totals['vendido'], 2, ',', '.')) ?></strong>
        </div>
        <div>
            <div class="form-hint" style="margin:0 0 4px;">Recebido</div>
            <strong style="font-size:20px;">R$ <?= h(number_format((float) $totals['recebido'], 2, ',', '.')) ?></strong>
        </div>
        <div>
            <div class="form-hint" style="margin:0 0 4px;">Contratos ganhos</div>
            <strong style="font-size:20px;"><?= (int) $totals['ganhos'] ?></strong>
        </div>
        <div>
            <div class="form-hint" style="margin:0 0 4px;">Oportunidades ativas</div>
            <strong style="font-size:20px;"><?= (int) $totals['ativas'] ?></strong>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:20px; display:flex; gap:12px; align-items:center;">
    <form method="get" action="<?= h(url('/crm')) ?>" style="display:flex; gap:10px; align-items:center;">
        <select name="segment" onchange="this.form.submit()">
            <option value="">Todos os segmentos</option>
            <?php foreach (Crm::SEGMENTS as $key => $label): ?>
                <option value="<?= h($key) ?>" <?= $segmentFilter === $key ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($segmentFilter): ?>
            <a href="<?= h(url('/crm')) ?>" class="btn btn-secondary btn-sm">Limpar filtro</a>
        <?php endif; ?>
    </form>
</div>

<?php
$byStatus = array_fill_keys(array_keys(Crm::STATUSES), []);
foreach ($opportunities as $o) {
    $byStatus[$o['status']][] = $o;
}
?>
<div class="kanban" id="kanban">
    <?php foreach (Crm::STATUSES as $statusKey => $statusLabel): ?>
        <?php [$colColor, $colBg] = Crm::statusPalette($statusKey); ?>
        <div class="kanban-col" data-status="<?= h($statusKey) ?>" style="--col:<?= $colColor ?>; --col-bg:<?= $colBg ?>;">
            <div class="kanban-col-head">
                <strong><?= h($statusLabel) ?></strong>
                <span class="kanban-count"><?= count($byStatus[$statusKey]) ?></span>
            </div>
            <div class="kanban-cards">
                <?php foreach ($byStatus[$statusKey] as $o): ?>
                    <?php
                    [$segFg, $segBg] = Crm::segmentPalette($o['segment']);
                    [$scFg, $scBg] = Crm::scorePalette((int) $o['score_total']);
                    ?>
                    <div class="kanban-card" draggable="true" data-id="<?= (int) $o['id'] ?>"
                         data-url="<?= h(url('/crm/' . $o['id'] . '/status')) ?>">
                        <a href="<?= h(url('/crm/' . $o['id'] . '/edit')) ?>" class="kanban-card-title"
                           title="<?= h($o['company_name']) ?>"><?= h(Crm::shortCompanyName($o['company_name'])) ?></a>
                        <div class="kanban-card-meta">
                            <span class="tag" style="background:<?= $segBg ?>; color:<?= $segFg ?>;"><?= h(Crm::segmentShortLabel($o['segment'])) ?></span>
                            <span class="tag" style="background:<?= $scBg ?>; color:<?= $scFg ?>;"><?= (int) $o['score_total'] ?>/10</span>
                        </div>
                        <?php if (!empty($o['next_action'])): ?>
                            <div class="kanban-card-next"><?= h(mb_strimwidth((string) $o['next_action'], 0, 70, '…')) ?></div>
                        <?php endif; ?>
                        <select class="kanban-move" aria-label="Mover para">
                            <?php foreach (Crm::STATUSES as $k => $l): ?>
                                <option value="<?= h($k) ?>" <?= $k === $o['status'] ? 'selected' : '' ?>><?= h($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<input type="hidden" id="kanbanCsrf" value="<?= h(csrf_token()) ?>">
<script>
    (function () {
        var csrf = document.getElementById('kanbanCsrf').value;

        function updateCounts() {
            document.querySelectorAll('.kanban-col').forEach(function (col) {
                col.querySelector('.kanban-count').textContent = col.querySelectorAll('.kanban-card').length;
            });
        }

        function moveCard(card, newStatus) {
            var fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('status', newStatus);
            return fetch(card.dataset.url, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j.ok) { throw new Error('falhou'); }
                    document.querySelector('.kanban-col[data-status="' + newStatus + '"] .kanban-cards').appendChild(card);
                    card.querySelector('.kanban-move').value = newStatus;
                    updateCounts();
                })
                .catch(function () { alert('Não consegui mover o card. Tenta de novo.'); });
        }

        document.querySelectorAll('.kanban-card').forEach(function (card) {
            card.addEventListener('dragstart', function (e) {
                e.dataTransfer.setData('text/plain', card.dataset.id);
                card.classList.add('dragging');
            });
            card.addEventListener('dragend', function () { card.classList.remove('dragging'); });
            card.querySelector('.kanban-move').addEventListener('change', function (e) {
                moveCard(card, e.target.value);
            });
        });

        document.querySelectorAll('.kanban-col').forEach(function (col) {
            col.addEventListener('dragover', function (e) { e.preventDefault(); col.classList.add('drop-target'); });
            col.addEventListener('dragleave', function () { col.classList.remove('drop-target'); });
            col.addEventListener('drop', function (e) {
                e.preventDefault();
                col.classList.remove('drop-target');
                var card = document.querySelector('.kanban-card[data-id="' + e.dataTransfer.getData('text/plain') + '"]');
                if (card && card.closest('.kanban-col') !== col) {
                    moveCard(card, col.dataset.status);
                }
            });
        });
    })();
</script>
