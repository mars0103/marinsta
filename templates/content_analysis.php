<?php
/** @var array|null $account */
/** @var array $posts */
/** @var array $byType */
/** @var float $avgEngagement */
/** @var int $maxEngagement */
/** @var string $sort */
/** @var string $typeFilter */
/** @var string $fromFilter */
/** @var string $toFilter */
/** @var string|null $oldestLoaded */
/** @var string|null $newestLoaded */
/** @var string|null $error */
?>
<div class="page-header">
    <div>
        <h1>Análise de Conteúdo</h1>
        <p>Curtidas + comentários de cada publicação, pra ver o que performa melhor.</p>
    </div>
</div>

<?php if (!$account): ?>
    <div class="card empty-state">
        <h2>Conecte sua conta do Instagram</h2>
        <p>Vai em Automações e clica em "Conectar Instagram" primeiro.</p>
    </div>
<?php elseif ($error): ?>
    <div class="card">
        <p>Não consegui buscar suas publicações agora: <?= h($error) ?></p>
    </div>
<?php else: ?>

    <div class="card" style="margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
        <form method="get" action="<?= h(url('/content')) ?>" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
            <div>
                <label class="form-hint" style="display:block; margin-bottom:4px;">De</label>
                <input type="date" name="from" value="<?= h($fromFilter) ?>" min="<?= h((string) $oldestLoaded) ?>" max="<?= h((string) $newestLoaded) ?>">
            </div>
            <div>
                <label class="form-hint" style="display:block; margin-bottom:4px;">Até</label>
                <input type="date" name="to" value="<?= h($toFilter) ?>" min="<?= h((string) $oldestLoaded) ?>" max="<?= h((string) $newestLoaded) ?>">
            </div>
            <select name="sort">
                <option value="engagement" <?= $sort === 'engagement' ? 'selected' : '' ?>>Mais engajamento</option>
                <option value="likes" <?= $sort === 'likes' ? 'selected' : '' ?>>Mais curtidas</option>
                <option value="comments" <?= $sort === 'comments' ? 'selected' : '' ?>>Mais comentários</option>
                <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Mais recentes</option>
            </select>
            <select name="type">
                <option value="">Todos os formatos</option>
                <?php foreach (array_keys($byType) as $type): ?>
                    <option value="<?= h($type) ?>" <?= $typeFilter === $type ? 'selected' : '' ?>><?= h($type) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            <?php if ($fromFilter || $toFilter || $typeFilter): ?>
                <a href="<?= h(url('/content')) ?>" class="btn btn-secondary btn-sm">Limpar filtros</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($oldestLoaded && $newestLoaded): ?>
        <p class="form-hint" style="margin:-10px 0 20px;">
            Carregado todo o histórico disponível: de <?= h(date('d/m/Y', strtotime($oldestLoaded))) ?> até <?= h(date('d/m/Y', strtotime($newestLoaded))) ?>.
            Se a conta tiver mais de 1.000 publicações, só as 1.000 mais recentes entram nessa busca.
        </p>
    <?php endif; ?>

    <?php if (empty($posts)): ?>
        <div class="card empty-state">
            <h2>Nenhuma publicação nesse filtro</h2>
            <p>Tenta ajustar o período ou o formato.</p>
        </div>
    <?php else: ?>

        <div class="card" style="margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px;">
                <div>
                    <div class="form-hint" style="margin:0 0 4px;">Publicações no período</div>
                    <strong style="font-size:20px;"><?= count($posts) ?></strong>
                </div>
                <div>
                    <div class="form-hint" style="margin:0 0 4px;">Engajamento médio (curtidas + comentários)</div>
                    <strong style="font-size:20px;"><?= h(number_format($avgEngagement, 0, ',', '.')) ?></strong>
                </div>
                <?php if (!empty($byType)): ?>
                    <?php $topType = array_key_first($byType); ?>
                    <div>
                        <div class="form-hint" style="margin:0 0 4px;">Formato que mais performa</div>
                        <strong style="font-size:20px;"><?= h($topType) ?></strong>
                        <span class="form-hint">(média <?= h(number_format($byType[$topType]['avg'], 0, ',', '.')) ?>)</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <strong>Por formato</strong>
            <div style="margin-top:10px;">
                <?php foreach ($byType as $type => $data): ?>
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                        <div style="width:90px; font-size:13px;"><?= h($type) ?></div>
                        <div style="flex:1; background:var(--blue-50); border-radius:6px; height:10px; overflow:hidden;">
                            <div style="width:<?= $byType ? min(100, round($data['avg'] / max(array_column($byType, 'avg'), [1])[0] * 100)) : 0 ?>%; background:var(--blue-600); height:100%;"></div>
                        </div>
                        <div style="width:140px; font-size:13px; color:var(--ink-soft); text-align:right;"><?= (int) $data['count'] ?> posts · média <?= h(number_format($data['avg'], 0, ',', '.')) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="form-hint" style="margin-top:10px;">Não inclui alcance/impressões — isso exigiria reconectar a conta pedindo uma permissão extra da Meta. Esse ranking usa só curtidas + comentários.</p>
        </div>

        <?php foreach ($posts as $p): ?>
            <div class="card automation-card">
                <div style="display:flex; gap:14px; align-items:center; flex:1; min-width:0;">
                    <?php if (!empty($p['thumbnail_url']) || !empty($p['media_url'])): ?>
                        <img src="<?= h($p['thumbnail_url'] ?? $p['media_url']) ?>" alt="" style="width:56px; height:56px; object-fit:cover; border-radius:8px; flex-shrink:0;">
                    <?php else: ?>
                        <div style="width:56px; height:56px; border-radius:8px; background:var(--blue-50); flex-shrink:0;"></div>
                    <?php endif; ?>
                    <div style="min-width:0;">
                        <div style="display:flex; gap:8px; align-items:center;">
                            <span class="tag"><?= h($p['type_label']) ?></span>
                            <span class="form-hint" style="margin:0;"><?= h(date('d/m/Y', strtotime((string) $p['timestamp']))) ?></span>
                        </div>
                        <div style="margin-top:4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:420px;">
                            <?= h(mb_strimwidth((string) ($p['caption'] ?? 'Sem legenda'), 0, 80, '…')) ?>
                        </div>
                        <?php if ($maxEngagement > 0): ?>
                            <div style="width:200px; background:var(--border); border-radius:4px; height:6px; margin-top:6px; overflow:hidden;">
                                <div style="width:<?= round($p['engagement'] / $maxEngagement * 100) ?>%; background:var(--blue-600); height:100%;"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="actions" style="text-align:right; flex-shrink:0;">
                    <div style="font-size:18px; font-weight:700;"><?= h(number_format($p['engagement'], 0, ',', '.')) ?></div>
                    <div class="form-hint" style="margin:0;"><?= h(number_format($p['likes'], 0, ',', '.')) ?> curtidas · <?= h(number_format($p['comments'], 0, ',', '.')) ?> comentários</div>
                    <?php if (!empty($p['permalink'])): ?>
                        <a href="<?= h($p['permalink']) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" style="margin-top:6px;">Ver no Instagram</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>
