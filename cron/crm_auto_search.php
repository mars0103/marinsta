<?php
declare(strict_types=1);

// Configure no cPanel > Cron Jobs pra rodar a cada 2 minutos, por exemplo:
// */2 * * * * php /home/SEU_USUARIO/marinsta_app/cron/crm_auto_search.php
//
// Processa um job de busca automática de leads por vez (fila -> buscando ->
// concluido/erro). Cada job pode levar bastante tempo (várias buscas na web
// pela API da Anthropic), por isso roda fora do request HTTP do painel.

set_time_limit(0);

require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Crm.php';
require __DIR__ . '/../src/CrmAutoSearch.php';

function ts(): string
{
    return '[' . date('Y-m-d H:i:s') . '] ';
}

$db = Database::pdo();

$stmt = $db->query("SELECT * FROM crm_search_jobs WHERE status = 'fila' ORDER BY created_at ASC LIMIT 1");
$job = $stmt->fetch();

if (!$job) {
    echo ts() . "nenhum job em fila.\n";
    exit;
}

echo ts() . "processando job #{$job['id']} (conta {$job['ig_account_id']}, pedindo {$job['requested_count']})\n";

CrmAutoSearch::run($db, $job);

$result = $db->prepare('SELECT status, found_count, error_message FROM crm_search_jobs WHERE id = ?');
$result->execute([$job['id']]);
$final = $result->fetch();

echo ts() . "job #{$job['id']} -> {$final['status']}, encontrados: {$final['found_count']}"
    . ($final['error_message'] ? ", erro: {$final['error_message']}" : '') . "\n";
