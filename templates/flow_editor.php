<?php
/** @var array $automation */
/** @var array $packs */
/** @var array $mediaList */
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fluxo · <?= h($automation['name']) ?> · Marinsta</title>
<link rel="icon" type="image/png" href="<?= h(url('assets/brand/favicon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= h(url('assets/css/style.css')) ?>?v=<?= asset_version() ?>">
<link rel="stylesheet" href="<?= h(url('assets/vendor/litegraph/litegraph.css')) ?>">
<style>
  html, body { height: 100%; margin: 0; font-family: 'Space Grotesk', system-ui, sans-serif; }
  .flow-topbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 20px; border-bottom: 1px solid var(--border); background: #fff;
  }
  .flow-topbar .left { display: flex; align-items: center; gap: 14px; }
  .flow-topbar input[type="text"] { width: 260px; }
  .flow-canvas-wrap { position: relative; height: calc(100vh - 61px); background: #f6f8fc; }
  #flowcanvas { width: 100%; height: 100%; display: block; }
  .flow-hint {
    position: absolute; left: 16px; bottom: 16px; background: #fff; border: 1px solid var(--border);
    border-radius: 8px; padding: 10px 14px; font-size: 12px; color: var(--ink-soft); max-width: 320px;
  }
</style>
</head>
<body>

<div class="flow-topbar">
    <div class="left">
        <a href="<?= h(url('/automations/' . $automation['id'] . '/edit')) ?>" class="btn btn-secondary btn-sm">← Voltar</a>
        <strong><?= h($automation['name']) ?></strong>
    </div>
    <button type="button" class="btn btn-primary" id="btnSave">Salvar fluxo</button>
</div>

<div class="flow-canvas-wrap">
    <canvas id="flowcanvas"></canvas>
    <div class="flow-hint">Arraste os nós, ligue a saída de um bloco na entrada do próximo, dê zoom com a roda do mouse.
    Isso edita os mesmos campos da tela normal — dá pra alternar entre as duas visualizações a qualquer momento.</div>
</div>

<form id="syncForm" method="post" action="<?= h(url('/automations/' . $automation['id'] . '/edit')) ?>" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="name" value="<?= h($automation['name']) ?>">
    <input type="hidden" name="is_active" value="<?= $automation['is_active'] ? '1' : '0' ?>">
    <input type="hidden" name="keywords" id="f_keywords">
    <input type="hidden" name="match_type" id="f_match_type">
    <input type="hidden" name="target_scope" id="f_target_scope">
    <input type="hidden" name="target_media_id" id="f_target_media_id">
    <input type="hidden" name="delay_minutes" id="f_delay_minutes">
    <input type="hidden" name="reply_source" id="f_reply_source">
    <input type="hidden" name="reply_text" id="f_reply_text">
    <input type="hidden" name="reply_pack_id" id="f_reply_pack_id">
    <input type="hidden" name="send_dm" id="f_send_dm">
    <input type="hidden" name="dm_source" id="f_dm_source">
    <input type="hidden" name="dm_text" id="f_dm_text">
    <input type="hidden" name="dm_pack_id" id="f_dm_pack_id">
    <input type="hidden" name="dm_button_label" id="f_dm_button_label">
    <input type="hidden" name="dm_button_url" id="f_dm_button_url">
</form>

<script src="<?= h(url('assets/vendor/litegraph/litegraph.min.js')) ?>"></script>
<script src="<?= h(url('assets/js/flow-editor.js')) ?>"></script>
<script>
const AUTOMATION = <?= json_encode($automation, JSON_UNESCAPED_UNICODE) ?>;
const PACKS = <?= json_encode($packs, JSON_UNESCAPED_UNICODE) ?>;
const MEDIA_LIST = <?= json_encode($mediaList, JSON_UNESCAPED_UNICODE) ?>;

const canvasEl = document.getElementById('flowcanvas');
function resizeCanvas() {
    const wrap = canvasEl.parentElement;
    canvasEl.width = wrap.clientWidth;
    canvasEl.height = wrap.clientHeight;
}
resizeCanvas();
window.addEventListener('resize', resizeCanvas);

let graph = null;

function start() {
    const built = buildGraph(canvasEl, AUTOMATION, PACKS, MEDIA_LIST);
    graph = built.graph;
}

// espera a fonte carregar de verdade antes de desenhar o canvas — sem isso
// o navegador desenha com a fonte padrão do sistema silenciosamente.
if (document.fonts && document.fonts.ready) {
    Promise.all([
        document.fonts.load("600 13px 'Space Grotesk'"),
        document.fonts.load("normal 12px 'Space Grotesk'"),
    ]).then(start).catch(start);
} else {
    start();
}

document.getElementById('btnSave').addEventListener('click', function () {
    if (!graph) {
        return;
    }
    const data = collectAutomationFromGraph(graph);
    if (!data) {
        alert('Não achei o bloco de Gatilho no fluxo — não dá pra salvar sem ele.');
        return;
    }
    document.getElementById('f_keywords').value = data.keywords;
    document.getElementById('f_match_type').value = data.match_type;
    document.getElementById('f_target_scope').value = data.target_scope;
    document.getElementById('f_target_media_id').value = data.target_media_id || '';
    document.getElementById('f_delay_minutes').value = data.delay_minutes || 0;
    document.getElementById('f_reply_source').value = data.reply_source;
    document.getElementById('f_reply_text').value = data.reply_text;
    document.getElementById('f_reply_pack_id').value = data.reply_pack_id || '';
    document.getElementById('f_send_dm').value = data.send_dm ? '1' : '';
    document.getElementById('f_dm_source').value = data.dm_source;
    document.getElementById('f_dm_text').value = data.dm_text;
    document.getElementById('f_dm_pack_id').value = data.dm_pack_id || '';
    document.getElementById('f_dm_button_label').value = data.dm_button_label || '';
    document.getElementById('f_dm_button_url').value = data.dm_button_url || '';
    document.getElementById('syncForm').submit();
});
</script>
</body>
</html>
