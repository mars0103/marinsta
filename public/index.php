<?php
declare(strict_types=1);

require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/InstagramClient.php';
require __DIR__ . '/../src/Crm.php';
require __DIR__ . '/../src/CrmAutoSearch.php';
require __DIR__ . '/../src/Mailer.php';

/**
 * Versão do CSS baseada na data de modificação do arquivo, pra forçar o
 * navegador a buscar a versão nova sempre que o style.css mudar (o servidor
 * manda cache de 7 dias nos assets estáticos).
 */
function asset_version(): string
{
    static $version = null;
    if ($version === null) {
        $version = (string) (@filemtime(__DIR__ . '/assets/css/style.css') ?: time());
    }
    return $version;
}

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = $uri;
if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}
$path = trim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];

try {
    // --- rotas públicas ---
    if ($path === 'login') {
        if ($method === 'POST') {
            csrf_verify();
            if (Auth::attempt((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
                redirect('/');
            }
            flash('error', 'Usuário ou senha inválidos.');
            redirect('/login');
        }
        view('login');
        exit;
    }

    if ($path === 'logout') {
        Auth::logout();
        redirect('/login');
    }

    if ($path === 'forgot-password') {
        if ($method === 'POST') {
            csrf_verify();

            if (!Auth::canRequestPasswordReset()) {
                flash('error', 'Já foi pedida uma senha nova há pouco. Aguarde alguns minutos e tente de novo.');
                redirect('/forgot-password');
            }

            $adminEmail = (string) config('ADMIN_EMAIL', '');
            if ($adminEmail === '') {
                flash('error', 'Não há email de recuperação configurado. Troque a senha via SSH.');
                redirect('/login');
            }

            $newPassword = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 16);
            $body = "Sua nova senha de acesso ao Marinsta:\n\n{$newPassword}\n\n"
                . "Troque por uma de sua escolha assim que entrar, em Perfil.\n";

            if (Mailer::send($adminEmail, 'Nova senha de acesso - Marinsta', $body)) {
                Auth::updatePassword($newPassword);
                Auth::recordPasswordResetRequest();
                flash('success', 'Enviamos uma nova senha para o seu email. Confira também a caixa de spam.');
            } else {
                flash('error', 'Não consegui enviar o email agora. Tente de novo em alguns minutos.');
            }
            redirect('/login');
        }

        view('forgot_password');
        exit;
    }

    if ($path === 'profile') {
        Auth::requireLogin();

        if ($method === 'POST') {
            csrf_verify();
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            if (!Auth::verifyCurrentPassword($current)) {
                flash('error', 'Senha atual incorreta.');
            } elseif (mb_strlen($new) < 8) {
                flash('error', 'A nova senha precisa ter pelo menos 8 caracteres.');
            } elseif ($new !== $confirm) {
                flash('error', 'A confirmação não bate com a nova senha.');
            } else {
                Auth::updatePassword($new);
                Auth::logout();
                flash('success', 'Senha alterada. Entre de novo com a nova senha.');
                redirect('/login');
            }
            redirect('/profile');
        }

        view('profile', [
            'adminUser' => config('ADMIN_USER', 'admin'),
            'title' => 'Perfil',
            'active' => 'profile',
        ]);
        exit;
    }

    // --- tudo abaixo exige login ---
    Auth::requireLogin();
    $db = Database::pdo();

    if ($path === '' || $path === 'dashboard') {
        $account = $db->query('SELECT * FROM ig_accounts ORDER BY id DESC LIMIT 1')->fetch();
        $automations = [];
        if ($account) {
            $stmt = $db->prepare('SELECT * FROM automations WHERE ig_account_id = ? ORDER BY id DESC');
            $stmt->execute([$account['id']]);
            $automations = $stmt->fetchAll();
        }
        view('dashboard', ['account' => $account, 'automations' => $automations, 'title' => 'Automações', 'active' => 'dashboard']);
        exit;
    }

    if ($path === 'connect') {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        redirect_external(InstagramClient::getAuthorizationUrl($state));
    }

    if ($path === 'connect/callback') {
        $state = $_GET['state'] ?? '';
        if (!hash_equals($_SESSION['oauth_state'] ?? '', (string) $state)) {
            flash('error', 'Estado inválido no retorno do Instagram. Tente conectar novamente.');
            redirect('/');
        }
        if (!empty($_GET['error'])) {
            flash('error', 'Você cancelou a conexão com o Instagram.');
            redirect('/');
        }

        $code = (string) ($_GET['code'] ?? '');
        $short = InstagramClient::exchangeCodeForToken($code);
        $long = InstagramClient::exchangeForLongLivedToken((string) $short['access_token']);
        $profile = InstagramClient::getProfile((string) $long['access_token']);

        $expiresAt = (new DateTimeImmutable())
            ->modify('+' . (int) ($long['expires_in'] ?? 5184000) . ' seconds')
            ->format('Y-m-d H:i:s');

        $stmt = $db->prepare(
            'INSERT INTO ig_accounts (ig_user_id, username, access_token, token_expires_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE username = VALUES(username),
                access_token = VALUES(access_token),
                token_expires_at = VALUES(token_expires_at)'
        );
        $stmt->execute([$profile['user_id'], $profile['username'], $long['access_token'], $expiresAt]);

        flash('success', 'Conta @' . $profile['username'] . ' conectada com sucesso.');
        redirect('/');
    }

    if ($path === 'automations/new') {
        $account = $db->query('SELECT * FROM ig_accounts ORDER BY id DESC LIMIT 1')->fetch();
        if (!$account) {
            flash('error', 'Conecte sua conta do Instagram antes de criar uma automação.');
            redirect('/');
        }

        if ($method === 'POST') {
            csrf_verify();
            create_or_update_automation($db, null, (int) $account['id']);
            flash('success', 'Automação criada.');
            redirect('/');
        }

        view('automation_form', [
            'automation' => null,
            'packs' => fetch_packs($db, (int) $account['id']),
            'mediaList' => fetch_media_picker($account),
            'title' => 'Nova automação',
            'active' => 'dashboard',
        ]);
        exit;
    }

    if (preg_match('#^automations/(\d+)/edit$#', $path, $m)) {
        $id = (int) $m[1];
        $stmt = $db->prepare('SELECT * FROM automations WHERE id = ?');
        $stmt->execute([$id]);
        $automation = $stmt->fetch();
        if (!$automation) {
            flash('error', 'Automação não encontrada.');
            redirect('/');
        }

        if ($method === 'POST') {
            csrf_verify();
            create_or_update_automation($db, $id, (int) $automation['ig_account_id']);
            flash('success', 'Automação atualizada.');
            redirect('/');
        }

        $accStmt = $db->prepare('SELECT * FROM ig_accounts WHERE id = ?');
        $accStmt->execute([$automation['ig_account_id']]);
        $account = $accStmt->fetch();

        view('automation_form', [
            'automation' => $automation,
            'packs' => fetch_packs($db, (int) $automation['ig_account_id']),
            'mediaList' => fetch_media_picker($account),
            'title' => 'Editar automação',
            'active' => 'dashboard',
        ]);
        exit;
    }

    if (preg_match('#^automations/(\d+)/flow$#', $path, $m)) {
        $id = (int) $m[1];
        $stmt = $db->prepare('SELECT * FROM automations WHERE id = ?');
        $stmt->execute([$id]);
        $automation = $stmt->fetch();
        if (!$automation) {
            flash('error', 'Automação não encontrada.');
            redirect('/');
        }

        $accStmt = $db->prepare('SELECT * FROM ig_accounts WHERE id = ?');
        $accStmt->execute([$automation['ig_account_id']]);
        $account = $accStmt->fetch();

        view('flow_editor', [
            'automation' => $automation,
            'packs' => fetch_packs($db, (int) $automation['ig_account_id']),
            'mediaList' => fetch_media_picker($account),
        ]);
        exit;
    }

    if ($path === 'packs') {
        $account = $db->query('SELECT * FROM ig_accounts ORDER BY id DESC LIMIT 1')->fetch();
        $packs = [];
        if ($account) {
            $stmt = $db->prepare(
                'SELECT p.*, COUNT(i.id) AS item_count
                 FROM reply_packs p LEFT JOIN reply_pack_items i ON i.pack_id = p.id
                 WHERE p.ig_account_id = ?
                 GROUP BY p.id ORDER BY p.id DESC'
            );
            $stmt->execute([$account['id']]);
            $packs = $stmt->fetchAll();
        }
        view('packs', ['packs' => $packs, 'title' => 'Pack de respostas', 'active' => 'packs']);
        exit;
    }

    if ($path === 'packs/new') {
        $account = $db->query('SELECT * FROM ig_accounts ORDER BY id DESC LIMIT 1')->fetch();
        if (!$account) {
            flash('error', 'Conecte sua conta do Instagram antes de criar um pack.');
            redirect('/');
        }

        if ($method === 'POST') {
            csrf_verify();
            create_or_update_pack($db, null, (int) $account['id']);
            flash('success', 'Pack criado.');
            redirect('/packs');
        }

        view('pack_form', ['pack' => null, 'items' => [], 'title' => 'Novo pack', 'active' => 'packs']);
        exit;
    }

    if (preg_match('#^packs/(\d+)/edit$#', $path, $m)) {
        $id = (int) $m[1];
        $stmt = $db->prepare('SELECT * FROM reply_packs WHERE id = ?');
        $stmt->execute([$id]);
        $pack = $stmt->fetch();
        if (!$pack) {
            flash('error', 'Pack não encontrado.');
            redirect('/packs');
        }

        if ($method === 'POST') {
            csrf_verify();
            create_or_update_pack($db, $id, (int) $pack['ig_account_id']);
            flash('success', 'Pack atualizado.');
            redirect('/packs');
        }

        $itemsStmt = $db->prepare('SELECT text FROM reply_pack_items WHERE pack_id = ? ORDER BY id ASC');
        $itemsStmt->execute([$id]);
        $items = $itemsStmt->fetchAll();

        view('pack_form', ['pack' => $pack, 'items' => $items, 'title' => 'Editar pack', 'active' => 'packs']);
        exit;
    }

    if (preg_match('#^packs/(\d+)/delete$#', $path, $m) && $method === 'POST') {
        csrf_verify();
        $stmt = $db->prepare('DELETE FROM reply_packs WHERE id = ?');
        $stmt->execute([(int) $m[1]]);
        flash('success', 'Pack excluído.');
        redirect('/packs');
    }

    if ($path === 'crm') {
        $account = $db->query('SELECT * FROM ig_accounts ORDER BY id DESC LIMIT 1')->fetch();
        $opportunities = [];
        $funnel = array_fill_keys(array_keys(Crm::STATUSES), 0);
        $totals = ['vendido' => 0, 'recebido' => 0, 'ganhos' => 0, 'ativas' => 0];

        $statusFilter = (string) ($_GET['status'] ?? '');
        $segmentFilter = (string) ($_GET['segment'] ?? '');

        if ($account) {
            $funnelStmt = $db->prepare('SELECT status, COUNT(*) c FROM crm_opportunities WHERE ig_account_id = ? GROUP BY status');
            $funnelStmt->execute([$account['id']]);
            foreach ($funnelStmt->fetchAll() as $row) {
                $funnel[$row['status']] = (int) $row['c'];
            }

            $totalsStmt = $db->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN status = 'ganho' THEN proposed_value ELSE 0 END), 0) AS vendido,
                    COALESCE(SUM(received_value), 0) AS recebido,
                    SUM(CASE WHEN status = 'ganho' THEN 1 ELSE 0 END) AS ganhos,
                    SUM(CASE WHEN status NOT IN ('ganho','perdido') THEN 1 ELSE 0 END) AS ativas
                 FROM crm_opportunities WHERE ig_account_id = ?"
            );
            $totalsStmt->execute([$account['id']]);
            $totals = $totalsStmt->fetch() ?: $totals;

            $sql = 'SELECT * FROM crm_opportunities WHERE ig_account_id = ?';
            $queryParams = [$account['id']];
            if ($statusFilter !== '' && isset(Crm::STATUSES[$statusFilter])) {
                $sql .= ' AND status = ?';
                $queryParams[] = $statusFilter;
            }
            if ($segmentFilter !== '' && isset(Crm::SEGMENTS[$segmentFilter])) {
                $sql .= ' AND segment = ?';
                $queryParams[] = $segmentFilter;
            }
            $sql .= ' ORDER BY score_total DESC, updated_at DESC';
            $listStmt = $db->prepare($sql);
            $listStmt->execute($queryParams);
            $opportunities = $listStmt->fetchAll();
        }

        $searchJob = null;
        if ($account) {
            $jobStmt = $db->prepare('SELECT * FROM crm_search_jobs WHERE ig_account_id = ? ORDER BY id DESC LIMIT 1');
            $jobStmt->execute([$account['id']]);
            $searchJob = $jobStmt->fetch() ?: null;
        }

        view('crm_list', [
            'opportunities' => $opportunities,
            'funnel' => $funnel,
            'totals' => $totals,
            'statusFilter' => $statusFilter,
            'segmentFilter' => $segmentFilter,
            'searchJob' => $searchJob,
            'title' => 'CRM',
            'active' => 'crm',
        ]);
        exit;
    }

    if ($path === 'crm/auto-search' && $method === 'POST') {
        csrf_verify();
        $account = $db->query('SELECT * FROM ig_accounts ORDER BY id DESC LIMIT 1')->fetch();
        if (!$account) {
            flash('error', 'Conecte sua conta do Instagram antes de usar o CRM.');
            redirect('/crm');
        }

        $pending = $db->prepare(
            "SELECT id FROM crm_search_jobs WHERE ig_account_id = ? AND status IN ('fila','buscando') LIMIT 1"
        );
        $pending->execute([$account['id']]);
        if ($pending->fetch()) {
            flash('error', 'Já tem uma busca em andamento. Aguarde terminar antes de iniciar outra.');
            redirect('/crm');
        }

        $requested = max(1, min(9, (int) ($_POST['requested_count'] ?? 5)));
        $db->prepare('INSERT INTO crm_search_jobs (ig_account_id, requested_count) VALUES (?, ?)')
            ->execute([$account['id'], $requested]);

        flash('success', 'Busca automática iniciada. Isso roda em segundo plano e pode levar alguns minutos — atualize a página pra ver o resultado.');
        redirect('/crm');
    }

    if ($path === 'crm/new') {
        $account = $db->query('SELECT * FROM ig_accounts ORDER BY id DESC LIMIT 1')->fetch();
        if (!$account) {
            flash('error', 'Conecte sua conta do Instagram antes de usar o CRM.');
            redirect('/');
        }

        if ($method === 'POST') {
            csrf_verify();
            create_or_update_opportunity($db, null, (int) $account['id']);
            flash('success', 'Oportunidade criada.');
            redirect('/crm');
        }

        view('crm_form', ['opportunity' => null, 'interactions' => [], 'title' => 'Nova oportunidade', 'active' => 'crm']);
        exit;
    }

    if (preg_match('#^crm/(\d+)/edit$#', $path, $m)) {
        $id = (int) $m[1];
        $stmt = $db->prepare('SELECT * FROM crm_opportunities WHERE id = ?');
        $stmt->execute([$id]);
        $opportunity = $stmt->fetch();
        if (!$opportunity) {
            flash('error', 'Oportunidade não encontrada.');
            redirect('/crm');
        }

        if ($method === 'POST') {
            csrf_verify();
            create_or_update_opportunity($db, $id, (int) $opportunity['ig_account_id']);
            flash('success', 'Oportunidade atualizada.');
            redirect('/crm/' . $id . '/edit');
        }

        $intStmt = $db->prepare('SELECT * FROM crm_interactions WHERE opportunity_id = ? ORDER BY happened_at DESC');
        $intStmt->execute([$id]);
        $interactions = $intStmt->fetchAll();

        view('crm_form', ['opportunity' => $opportunity, 'interactions' => $interactions, 'title' => $opportunity['company_name'], 'active' => 'crm']);
        exit;
    }

    if (preg_match('#^crm/(\d+)/interactions$#', $path, $m) && $method === 'POST') {
        csrf_verify();
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if ($notes !== '') {
            $stmt = $db->prepare("INSERT INTO crm_interactions (opportunity_id, type, notes) VALUES (?, 'nota', ?)");
            $stmt->execute([(int) $m[1], $notes]);
            $db->prepare('UPDATE crm_opportunities SET last_interaction_at = CURDATE() WHERE id = ?')->execute([(int) $m[1]]);
        }
        redirect('/crm/' . $m[1] . '/edit');
    }

    if (preg_match('#^crm/(\d+)/delete$#', $path, $m) && $method === 'POST') {
        csrf_verify();
        $stmt = $db->prepare('DELETE FROM crm_opportunities WHERE id = ?');
        $stmt->execute([(int) $m[1]]);
        flash('success', 'Oportunidade excluída.');
        redirect('/crm');
    }

    if (preg_match('#^crm/(\d+)/status$#', $path, $m) && $method === 'POST') {
        csrf_verify();
        header('Content-Type: application/json');
        $newStatus = (string) ($_POST['status'] ?? '');
        if (!isset(Crm::STATUSES[$newStatus])) {
            http_response_code(422);
            echo json_encode(['ok' => false]);
            exit;
        }

        $id = (int) $m[1];
        $prev = $db->prepare('SELECT status FROM crm_opportunities WHERE id = ?');
        $prev->execute([$id]);
        $prevStatus = $prev->fetchColumn();
        if ($prevStatus === false) {
            http_response_code(404);
            echo json_encode(['ok' => false]);
            exit;
        }

        if ($prevStatus !== $newStatus) {
            $db->prepare('UPDATE crm_opportunities SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
            $db->prepare("INSERT INTO crm_interactions (opportunity_id, type, notes) VALUES (?, 'status', ?)")
                ->execute([$id, 'Status alterado de "' . Crm::statusLabel((string) $prevStatus) . '" para "' . Crm::statusLabel($newStatus) . '".']);
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    if (preg_match('#^automations/(\d+)/toggle$#', $path, $m) && $method === 'POST') {
        csrf_verify();
        $stmt = $db->prepare('UPDATE automations SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([(int) $m[1]]);
        redirect('/');
    }

    if (preg_match('#^automations/(\d+)/delete$#', $path, $m) && $method === 'POST') {
        csrf_verify();
        $stmt = $db->prepare('DELETE FROM automations WHERE id = ?');
        $stmt->execute([(int) $m[1]]);
        flash('success', 'Automação excluída.');
        redirect('/');
    }

    if ($path === 'logs') {
        // só mostra atividade real (comentário que bateu com alguma automação) —
        // os milhares de comentários sem correspondência não aparecem mais aqui.
        $perPage = 50;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $total = (int) $db->query('SELECT COUNT(*) FROM automation_logs WHERE matched = 1')->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $db->prepare(
            'SELECT l.*, a.name AS automation_name
             FROM automation_logs l
             LEFT JOIN automations a ON a.id = l.automation_id
             WHERE l.matched = 1
             ORDER BY l.id DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute();
        $logs = $stmt->fetchAll();

        view('logs', [
            'logs' => $logs,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'title' => 'Respostas enviadas',
            'active' => 'logs',
        ]);
        exit;
    }

    if ($path === 'content') {
        $account = $db->query('SELECT * FROM ig_accounts ORDER BY id DESC LIMIT 1')->fetch();
        $posts = [];
        $error = null;

        if ($account) {
            try {
                $posts = InstagramClient::getMediaWithEngagement((string) $account['access_token'], 50);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        foreach ($posts as &$p) {
            $p['likes'] = (int) ($p['like_count'] ?? 0);
            $p['comments'] = (int) ($p['comments_count'] ?? 0);
            $p['engagement'] = $p['likes'] + $p['comments'];
            $p['type_label'] = content_type_label($p);
        }
        unset($p);

        $oldestLoaded = null;
        $newestLoaded = null;
        foreach ($posts as $p) {
            $day = substr((string) $p['timestamp'], 0, 10);
            if ($oldestLoaded === null || $day < $oldestLoaded) {
                $oldestLoaded = $day;
            }
            if ($newestLoaded === null || $day > $newestLoaded) {
                $newestLoaded = $day;
            }
        }

        $fromFilter = (string) ($_GET['from'] ?? '');
        $toFilter = (string) ($_GET['to'] ?? '');
        if ($fromFilter !== '') {
            $posts = array_values(array_filter($posts, static fn ($p) => substr((string) $p['timestamp'], 0, 10) >= $fromFilter));
        }
        if ($toFilter !== '') {
            $posts = array_values(array_filter($posts, static fn ($p) => substr((string) $p['timestamp'], 0, 10) <= $toFilter));
        }

        $typeFilter = (string) ($_GET['type'] ?? '');
        if ($typeFilter !== '') {
            $posts = array_values(array_filter($posts, static fn ($p) => $p['type_label'] === $typeFilter));
        }

        $sort = (string) ($_GET['sort'] ?? 'engagement');
        usort($posts, static function ($a, $b) use ($sort) {
            return match ($sort) {
                'likes' => $b['likes'] <=> $a['likes'],
                'comments' => $b['comments'] <=> $a['comments'],
                'recent' => strcmp((string) $b['timestamp'], (string) $a['timestamp']),
                default => $b['engagement'] <=> $a['engagement'],
            };
        });

        $maxEngagement = 0;
        foreach ($posts as $p) {
            $maxEngagement = max($maxEngagement, $p['engagement']);
        }

        $byType = [];
        foreach ($posts as $p) {
            $t = $p['type_label'];
            $byType[$t]['count'] = ($byType[$t]['count'] ?? 0) + 1;
            $byType[$t]['engagement'] = ($byType[$t]['engagement'] ?? 0) + $p['engagement'];
        }
        foreach ($byType as $t => $data) {
            $byType[$t]['avg'] = $data['count'] > 0 ? round($data['engagement'] / $data['count'], 1) : 0;
        }
        uasort($byType, static fn ($a, $b) => $b['avg'] <=> $a['avg']);

        $avgEngagement = count($posts) > 0 ? round(array_sum(array_column($posts, 'engagement')) / count($posts), 1) : 0;

        view('content_analysis', [
            'account' => $account,
            'posts' => $posts,
            'byType' => $byType,
            'avgEngagement' => $avgEngagement,
            'maxEngagement' => $maxEngagement,
            'sort' => $sort,
            'typeFilter' => $typeFilter,
            'fromFilter' => $fromFilter,
            'toFilter' => $toFilter,
            'oldestLoaded' => $oldestLoaded,
            'newestLoaded' => $newestLoaded,
            'error' => $error,
            'title' => 'Análise de Conteúdo',
            'active' => 'content',
        ]);
        exit;
    }

    http_response_code(404);
    echo '404 - página não encontrada.';
} catch (Throwable $e) {
    http_response_code(500);
    if (config('APP_DEBUG', '0') === '1') {
        echo '<pre>' . h($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
    } else {
        echo 'Ocorreu um erro. Tente novamente.';
    }
}

function redirect_external(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function content_type_label(array $media): string
{
    $product = $media['media_product_type'] ?? '';
    $type = $media['media_type'] ?? '';
    if ($product === 'REELS') {
        return 'Reels';
    }
    if ($type === 'CAROUSEL_ALBUM') {
        return 'Carrossel';
    }
    if ($type === 'VIDEO') {
        return 'Vídeo';
    }
    if ($type === 'IMAGE') {
        return 'Imagem';
    }
    return 'Outro';
}

function fetch_packs(PDO $db, int $igAccountId): array
{
    $stmt = $db->prepare('SELECT id, name FROM reply_packs WHERE ig_account_id = ? ORDER BY name ASC');
    $stmt->execute([$igAccountId]);
    return $stmt->fetchAll();
}

function fetch_media_picker(?array $account): array
{
    if (!$account) {
        return [];
    }
    try {
        return InstagramClient::getMediaForPicker((string) $account['access_token'], 15);
    } catch (Throwable $e) {
        return [];
    }
}

function create_or_update_opportunity(PDO $db, ?int $id, int $igAccountId): void
{
    $companyName = trim((string) ($_POST['company_name'] ?? ''));
    if ($companyName === '') {
        flash('error', 'Preencha o nome da empresa.');
        redirect($id ? "/crm/{$id}/edit" : '/crm/new');
    }

    $segment = ($_POST['segment'] ?? 'A') === 'B' ? 'B' : 'A';
    $profileUrl = trim((string) ($_POST['profile_url'] ?? '')) ?: null;
    $instagramUrl = trim((string) ($_POST['instagram_url'] ?? '')) ?: null;
    $contactName = trim((string) ($_POST['contact_name'] ?? '')) ?: null;
    $contactRole = trim((string) ($_POST['contact_role'] ?? '')) ?: null;
    $source = trim((string) ($_POST['source'] ?? '')) ?: null;
    $problemObserved = trim((string) ($_POST['problem_observed'] ?? '')) ?: null;

    $scores = [];
    foreach (array_keys(Crm::SCORE_CRITERIA) as $field) {
        $scores[$field] = max(0, min(2, (int) ($_POST[$field] ?? 0)));
    }

    $status = (string) ($_POST['status'] ?? 'pesquisado');
    if (!isset(Crm::STATUSES[$status])) {
        $status = 'pesquisado';
    }
    $lossReason = $status === 'perdido' ? (trim((string) ($_POST['loss_reason'] ?? '')) ?: null) : null;

    $proposedValue = ($_POST['proposed_value'] ?? '') !== '' ? (float) $_POST['proposed_value'] : null;
    $receivedValue = (float) ($_POST['received_value'] ?? 0);
    $nextAction = trim((string) ($_POST['next_action'] ?? '')) ?: null;
    $nextActionAt = trim((string) ($_POST['next_action_at'] ?? '')) ?: null;
    $responsible = trim((string) ($_POST['responsible'] ?? '')) ?: null;
    $consentContext = trim((string) ($_POST['consent_context'] ?? '')) ?: null;
    $outreachScript = trim((string) ($_POST['outreach_script'] ?? '')) ?: Crm::buildOutreachScript($companyName, $segment, $problemObserved);

    try {
        if ($id === null) {
            $stmt = $db->prepare(
                'INSERT INTO crm_opportunities
                    (ig_account_id, company_name, segment, profile_url, instagram_url, contact_name, contact_role, source,
                     score_valor_alto, score_problema, score_momento, score_decisor, score_investimento,
                     problem_observed, status, proposed_value, received_value, next_action, next_action_at,
                     responsible, consent_context, loss_reason, outreach_script)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $igAccountId, $companyName, $segment, $profileUrl, $instagramUrl, $contactName, $contactRole, $source,
                $scores['score_valor_alto'], $scores['score_problema'], $scores['score_momento'],
                $scores['score_decisor'], $scores['score_investimento'],
                $problemObserved, $status, $proposedValue, $receivedValue, $nextAction, $nextActionAt,
                $responsible, $consentContext, $lossReason, $outreachScript,
            ]);
        } else {
            $prev = $db->prepare('SELECT status FROM crm_opportunities WHERE id = ?');
            $prev->execute([$id]);
            $prevStatus = $prev->fetchColumn();

            $stmt = $db->prepare(
                'UPDATE crm_opportunities SET
                    company_name = ?, segment = ?, profile_url = ?, instagram_url = ?, contact_name = ?, contact_role = ?, source = ?,
                    score_valor_alto = ?, score_problema = ?, score_momento = ?, score_decisor = ?, score_investimento = ?,
                    problem_observed = ?, status = ?, proposed_value = ?, received_value = ?, next_action = ?, next_action_at = ?,
                    responsible = ?, consent_context = ?, loss_reason = ?, outreach_script = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $companyName, $segment, $profileUrl, $instagramUrl, $contactName, $contactRole, $source,
                $scores['score_valor_alto'], $scores['score_problema'], $scores['score_momento'],
                $scores['score_decisor'], $scores['score_investimento'],
                $problemObserved, $status, $proposedValue, $receivedValue, $nextAction, $nextActionAt,
                $responsible, $consentContext, $lossReason, $outreachScript, $id,
            ]);

            if ($prevStatus !== false && $prevStatus !== $status) {
                $log = $db->prepare(
                    "INSERT INTO crm_interactions (opportunity_id, type, notes) VALUES (?, 'status', ?)"
                );
                $log->execute([$id, 'Status alterado de "' . Crm::statusLabel((string) $prevStatus) . '" para "' . Crm::statusLabel($status) . '".']);
            }
        }
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            flash('error', 'Já existe uma oportunidade cadastrada com esse nome de empresa.');
            redirect($id ? "/crm/{$id}/edit" : '/crm/new');
        }
        throw $e;
    }
}

function create_or_update_pack(PDO $db, ?int $id, int $igAccountId): void
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $lines = preg_split('/\r\n|\r|\n/', (string) ($_POST['items'] ?? ''));
    $items = array_values(array_filter(array_map('trim', $lines), static fn (string $l) => $l !== ''));

    if ($name === '' || !$items) {
        flash('error', 'Preencha o nome e pelo menos uma resposta.');
        redirect($id ? "/packs/{$id}/edit" : '/packs/new');
    }

    if ($id === null) {
        $stmt = $db->prepare('INSERT INTO reply_packs (ig_account_id, name) VALUES (?, ?)');
        $stmt->execute([$igAccountId, $name]);
        $id = (int) $db->lastInsertId();
    } else {
        $stmt = $db->prepare('UPDATE reply_packs SET name = ? WHERE id = ?');
        $stmt->execute([$name, $id]);
        $db->prepare('DELETE FROM reply_pack_items WHERE pack_id = ?')->execute([$id]);
    }

    $insertItem = $db->prepare('INSERT INTO reply_pack_items (pack_id, text) VALUES (?, ?)');
    foreach ($items as $item) {
        $insertItem->execute([$id, $item]);
    }
}

function create_or_update_automation(PDO $db, ?int $id, int $igAccountId): void
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $keywords = trim((string) ($_POST['keywords'] ?? ''));
    $matchType = ($_POST['match_type'] ?? 'any') === 'all' ? 'all' : 'any';
    $isActive = !empty($_POST['is_active']) ? 1 : 0;

    $targetScope = $_POST['target_scope'] ?? 'all';
    if (!in_array($targetScope, ['all', 'next', 'specific'], true)) {
        $targetScope = 'all';
    }
    $targetMediaId = null;
    if ($targetScope === 'specific') {
        $targetMediaId = trim((string) ($_POST['target_media_id'] ?? ''));
        if ($targetMediaId === '') {
            flash('error', 'Escolha qual publicação essa automação deve responder.');
            redirect($id ? "/automations/{$id}/edit" : '/automations/new');
        }
    } elseif ($targetScope === 'next' && $id !== null) {
        // preserva o alvo já resolvido, a menos que a automação não fosse "próxima" antes
        $prev = $db->prepare('SELECT target_scope, target_media_id FROM automations WHERE id = ?');
        $prev->execute([$id]);
        $prevRow = $prev->fetch();
        if ($prevRow && $prevRow['target_scope'] === 'next') {
            $targetMediaId = $prevRow['target_media_id'];
        }
    }

    $replySource = $_POST['reply_source'] ?? 'text';
    $replyText = trim((string) ($_POST['reply_text'] ?? ''));
    $replyPackId = $replySource === 'pack' ? (int) ($_POST['reply_pack_id'] ?? 0) : null;
    if ($replySource === 'pack' && !$replyPackId) {
        flash('error', 'Escolha um pack de respostas para o comentário.');
        redirect($id ? "/automations/{$id}/edit" : '/automations/new');
    }
    if ($replySource !== 'pack' && $replyText === '') {
        flash('error', 'Preencha o texto da resposta do comentário ou escolha um pack.');
        redirect($id ? "/automations/{$id}/edit" : '/automations/new');
    }

    $sendDm = !empty($_POST['send_dm']) ? 1 : 0;
    $dmSource = $_POST['dm_source'] ?? 'text';
    $dmText = trim((string) ($_POST['dm_text'] ?? ''));
    $dmPackId = $dmSource === 'pack' ? (int) ($_POST['dm_pack_id'] ?? 0) : null;
    if ($sendDm && $dmSource === 'pack' && !$dmPackId) {
        flash('error', 'Escolha um pack de respostas para a DM.');
        redirect($id ? "/automations/{$id}/edit" : '/automations/new');
    }
    if ($sendDm && $dmSource !== 'pack' && $dmText === '') {
        flash('error', 'Preencha o texto da DM, escolha um pack, ou desmarque a opção de enviar DM.');
        redirect($id ? "/automations/{$id}/edit" : '/automations/new');
    }

    if ($name === '' || $keywords === '') {
        flash('error', 'Preencha nome e palavras-chave.');
        redirect($id ? "/automations/{$id}/edit" : '/automations/new');
    }

    $delayMinutes = max(0, (int) ($_POST['delay_minutes'] ?? 0));

    $dmButtonLabel = trim((string) ($_POST['dm_button_label'] ?? '')) ?: null;
    $dmButtonUrl = trim((string) ($_POST['dm_button_url'] ?? '')) ?: null;
    if ($dmButtonLabel !== null) {
        $dmButtonLabel = mb_substr($dmButtonLabel, 0, 20);
    }
    if (($dmButtonLabel !== null) !== ($dmButtonUrl !== null)) {
        flash('error', 'Preencha o texto E o link do botão da DM, ou deixe os dois em branco.');
        redirect($id ? "/automations/{$id}/edit" : '/automations/new');
    }

    if ($id === null) {
        $stmt = $db->prepare(
            'INSERT INTO automations
                (ig_account_id, name, keywords, match_type, target_scope, target_media_id, delay_minutes,
                 reply_text, reply_pack_id, send_dm, dm_text, dm_pack_id, dm_button_label, dm_button_url, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $igAccountId, $name, $keywords, $matchType, $targetScope, $targetMediaId, $delayMinutes,
            $replyText, $replyPackId, $sendDm, $dmText, $dmPackId, $dmButtonLabel, $dmButtonUrl, $isActive,
        ]);
    } else {
        $stmt = $db->prepare(
            'UPDATE automations SET
                name = ?, keywords = ?, match_type = ?, target_scope = ?, target_media_id = ?, delay_minutes = ?,
                reply_text = ?, reply_pack_id = ?, send_dm = ?, dm_text = ?, dm_pack_id = ?,
                dm_button_label = ?, dm_button_url = ?, is_active = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $name, $keywords, $matchType, $targetScope, $targetMediaId, $delayMinutes,
            $replyText, $replyPackId, $sendDm, $dmText, $dmPackId, $dmButtonLabel, $dmButtonUrl, $isActive, $id,
        ]);
    }
}
