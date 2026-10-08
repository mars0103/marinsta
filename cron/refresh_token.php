<?php
declare(strict_types=1);

// Configure no cPanel > Cron Jobs para rodar 1x por dia, por exemplo:
// php /home/SEU_USUARIO/marinsta/cron/refresh_token.php

require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/InstagramClient.php';

$db = Database::pdo();

// Renova qualquer token que vença nos próximos 10 dias (tokens de longa duração
// duram ~60 dias e só podem ser renovados depois de terem pelo menos 24h de vida).
$stmt = $db->query(
    "SELECT * FROM ig_accounts WHERE token_expires_at <= DATE_ADD(NOW(), INTERVAL 10 DAY)"
);
$accounts = $stmt->fetchAll();

foreach ($accounts as $account) {
    try {
        $result = InstagramClient::refreshLongLivedToken((string) $account['access_token']);
        $expiresAt = (new DateTimeImmutable())
            ->modify('+' . (int) ($result['expires_in'] ?? 5184000) . ' seconds')
            ->format('Y-m-d H:i:s');

        $update = $db->prepare('UPDATE ig_accounts SET access_token = ?, token_expires_at = ? WHERE id = ?');
        $update->execute([$result['access_token'], $expiresAt, $account['id']]);

        echo "[ok] @{$account['username']} renovado até {$expiresAt}\n";
    } catch (Throwable $e) {
        echo "[erro] @{$account['username']}: {$e->getMessage()}\n";
    }
}
