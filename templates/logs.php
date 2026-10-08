<?php
/** @var array $logs */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
?>
<div class="page-header">
    <div>
        <h1>Respostas enviadas</h1>
        <p><?= (int) $total ?> comentários que bateram com alguma automação, no total (comentários sem correspondência não aparecem aqui). Página <?= (int) $page ?> de <?= (int) $totalPages ?>.</p>
    </div>
</div>

<div class="card">
    <?php if (empty($logs)): ?>
        <div class="empty-state">
            <h2>Nada por aqui ainda</h2>
            <p>Assim que uma automação responder algum comentário, aparece aqui.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Usuário</th>
                    <th>Comentário</th>
                    <th>Automação</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= h($log['created_at']) ?></td>
                        <td>@<?= h($log['commenter_username'] ?? '—') ?></td>
                        <td><?= h(mb_strimwidth((string) $log['comment_text'], 0, 80, '…')) ?></td>
                        <td><?= h($log['automation_name'] ?? '—') ?></td>
                        <td>
                            <?php if (!empty($log['scheduled_for'])): ?>
                                <span class="tag">Agendado pra <?= h(date('d/m H:i', strtotime((string) $log['scheduled_for']))) ?></span>
                            <?php elseif ($log['reply_sent']): ?>
                                <span class="status-pill status-connected">Comentário respondido</span>
                            <?php else: ?>
                                <span class="status-pill status-disconnected">Comentário não enviado</span>
                            <?php endif; ?>
                            <?php if ($log['dm_sent']): ?>
                                <span class="status-pill status-connected">DM enviada</span>
                            <?php elseif (!empty($log['error']) && str_contains((string) $log['error'], 'DM:')): ?>
                                <span class="status-pill status-disconnected">DM falhou</span>
                            <?php endif; ?>
                            <?php if (!empty($log['error'])): ?>
                                <br><small title="<?= h($log['error']) ?>" style="color:var(--ink-soft)"><?= h(mb_strimwidth($log['error'], 0, 60, '…')) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="display:flex; justify-content:center; gap:10px; align-items:center; margin-top:16px;">
                <a href="<?= h(url('/logs?page=' . max(1, $page - 1))) ?>"
                   class="btn btn-secondary btn-sm" <?= $page <= 1 ? 'style="pointer-events:none;opacity:.4"' : '' ?>>← Anterior</a>
                <span class="form-hint">Página <?= (int) $page ?> de <?= (int) $totalPages ?></span>
                <a href="<?= h(url('/logs?page=' . min($totalPages, $page + 1))) ?>"
                   class="btn btn-secondary btn-sm" <?= $page >= $totalPages ? 'style="pointer-events:none;opacity:.4"' : '' ?>>Próxima →</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
