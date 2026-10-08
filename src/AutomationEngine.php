<?php
declare(strict_types=1);

final class AutomationEngine
{
    /**
     * Processa um evento de comentário vindo do webhook.
     *
     * @param array<string,mixed> $value payload de "changes[].value"
     */
    public static function handleComment(PDO $db, ?string $igAccountScopedId, array $value): void
    {
        $commentId = $value['id'] ?? null;
        $text = $value['text'] ?? '';
        $mediaId = $value['media']['id'] ?? null;
        $fromId = $value['from']['id'] ?? null;
        $fromUsername = $value['from']['username'] ?? null;

        if (!$commentId || !$igAccountScopedId) {
            return;
        }

        $account = self::findAccount($db, $igAccountScopedId);
        if (!$account) {
            return;
        }

        // Nunca reagir a comentários feitos pela própria conta (evita loop com a própria resposta).
        if ($fromId !== null && $fromId === $account['ig_user_id']) {
            return;
        }

        self::processComment($db, $account, $mediaId, (string) $commentId, $fromUsername, $text);
    }

    /**
     * Antes de processar comentários, resolve as automações com alvo "próxima
     * publicação" que ainda não têm um post definido: pega, entre as mídias
     * mais novas que a automação, a mais antiga delas (a primeira publicada
     * depois que a automação foi criada) e "trava" ela como alvo.
     *
     * @param array<int,array<string,mixed>> $mediaItems itens com pelo menos id e timestamp, mais recente primeiro
     */
    public static function resolveNextPostAutomations(PDO $db, int $igAccountId, array $mediaItems): void
    {
        $stmt = $db->prepare(
            "SELECT id, created_at FROM automations
             WHERE ig_account_id = ? AND target_scope = 'next' AND target_media_id IS NULL"
        );
        $stmt->execute([$igAccountId]);
        $pending = $stmt->fetchAll();

        if (!$pending) {
            return;
        }

        // ordena as mídias da mais antiga pra mais nova, pra achar a "próxima" de verdade
        $sorted = $mediaItems;
        usort($sorted, static fn (array $a, array $b): int => strcmp((string) ($a['timestamp'] ?? ''), (string) ($b['timestamp'] ?? '')));

        foreach ($pending as $automation) {
            $createdAt = strtotime((string) $automation['created_at']);
            foreach ($sorted as $media) {
                $mediaTime = strtotime((string) ($media['timestamp'] ?? ''));
                if ($mediaTime !== false && $createdAt !== false && $mediaTime > $createdAt) {
                    $update = $db->prepare('UPDATE automations SET target_media_id = ? WHERE id = ?');
                    $update->execute([$media['id'], $automation['id']]);
                    break;
                }
            }
        }
    }

