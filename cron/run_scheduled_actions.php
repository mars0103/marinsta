<?php
declare(strict_types=1);

// Configure no cPanel > Cron Jobs pra rodar a cada 1 minuto, por exemplo:
// * * * * * php /home/SEU_USUARIO/marinsta_app/cron/run_scheduled_actions.php
//
// Executa as respostas que estavam esperando um delay configurado na
// automação (bloco "Esperar X minutos" no editor de fluxo).

require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/InstagramClient.php';
require __DIR__ . '/../src/AutomationEngine.php';

$db = Database::pdo();

$stmt = $db->prepare(
    'SELECT * FROM scheduled_actions WHERE run_at <= NOW() AND executed_at IS NULL ORDER BY run_at ASC LIMIT 50'
);
$stmt->execute();
$due = $stmt->fetchAll();

foreach ($due as $action) {
    $accStmt = $db->prepare('SELECT * FROM ig_accounts WHERE id = ?');
    $accStmt->execute([$action['ig_account_id']]);
    $account = $accStmt->fetch();

    $autoStmt = $db->prepare('SELECT * FROM automations WHERE id = ?');
    $autoStmt->execute([$action['automation_id']]);
    $automation = $autoStmt->fetch();

    if ($account && $automation) {
        AutomationEngine::executeActions(
            $db,
            $account,
            $automation,
            (string) $action['ig_comment_id'],
            $action['commenter_username'],
            (string) $action['comment_text']
        );
        echo "[ok] comentário {$action['ig_comment_id']} executado (delay).\n";
    } else {
        echo "[erro] conta ou automação não encontrada pra ação agendada {$action['id']}.\n";
    }

    $done = $db->prepare('UPDATE scheduled_actions SET executed_at = NOW() WHERE id = ?');
    $done->execute([$action['id']]);
}
