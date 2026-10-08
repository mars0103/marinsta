<?php
declare(strict_types=1);

// Configure no cPanel > Cron Jobs pra rodar a cada 3 minutos, por exemplo:
// */3 * * * * php /home/SEU_USUARIO/marinsta_app/cron/poll_comments.php
//
// Busca comentários novos nas últimas mídias de cada conta conectada e roda
// as automações neles. É a alternativa ao webhook: funciona 100% com o app
// da Meta em modo "Em desenvolvimento", sem precisar publicar/verificar a
// empresa — só com resposta em até alguns minutos, em vez de instantânea.

require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/InstagramClient.php';
require __DIR__ . '/../src/AutomationEngine.php';

const MEDIA_LIMIT = 8; // poucas mídias recentes, pra não estourar o rate limit da API

function ts(): string
{
    return '[' . date('Y-m-d H:i:s') . '] ';
}

$db = Database::pdo();
$accounts = $db->query('SELECT * FROM ig_accounts')->fetchAll();

foreach ($accounts as $account) {
    try {
        $mediaItems = InstagramClient::getRecentMedia((string) $account['access_token'], MEDIA_LIMIT);
    } catch (Throwable $e) {
        echo ts() . "[erro] mídia de @{$account['username']}: {$e->getMessage()}\n";
        continue;
    }

    AutomationEngine::resolveNextPostAutomations($db, (int) $account['id'], $mediaItems);

    foreach ($mediaItems as $media) {
        $mediaId = $media['id'] ?? null;
        if (!$mediaId) {
            continue;
        }

        try {
            $comments = InstagramClient::getComments((string) $mediaId, (string) $account['access_token']);
        } catch (Throwable $e) {
            echo ts() . "[erro] comentários da mídia {$mediaId}: {$e->getMessage()}\n";
            continue;
        }

        foreach ($comments as $comment) {
            $commentId = $comment['id'] ?? null;
            if (!$commentId) {
                continue;
            }

            AutomationEngine::processComment(
                $db,
                $account,
                (string) $mediaId,
                (string) $commentId,
                $comment['username'] ?? null,
                (string) ($comment['text'] ?? '')
            );
        }
    }
}
