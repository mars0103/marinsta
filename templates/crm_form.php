<?php
/** @var array|null $opportunity */
/** @var array $interactions */
$isEdit = $opportunity !== null;
$action = $isEdit ? url('/crm/' . $opportunity['id'] . '/edit') : url('/crm/new');
?>
<div class="page-header">
    <div>
        <h1><?= $isEdit ? h($opportunity['company_name']) : 'Nova oportunidade' ?></h1>
        <p>Dados da empresa, pontuação de qualificação e próxima ação.</p>
    </div>
    <?php if ($isEdit && !empty($opportunity['instagram_url'])): ?>
        <a href="<?= h($opportunity['instagram_url']) ?>" target="_blank" rel="noopener" class="btn btn-secondary">
            <?= icon('instagram') ?> Visitar Instagram
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="post" action="<?= h($action) ?>">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="company_name">Empresa</label>
            <input type="text" id="company_name" name="company_name" required
                   value="<?= h($opportunity['company_name'] ?? '') ?>" placeholder="Nome da empresa">
        </div>

        <div class="form-group">
            <label for="segment">Segmento</label>
            <select id="segment" name="segment">
                <?php $seg = $opportunity['segment'] ?? 'A'; ?>
                <?php foreach (Crm::SEGMENTS as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $seg === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="display:flex; gap:12px;">
            <div style="flex:1;">
                <label for="profile_url">Site institucional</label>
                <input type="text" id="profile_url" name="profile_url"
                       value="<?= h($opportunity['profile_url'] ?? '') ?>" placeholder="https://empresa.com.br">
            </div>
            <div style="flex:1;">
                <label for="instagram_url">Instagram</label>
                <input type="text" id="instagram_url" name="instagram_url"
                       value="<?= h($opportunity['instagram_url'] ?? '') ?>" placeholder="https://instagram.com/perfil">
            </div>
        </div>

        <div class="form-group" style="display:flex; gap:12px;">
            <div style="flex:1;">
                <label for="contact_name">Nome do contato</label>
                <input type="text" id="contact_name" name="contact_name" value="<?= h($opportunity['contact_name'] ?? '') ?>">
            </div>
            <div style="flex:1;">
                <label for="contact_role">Cargo</label>
                <input type="text" id="contact_role" name="contact_role" value="<?= h($opportunity['contact_role'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="source">Fonte (onde achou esse lead)</label>
            <input type="text" id="source" name="source" value="<?= h($opportunity['source'] ?? '') ?>" placeholder="Instagram, indicação, LinkedIn...">
        </div>

        <div class="form-group">
            <label for="problem_observed">Problema observado</label>
            <textarea id="problem_observed" name="problem_observed" placeholder="O que está confuso ou mal resolvido na apresentação digital deles"><?= h($opportunity['problem_observed'] ?? '') ?></textarea>
        </div>

        <div class="panel-gradient">
            <strong>Pontuação de qualificação</strong>
            <p class="form-hint" style="margin-top:4px;">0 = não se aplica, 1 = parcial, 2 = sim claramente. Priorize 7 a 10 pontos.</p>
            <?php foreach (Crm::SCORE_CRITERIA as $field => $label): ?>
                <div class="form-group" style="margin-top:12px;">
                    <label for="<?= $field ?>"><?= h($label) ?></label>
                    <select id="<?= $field ?>" name="<?= $field ?>" style="max-width:120px">
                        <?php $v = (int) ($opportunity[$field] ?? 0); ?>
                        <option value="0" <?= $v === 0 ? 'selected' : '' ?>>0</option>
                        <option value="1" <?= $v === 1 ? 'selected' : '' ?>>1</option>
                        <option value="2" <?= $v === 2 ? 'selected' : '' ?>>2</option>
                    </select>
                </div>
            <?php endforeach; ?>
            <?php if ($isEdit): ?>
                <p style="margin-top:12px;"><strong>Total atual: <?= (int) $opportunity['score_total'] ?>/10</strong></p>
            <?php endif; ?>
        </div>

        <div class="form-group" style="margin-top:20px;">
            <label for="status">Status no funil</label>
            <select id="status" name="status">
                <?php $st = $opportunity['status'] ?? 'pesquisado'; ?>
                <?php foreach (Crm::STATUSES as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $st === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" id="loss_reason_wrap" style="<?= ($opportunity['status'] ?? '') === 'perdido' ? '' : 'display:none' ?>">
            <label for="loss_reason">Motivo da perda</label>
            <textarea id="loss_reason" name="loss_reason"><?= h($opportunity['loss_reason'] ?? '') ?></textarea>
        </div>
        <script>
            document.getElementById('status').addEventListener('change', function () {
                document.getElementById('loss_reason_wrap').style.display = this.value === 'perdido' ? 'block' : 'none';
            });
        </script>

        <div class="form-group" style="display:flex; gap:12px;">
            <div style="flex:1;">
                <label for="proposed_value">Valor proposto (R$)</label>
                <input type="number" step="0.01" id="proposed_value" name="proposed_value" value="<?= h((string) ($opportunity['proposed_value'] ?? '')) ?>">
            </div>
            <div style="flex:1;">
                <label for="received_value">Recebido até agora (R$)</label>
                <input type="number" step="0.01" id="received_value" name="received_value" value="<?= h((string) ($opportunity['received_value'] ?? '0')) ?>">
            </div>
        </div>

        <div class="form-group" style="display:flex; gap:12px;">
            <div style="flex:1;">
                <label for="next_action">Próxima ação</label>
                <textarea id="next_action" name="next_action" rows="4" placeholder="Ex: mandar vídeo diagnóstico"><?= h($opportunity['next_action'] ?? '') ?></textarea>
            </div>
            <div style="flex:1;">
                <label for="next_action_at">Data</label>
                <input type="date" id="next_action_at" name="next_action_at" value="<?= h($opportunity['next_action_at'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="responsible">Responsável</label>
            <input type="text" id="responsible" name="responsible" value="<?= h($opportunity['responsible'] ?? 'Matheus') ?>">
        </div>

        <div class="form-group">
            <label for="outreach_script">Script da primeira mensagem</label>
            <textarea id="outreach_script" name="outreach_script" rows="8"
                      placeholder="Deixe vazio que eu gero automaticamente a partir da empresa e do problema observado"><?= h($opportunity['outreach_script'] ?? '') ?></textarea>
            <div style="display:flex; gap:8px; align-items:center; margin-top:8px;">
                <button type="button" class="btn btn-secondary btn-sm" id="copyOutreach">Copiar script</button>
                <span class="form-hint" style="margin:0;">Pode editar à vontade — o que estiver aqui é o que vai salvo.</span>
            </div>
        </div>
        <script>
            document.getElementById('copyOutreach').addEventListener('click', function () {
                var text = document.getElementById('outreach_script').value;
                navigator.clipboard.writeText(text).then(function () {
                    this.textContent = 'Copiado!';
                }.bind(this));
            });
        </script>

        <div class="form-group">
            <label for="consent_context">Contexto / consentimento do primeiro contato</label>
            <textarea id="consent_context" name="consent_context" placeholder="Como e quando foi feita a primeira abordagem"><?= h($opportunity['consent_context'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Salvar alterações' : 'Criar oportunidade' ?></button>
            <a href="<?= h(url('/crm')) ?>" class="btn btn-secondary">Voltar</a>
            <?php if ($isEdit): ?>
                <form method="post" action="<?= h(url('/crm/' . $opportunity['id'] . '/delete')) ?>"
                      onsubmit="return confirm('Excluir esta oportunidade?');" style="margin-left:auto">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger">Excluir</button>
                </form>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($isEdit): ?>
    <div class="card" style="margin-top:20px;">
        <h3 style="margin-top:0;">Histórico</h3>

        <form method="post" action="<?= h(url('/crm/' . $opportunity['id'] . '/interactions')) ?>" style="margin-bottom:16px;">
            <?= csrf_field() ?>
            <div class="form-group">
                <textarea name="notes" required placeholder="Registrar uma nota, resposta, reunião..." rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-secondary btn-sm">Adicionar</button>
        </form>

        <?php if (empty($interactions)): ?>
            <p class="form-hint">Nenhum registro ainda.</p>
        <?php else: ?>
            <?php foreach ($interactions as $i): ?>
                <div style="padding:10px 0; border-bottom:1px solid var(--border);">
                    <div class="form-hint" style="margin:0 0 4px;"><?= h(date('d/m/Y H:i', strtotime((string) $i['happened_at']))) ?></div>
                    <div><?= nl2br(h($i['notes'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
