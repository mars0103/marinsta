<?php
declare(strict_types=1);

require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/InstagramClient.php';
require __DIR__ . '/../src/AutomationEngine.php';

// --- Handshake de verificação (GET), exigido pelo painel de Webhooks da Meta ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($mode === 'subscribe' && hash_equals((string) config('WEBHOOK_VERIFY_TOKEN', ''), (string) $token)) {
        http_response_code(200);
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }

    http_response_code(403);
    exit;
}

// --- Evento real (POST) ---
$rawBody = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null;

if (!InstagramClient::verifySignature($rawBody, $signature, (string) config('IG_APP_SECRET', ''))) {
    http_response_code(403);
    exit;
}

// Confirma recebimento imediatamente: a Meta espera resposta rápida e reenvia em caso de timeout.
http_response_code(200);
echo 'EVENT_RECEIVED';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    exit;
}

$db = Database::pdo();

foreach ($payload['entry'] ?? [] as $entry) {
    $igAccountScopedId = $entry['id'] ?? null;
    foreach ($entry['changes'] ?? [] as $change) {
        if (($change['field'] ?? '') === 'comments') {
            AutomationEngine::handleComment($db, $igAccountScopedId, $change['value'] ?? []);
        }
    }
}
