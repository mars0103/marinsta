<?php
declare(strict_types=1);

// Rode este script MANUALMENTE, uma única vez, antes de ligar o cron de
// polling (poll_comments.php). Ele marca todos os comentários que já
// existem hoje como "já vistos", sem responder nenhum — assim, quando o
// polling começar a rodar de verdade, ele só reage a comentários NOVOS.
//
// Uso: php cron/seed_processed_comments.php

require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/InstagramClient.php';
require __DIR__ . '/../src/AutomationEngine.php';

const MEDIA_LIMIT = 15;

$db = Database::pdo();
$accounts = $db->query('SELECT * FROM ig_accounts')->fetchAll();

foreach ($accounts as $account) {
    $mediaItems = InstagramClient::getRecentMedia((string) $account['access_token'], MEDIA_LIMIT);
    $seeded = 0;

    foreach ($mediaItems as $media) {
        $mediaId = $media['id'] ?? null;
        if (!$mediaId) {
            continue;
        }

        $comments = InstagramClient::getComments((string) $mediaId, (string) $account['access_token']);

        foreach ($comments as $comment) {
            $commentId = $comment['id'] ?? null;
            if (!$commentId) {
                continue;
            }

            AutomationEngine::markAsSeen(
                $db,
                (string) $commentId,
                $comment['username'] ?? null,
                (string) ($comment['text'] ?? '')
            );
            $seeded++;
        }
    }

    echo "@{$account['username']}: {$seeded} comentários marcados como já vistos.\n";
}

echo "Pronto. Agora pode ligar o cron de poll_comments.php.\n";