    /**
     * Processa um comentário já identificado (usado tanto pelo webhook quanto
     * pelo polling via cron). Casa com as automações ativas e responde.
     */
    public static function processComment(PDO $db, array $account, ?string $mediaId, string $commentId, ?string $fromUsername, string $text): void
    {
        // Nunca reagir a comentários feitos pela própria conta (evita loop com a própria resposta).
        if ($fromUsername !== null && strcasecmp($fromUsername, (string) $account['username']) === 0) {
            return;
        }

        // Idempotência: se este comentário já foi processado (reentrega do webhook ou já visto no polling), ignora.
        if (self::alreadyProcessed($db, $commentId)) {
            return;
        }

        $automation = self::findMatchingAutomation($db, (int) $account['id'], $mediaId, $text);

        if (!$automation) {
            self::log($db, null, $commentId, $fromUsername, $text, false, false, false, null, null);
            return;
        }

        $delayMinutes = (int) ($automation['delay_minutes'] ?? 0);

        if ($delayMinutes > 0) {
            $runAt = (new DateTimeImmutable())->modify("+{$delayMinutes} minutes")->format('Y-m-d H:i:s');
            self::log($db, (int) $automation['id'], $commentId, $fromUsername, $text, true, false, false, null, $runAt);
            try {
                $stmt = $db->prepare(
                    'INSERT INTO scheduled_actions (automation_id, ig_account_id, media_id, ig_comment_id, commenter_username, comment_text, run_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([(int) $automation['id'], (int) $account['id'], $mediaId, $commentId, $fromUsername, $text, $runAt]);
            } catch (Throwable $e) {
                // idem: possível reentrega, ignora colisão de UNIQUE KEY
            }
            return;
        }

        self::executeActions($db, $account, $automation, $commentId, $fromUsername, $text);
    }

    /**
     * Executa de fato a resposta (comentário + DM) de uma automação já
     * identificada, e grava o resultado. Usado tanto na execução imediata
     * quanto pelo cron de ações agendadas (delay).
     */
    public static function executeActions(PDO $db, array $account, array $automation, string $commentId, ?string $fromUsername, string $text): void
    {
        $replySent = false;
        $dmSent = false;
        $errors = [];

        try {
            $replyText = self::applyVariables(
                self::resolveText($db, $automation['reply_text'], $automation['reply_pack_id']),
                $fromUsername
            );
            InstagramClient::replyToComment($commentId, (string) $account['access_token'], $replyText);
            $replySent = true;
        } catch (Throwable $e) {
            $errors[] = 'Comentário: ' . $e->getMessage();
        }

        if (!empty($automation['send_dm'])) {
            try {
                $dmText = self::applyVariables(
                    self::resolveText($db, $automation['dm_text'], $automation['dm_pack_id']),
                    $fromUsername
                );
                if ($dmText !== '') {
                    if (!empty($automation['dm_button_label']) && !empty($automation['dm_button_url'])) {
                        InstagramClient::sendPrivateReplyWithButton(
                            $commentId,
                            (string) $account['access_token'],
                            $dmText,
                            (string) $automation['dm_button_label'],
                            (string) $automation['dm_button_url']
                        );
                    } else {
                        InstagramClient::sendPrivateReply($commentId, (string) $account['access_token'], $dmText);
                    }
                    $dmSent = true;
                }
            } catch (Throwable $e) {
                $errors[] = 'DM: ' . $e->getMessage();
            }
        }

        $error = $errors ? implode(' | ', $errors) : null;

        if (self::alreadyProcessed($db, $commentId)) {
            self::updateLogResult($db, $commentId, $replySent, $dmSent, $error);
        } else {
            self::log($db, (int) $automation['id'], $commentId, $fromUsername, $text, true, $replySent, $dmSent, $error, null);
        }
    }

    /**
     * Marca um comentário como "já visto" sem rodar nenhuma automação nele.
     * Usado pra semear o histórico existente antes de ligar o polling, pra não
     * sair respondendo comentários antigos na primeira rodada.
     */
    public static function markAsSeen(PDO $db, string $commentId, ?string $username, string $text): void
    {
        if (self::alreadyProcessed($db, $commentId)) {
            return;
        }
        self::log($db, null, $commentId, $username, $text, false, false, false, null, null);
    }

    /**
     * Resolve o texto final a enviar: se a automação usa um pack de respostas,
     * sorteia um item dele; senão usa o texto fixo cadastrado.
     */
    private static function resolveText(PDO $db, ?string $fixedText, $packId): string
    {
        if (!empty($packId)) {
            $stmt = $db->prepare('SELECT text FROM reply_pack_items WHERE pack_id = ?');
            $stmt->execute([(int) $packId]);
            $items = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($items) {
                return (string) $items[array_rand($items)];
            }
        }
        return trim((string) $fixedText);
    }

    /**
     * Substitui variáveis tipo {{usuario}} pelo valor real antes de enviar.
     * {{usuario}} vira @usuario (menção de verdade no Instagram).
     */
    private static function applyVariables(string $text, ?string $username): string
    {
        return str_replace('{{usuario}}', $username ? '@' . $username : '', $text);
    }

    private static function findAccount(PDO $db, string $igUserId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM ig_accounts WHERE ig_user_id = ? LIMIT 1');
        $stmt->execute([$igUserId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function alreadyProcessed(PDO $db, string $commentId): bool
    {
        $stmt = $db->prepare('SELECT id FROM automation_logs WHERE ig_comment_id = ? LIMIT 1');
        $stmt->execute([$commentId]);
        return (bool) $stmt->fetch();
    }

    private static function findMatchingAutomation(PDO $db, int $igAccountId, ?string $mediaId, string $commentText): ?array
    {
        $stmt = $db->prepare(
            "SELECT * FROM automations WHERE ig_account_id = ? AND is_active = 1 ORDER BY id ASC"
        );
        $stmt->execute([$igAccountId]);
        $automations = $stmt->fetchAll();

        $haystack = self::normalize($commentText);

        foreach ($automations as $automation) {
            // alvo de publicação: "todas" sempre serve; "próxima"/"específica" só se bater a mídia
            if ($automation['target_scope'] !== 'all') {
                if ($automation['target_media_id'] === null || $mediaId === null) {
                    continue;
                }
                if ((string) $automation['target_media_id'] !== (string) $mediaId) {
                    continue;
                }
            }

            $keywords = array_filter(array_map('trim', explode(',', (string) $automation['keywords'])));
            if (!$keywords) {
                continue;
            }

            $matches = array_map(
                static fn (string $kw): bool => str_contains($haystack, self::normalize($kw)),
                $keywords
            );

            $isMatch = $automation['match_type'] === 'all'
                ? !in_array(false, $matches, true)
                : in_array(true, $matches, true);

            if ($isMatch) {
                return $automation;
            }
        }

        return null;
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        // remove acentos (Código == Codigo), pra não perder gente que digita certo em português
        $decomposed = Normalizer::normalize($value, Normalizer::FORM_D);
        if ($decomposed !== false) {
            $value = preg_replace('/\p{Mn}/u', '', $decomposed);
        }

        return $value;
    }

    private static function log(
        PDO $db,
        ?int $automationId,
        string $commentId,
        ?string $commenterUsername,
        string $commentText,
        bool $matched,
        bool $replySent,
        bool $dmSent,
        ?string $error,
        ?string $scheduledFor
    ): void {
        try {
            $stmt = $db->prepare(
                'INSERT INTO automation_logs
                    (automation_id, ig_comment_id, commenter_username, comment_text, matched, reply_sent, dm_sent, scheduled_for, error)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $automationId,
                $commentId,
                $commenterUsername,
                $commentText,
                $matched ? 1 : 0,
                $replySent ? 1 : 0,
                $dmSent ? 1 : 0,
                $scheduledFor,
                $error,
            ]);
        } catch (Throwable $e) {
            // Reentrega do Meta pode colidir com a UNIQUE KEY em ig_comment_id; ignora silenciosamente.
        }
    }

    /**
     * Atualiza o resultado de um log já existente (usado quando uma ação
     * agendada com delay finalmente executa).
     */
    private static function updateLogResult(PDO $db, string $commentId, bool $replySent, bool $dmSent, ?string $error): void
    {
        $stmt = $db->prepare(
            'UPDATE automation_logs SET reply_sent = ?, dm_sent = ?, error = ?, scheduled_for = NULL WHERE ig_comment_id = ?'
        );
        $stmt->execute([$replySent ? 1 : 0, $dmSent ? 1 : 0, $error, $commentId]);
    }
}
