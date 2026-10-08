<?php
/** @var array|null $automation */
/** @var array $packs */
/** @var array $mediaList */
$isEdit = $automation !== null;
$action = $isEdit ? url('/automations/' . $automation['id'] . '/edit') : url('/automations/new');
$targetScope = $automation['target_scope'] ?? 'all';
$replySource = !empty($automation['reply_pack_id']) ? 'pack' : 'text';
$dmSource = !empty($automation['dm_pack_id']) ? 'pack' : 'text';
?>
<div class="page-header">
    <div>
        <h1><?= $isEdit ? 'Editar automação' : 'Nova automação' ?></h1>
        <p>Quando um comentário contiver as palavras-chave, o Marinsta responde automaticamente.</p>
    </div>
    <?php if ($isEdit): ?>
        <a href="<?= h(url('/automations/' . $automation['id'] . '/flow')) ?>" class="btn btn-secondary">Ver como fluxo 🔀</a>
    <?php endif; ?>
</div>

<div class="card">
    <form method="post" action="<?= h($action) ?>">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="name">Nome da automação</label>
            <input type="text" id="name" name="name" required
                   value="<?= h($automation['name'] ?? '') ?>" placeholder="Ex: Preço no comentário">
        </div>

        <div class="form-group">
            <label for="keywords">Palavras-chave</label>
            <input type="text" id="keywords" name="keywords" required
                   value="<?= h($automation['keywords'] ?? '') ?>" placeholder="preço, valor, quanto custa">
            <p class="form-hint">Separe por vírgula. Não diferencia maiúsculas/minúsculas.</p>
        </div>

        <div class="form-group">
            <label for="match_type">Disparar quando o comentário tiver</label>
            <select id="match_type" name="match_type">
                <?php $mt = $automation['match_type'] ?? 'any'; ?>
                <option value="any" <?= $mt === 'any' ? 'selected' : '' ?>>Qualquer uma das palavras</option>
                <option value="all" <?= $mt === 'all' ? 'selected' : '' ?>>Todas as palavras</option>
            </select>
        </div>

        <div class="form-group">
            <label>Em qual publicação</label>
            <select id="target_scope" name="target_scope" onchange="document.getElementById('target_media_wrap').style.display = this.value === 'specific' ? 'block' : 'none'">
                <option value="all" <?= $targetScope === 'all' ? 'selected' : '' ?>>Todas as publicações</option>
                <option value="next" <?= $targetScope === 'next' ? 'selected' : '' ?>>Só a próxima publicação (a partir de agora)</option>
                <option value="specific" <?= $targetScope === 'specific' ? 'selected' : '' ?>>Uma publicação específica</option>
            </select>
            <?php if ($targetScope === 'next' && !empty($automation['target_media_id'])): ?>
                <p class="form-hint">Já travada na publicação de ID <?= h($automation['target_media_id']) ?>.</p>
            <?php elseif ($targetScope === 'next'): ?>
                <p class="form-hint">Assim que você publicar algo novo, esta automação passa a valer só pra ele.</p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="delay_minutes">Esperar quantos minutos antes de responder</label>
            <input type="number" id="delay_minutes" name="delay_minutes" min="0" step="1"
                   value="<?= h((string) ($automation['delay_minutes'] ?? 0)) ?>" style="max-width:120px">
            <p class="form-hint">0 = responde assim que o polling detectar o comentário (poucos minutos). Um número maior atrasa a resposta de propósito, pra parecer menos automático.</p>
        </div>

        <div class="form-group" id="target_media_wrap" style="<?= $targetScope === 'specific' ? '' : 'display:none' ?>">
            <label for="target_media_id">Qual publicação</label>
            <select id="target_media_id" name="target_media_id">
                <option value="">Selecione...</option>
                <?php foreach ($mediaList as $media): ?>
                    <?php
                        $caption = trim((string) ($media['caption'] ?? ''));
                        $label = ($caption !== '' ? mb_strimwidth($caption, 0, 60, '…') : 'Sem legenda') . ' — ' . h(substr((string) ($media['timestamp'] ?? ''), 0, 10));
                        $selected = (string) ($automation['target_media_id'] ?? '') === (string) $media['id'];
                    ?>
                    <option value="<?= h($media['id']) ?>" <?= $selected ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($mediaList)): ?>
                <p class="form-hint">Não consegui carregar suas publicações agora — tente de novo em alguns instantes.</p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Resposta no comentário</label>
            <select id="reply_source" name="reply_source" onchange="
                document.getElementById('reply_text_wrap').style.display = this.value === 'text' ? 'block' : 'none';
                document.getElementById('reply_pack_wrap').style.display = this.value === 'pack' ? 'block' : 'none';
            ">
                <option value="text" <?= $replySource === 'text' ? 'selected' : '' ?>>Texto fixo</option>
                <option value="pack" <?= $replySource === 'pack' ? 'selected' : '' ?>>Pack de respostas (sorteia uma)</option>
            </select>
        </div>

        <div class="form-group" id="reply_text_wrap" style="<?= $replySource === 'text' ? '' : 'display:none' ?>">
            <label for="reply_text">Texto da resposta no comentário</label>
            <textarea id="reply_text" name="reply_text" data-variables="1"
                      placeholder="Oi! Te chamamos na DM com os detalhes 😉"><?= h($automation['reply_text'] ?? '') ?></textarea>
            <p class="form-hint">Digite "/" pra inserir o nome de quem comentou. O Instagram não deixa link clicável em comentário público — se quiser mandar link, use o campo de DM abaixo.</p>
        </div>

        <div class="form-group" id="reply_pack_wrap" style="<?= $replySource === 'pack' ? '' : 'display:none' ?>">
            <label for="reply_pack_id">Pack de respostas</label>
            <select id="reply_pack_id" name="reply_pack_id">
                <option value="">Selecione...</option>
                <?php foreach ($packs as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (int) ($automation['reply_pack_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= h($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($packs)): ?>
                <p class="form-hint">Você ainda não tem nenhum pack — <a href="<?= h(url('/packs/new')) ?>">crie um aqui</a>.</p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" id="send_dm" name="send_dm" value="1" style="width:auto"
                       onchange="document.getElementById('dm_wrap').style.display = this.checked ? 'block' : 'none'"
                       <?= ($automation['send_dm'] ?? 0) ? 'checked' : '' ?>>
                Também mandar uma DM (mensagem direta) pra quem comentou
            </label>
        </div>

        <div id="dm_wrap" style="<?= ($automation['send_dm'] ?? 0) ? '' : 'display:none' ?>">
            <div class="form-group">
                <label>Resposta na DM</label>
                <select id="dm_source" name="dm_source" onchange="
                    document.getElementById('dm_text_wrap').style.display = this.value === 'text' ? 'block' : 'none';
                    document.getElementById('dm_pack_wrap').style.display = this.value === 'pack' ? 'block' : 'none';
                ">
                    <option value="text" <?= $dmSource === 'text' ? 'selected' : '' ?>>Texto fixo</option>
                    <option value="pack" <?= $dmSource === 'pack' ? 'selected' : '' ?>>Pack de respostas (sorteia uma)</option>
                </select>
            </div>

            <div class="form-group" id="dm_text_wrap" style="<?= $dmSource === 'text' ? '' : 'display:none' ?>">
                <label for="dm_text">Texto da DM</label>
                <textarea id="dm_text" name="dm_text" data-variables="1"
                          placeholder="Aqui está o link que você pediu: https://seusite.com.br"><?= h($automation['dm_text'] ?? '') ?></textarea>
                <p class="form-hint">Digite "/" pra inserir o nome de quem comentou. Pode colocar link aqui, fica clicável no direct.</p>
            </div>

            <div class="form-group" id="dm_pack_wrap" style="<?= $dmSource === 'pack' ? '' : 'display:none' ?>">
                <label for="dm_pack_id">Pack de respostas</label>
                <select id="dm_pack_id" name="dm_pack_id">
                    <option value="">Selecione...</option>
                    <?php foreach ($packs as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (int) ($automation['dm_pack_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= h($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($packs)): ?>
                    <p class="form-hint">Você ainda não tem nenhum pack — <a href="<?= h(url('/packs/new')) ?>">crie um aqui</a>.</p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="dm_button_label">Botão na DM (opcional)</label>
                <input type="text" id="dm_button_label" name="dm_button_label" maxlength="20"
                       value="<?= h($automation['dm_button_label'] ?? '') ?>" placeholder="Ex: Ver agora" style="max-width:220px">
                <p class="form-hint">Até 20 caracteres. Preenchendo isso e o link abaixo, a DM sai com um botão clicável em vez do link em texto.</p>
            </div>

            <div class="form-group">
                <label for="dm_button_url">Link do botão</label>
                <input type="text" id="dm_button_url" name="dm_button_url"
                       value="<?= h($automation['dm_button_url'] ?? '') ?>" placeholder="https://seusite.com.br">
            </div>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" value="1" style="width:auto"
                       <?= ($automation['is_active'] ?? 1) ? 'checked' : '' ?>>
                Automação ativa
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Salvar alterações' : 'Criar automação' ?></button>
            <a href="<?= h(url('/')) ?>" class="btn btn-secondary">Cancelar</a>
        </div>
    </form>
</div>

<script src="<?= h(url('assets/js/variable-picker.js')) ?>"></script>
