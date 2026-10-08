<?php
/** @var array $packs */
?>
<div class="page-header">
    <div>
        <h1>Pack de respostas</h1>
        <p>Grupos de respostas variadas — a automação sorteia uma delas a cada vez, pra não parecer robótico.</p>
    </div>
    <a href="<?= h(url('/packs/new')) ?>" class="btn btn-primary">+ Novo pack</a>
</div>

<?php if (empty($packs)): ?>
    <div class="card empty-state">
        <h2>Nenhum pack ainda</h2>
        <p>Crie um pack com algumas variações de texto e use ele numa automação.</p>
    </div>
<?php else: ?>
    <?php foreach ($packs as $p): ?>
        <div class="card automation-card">
            <div>
                <h3><?= h($p['name']) ?></h3>
                <div class="automation-meta">
                    <span class="tag"><?= (int) $p['item_count'] ?> respostas</span>
                </div>
            </div>
            <div class="actions">
                <a href="<?= h(url('/packs/' . $p['id'] . '/edit')) ?>" class="btn btn-secondary btn-sm">Editar</a>
                <form method="post" action="<?= h(url('/packs/' . $p['id'] . '/delete')) ?>"
                      onsubmit="return confirm('Excluir este pack? Automações que usam ele voltam a usar texto fixo.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
