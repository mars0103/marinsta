<?php
/** @var array|null $pack */
/** @var array $items */
$isEdit = $pack !== null;
$action = $isEdit ? url('/packs/' . $pack['id'] . '/edit') : url('/packs/new');
$itemsText = implode("\n", array_map(static fn ($i) => $i['text'], $items ?? []));
?>
<div class="page-header">
    <div>
        <h1><?= $isEdit ? 'Editar pack' : 'Novo pack' ?></h1>
        <p>Uma resposta por linha. Na hora de responder, o Marinsta sorteia uma aleatoriamente.</p>
    </div>
</div>

<div class="card">
    <form method="post" action="<?= h($action) ?>">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="name">Nome do pack</label>
            <input type="text" id="name" name="name" required
                   value="<?= h($pack['name'] ?? '') ?>" placeholder="Ex: Respostas Codesstore">
        </div>

        <div class="form-group">
            <label for="items">Respostas (uma por linha)</label>
            <textarea id="items" name="items" required rows="8" data-variables="1"
                      placeholder="Já te chamei na DM! 😉&#10;Vou te mandar os detalhes por lá!&#10;Confere seu direct 👀"><?= h($itemsText) ?></textarea>
            <p class="form-hint">Linhas em branco são ignoradas. Digite "/" pra inserir o nome de quem comentou.</p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Salvar alterações' : 'Criar pack' ?></button>
            <a href="<?= h(url('/packs')) ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script src="<?= h(url('assets/js/variable-picker.js')) ?>"></script>
