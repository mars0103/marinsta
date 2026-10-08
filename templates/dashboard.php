<?php
/** @var array|null $account */
/** @var array $automations */
?>
<div class="page-header">
    <div>
        <h1>Automações</h1>
        <p>Responda comentários automaticamente com base em palavras-chave.</p>
    </div>
    <?php if ($account): ?>
        <a href="<?= h(url('/automations/new')) ?>" class="btn btn-primary">+ Nova automação</a>
    <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px; display:flex; align-items:center; justify-content:space-between;">
    <div>
        <?php if ($account): ?>
            <span class="status-pill status-connected">● Conectado</span>
            <strong style="margin-left:8px;">@<?= h($account['username']) ?></strong>
        <?php else: ?>
            <span class="status-pill status-disconnected">● Nenhuma conta conectada</span>
        <?php endif; ?>
    </div>
    <a href="<?= h(url('/connect')) ?>" class="btn btn-secondary">
        <?= $account ? 'Reconectar / trocar conta' : 'Conectar Instagram' ?>
    </a>
</div>

<?php if (!$account): ?>
    <div class="card empty-state">
        <h2>Conecte sua conta do Instagram</h2>
        <p>Clique em "Conectar Instagram" acima para autorizar o Marinsta a ler comentários e responder por você.</p>
    </div>
<?php elseif (empty($automations)): ?>
    <div class="card empty-state">
        <h2>Nenhuma automação ainda</h2>
        <p>Crie a primeira automação para responder comentários automaticamente.</p>
    </div>
<?php else: ?>
    <?php foreach ($automations as $a): ?>
        <div class="card automation-card">
            <div>
                <h3><?= h($a['name']) ?></h3>
                <div class="automation-meta">
                    <span class="tag">Comentário no Feed</span>
                    <?php if ($a['send_dm']): ?>
                        <span class="tag">+ DM</span>
                    <?php endif; ?>
                    <?php if ($a['target_scope'] === 'next'): ?>
                        <span class="tag"><?= $a['target_media_id'] ? 'próxima publicação (travada)' : 'aguardando próxima publicação' ?></span>
                    <?php elseif ($a['target_scope'] === 'specific'): ?>
                        <span class="tag">publicação específica</span>
                    <?php endif; ?>
                    <span>gatilho: <?= h($a['match_type'] === 'all' ? 'todas as palavras' : 'qualquer palavra') ?></span>
                    <span>· "<?= h($a['keywords']) ?>"</span>
                </div>
            </div>
            <div class="actions">
                <form method="post" action="<?= h(url('/automations/' . $a['id'] . '/toggle')) ?>">
                    <?= csrf_field() ?>
                    <label class="switch">
                        <input type="checkbox" onchange="this.form.submit()" <?= $a['is_active'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </form>
                <a href="<?= h(url('/automations/' . $a['id'] . '/edit')) ?>" class="btn btn-secondary btn-sm">Editar</a>
                <form method="post" action="<?= h(url('/automations/' . $a['id'] . '/delete')) ?>"
                      onsubmit="return confirm('Excluir esta automação?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
