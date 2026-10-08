<?php
declare(strict_types=1);

/**
 * Busca automática de leads para o CRM usando a API da Anthropic (Claude)
 * com a ferramenta de busca na web. Reproduz o mesmo método usado manualmente
 * na pesquisa: achar empresas/marcas/pessoas com alta atividade comercial
 * comprovada que, verificadamente, não têm site oficial — e pontuar com
 * honestidade pelos mesmos 5 critérios do CRM (0 a 2 cada).
 */
final class CrmAutoSearch
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const MODEL = 'claude-sonnet-5-5';
    private const MAX_SEARCH_USES = 20;
    private const MAX_PAUSE_CONTINUATIONS = 4;

    /**
     * Executa a busca para um job em fila e grava as oportunidades encontradas.
     * Marca o job como "buscando" -> "concluido"/"erro".
     */
    public static function run(PDO $db, array $job): void
    {
        $jobId = (int) $job['id'];
        $igAccountId = (int) $job['ig_account_id'];
        $requested = (int) $job['requested_count'];

        $db->prepare("UPDATE crm_search_jobs SET status = 'buscando', started_at = NOW() WHERE id = ?")
            ->execute([$jobId]);

        try {
            $existing = $db->prepare('SELECT company_name FROM crm_opportunities WHERE ig_account_id = ?');
            $existing->execute([$igAccountId]);
            $existingNames = $existing->fetchAll(PDO::FETCH_COLUMN);

            $candidates = self::search($requested, $existingNames);
            $found = self::insertCandidates($db, $igAccountId, $candidates);

            $db->prepare(
                "UPDATE crm_search_jobs SET status = 'concluido', found_count = ?, finished_at = NOW() WHERE id = ?"
            )->execute([$found, $jobId]);
        } catch (Throwable $e) {
            $db->prepare(
                "UPDATE crm_search_jobs SET status = 'erro', error_message = ?, finished_at = NOW() WHERE id = ?"
            )->execute([$e->getMessage(), $jobId]);
        }
    }

    /**
     * Chama a API da Anthropic com web search e devolve os candidatos (array de arrays).
     */
    private static function search(int $requested, array $existingNames): array
    {
        $apiKey = config('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            throw new RuntimeException('ANTHROPIC_API_KEY não configurada no .env.');
        }

        $prompt = self::buildPrompt($requested, $existingNames);
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        $finalText = '';
        for ($i = 0; $i < self::MAX_PAUSE_CONTINUATIONS; $i++) {
            $response = self::callApi($apiKey, $messages);
            $content = $response['content'] ?? [];

            foreach ($content as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $finalText = $block['text'];
                }
            }

            if (($response['stop_reason'] ?? '') !== 'pause_turn') {
                break;
            }

            // Continua a busca pausada: devolve a mensagem do assistente como veio.
            $messages[] = ['role' => 'assistant', 'content' => $content];
        }

        return self::extractJson($finalText);
    }

    private static function buildPrompt(int $requested, array $existingNames): string
    {
        $criteria = implode("\n", array_map(
            static fn (string $field, string $label) => "- {$field}: {$label} (0, 1 ou 2)",
            array_keys(Crm::SCORE_CRITERIA),
            array_values(Crm::SCORE_CRITERIA)
        ));

        $skip = $existingNames
            ? "Não repita nenhuma destas empresas/pessoas, já cadastradas: " . implode('; ', $existingNames) . ".\n"
            : '';

        return <<<PROMPT
Você está ajudando a prospectar clientes para a Mars Design (marsdesigner.com.br), um estúdio
brasileiro de identidade visual e presença digital. O objetivo é achar até {$requested} empresas,
marcas ou pessoas públicas no Brasil que:

1. Têm atividade comercial comprovadamente alta (vendas relevantes, crescimento viral recente,
   grande base de seguidores engajada, ou faturamento conhecido) — isso precisa ser verificável
   por busca, não suposição.
2. NÃO têm site institucional próprio verificável (ou têm um site muito mal resolvido/quebrado).
   Isso PRECISA ser confirmado buscando o nome + "site oficial", checando o link na bio do
   Instagram, e etc. Nunca assuma que não existe site sem checar.

Use a ferramenta de busca na web livremente para verificar cada candidato antes de incluí-lo.
Se um candidato tiver site oficial decente, descarte-o e procure outro — não inclua "quase certezas".
{$skip}
Pontuação (0 a 2 em cada critério, sendo honesto — não infle):
{$criteria}

Priorize candidatos que fiquem entre 6 e 10 pontos. Se não achar {$requested} candidatos bons,
retorne menos — nunca invente ou force a pontuação pra completar a cota.

Quando terminar a pesquisa, responda SOMENTE com um bloco de código JSON (```json ... ```) como
última mensagem, contendo um array de objetos com exatamente estes campos:

- company_name (string): nome da empresa/marca/pessoa, com parênteses explicando quem é se não for óbvio
- segment ("A" ou "B"): A = indústria/B2B, B = marca/produto premium
- instagram_url (string): URL completa do perfil do Instagram
- problem_observed (string, em português): o problema concreto de presença digital observado
- next_action (string, em português): sugestão de próximo passo de abordagem
- consent_context (string, em português): nota breve sobre como fazer o primeiro contato
- score_valor_alto, score_problema, score_momento, score_decisor, score_investimento (inteiros 0-2)

Não escreva nada depois do bloco JSON.
PROMPT;
    }

    private static function callApi(string $apiKey, array $messages): array
    {
        $payload = [
            'model' => self::MODEL,
            'max_tokens' => 8192,
            'messages' => $messages,
            'tools' => [[
                'type' => 'web_search_20250305',
                'name' => 'web_search',
                'max_uses' => self::MAX_SEARCH_USES,
                'user_location' => ['type' => 'approximate', 'country' => 'BR'],
            ]],
        ];

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $apiKey,
                'anthropic-version: ' . self::API_VERSION,
                'content-type: application/json',
            ],
            CURLOPT_TIMEOUT => 280,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Falha ao chamar a API da Anthropic: ' . $error);
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string) $raw, true);
        if ($status >= 400 || !is_array($decoded)) {
            $message = $decoded['error']['message'] ?? $raw;
            throw new RuntimeException("API da Anthropic retornou erro ({$status}): {$message}");
        }

        return $decoded;
    }

    private static function extractJson(string $text): array
    {
        if (!preg_match('/```json\s*(\[.*?\])\s*```/s', $text, $m)) {
            if (!preg_match('/(\[.*\])/s', $text, $m)) {
                throw new RuntimeException('Não achei um JSON válido na resposta da IA.');
            }
        }

        $data = json_decode($m[1], true);
        if (!is_array($data)) {
            throw new RuntimeException('JSON retornado pela IA é inválido.');
        }

        return $data;
    }

    private static function insertCandidates(PDO $db, int $igAccountId, array $candidates): int
    {
        $stmt = $db->prepare(
            'INSERT IGNORE INTO crm_opportunities
                (ig_account_id, company_name, segment, instagram_url, source,
                 score_valor_alto, score_problema, score_momento, score_decisor, score_investimento,
                 problem_observed, status, next_action, consent_context, outreach_script, responsible)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pesquisado\', ?, ?, ?, \'Matheus\')'
        );

        $inserted = 0;
        foreach ($candidates as $c) {
            $companyName = trim((string) ($c['company_name'] ?? ''));
            if ($companyName === '') {
                continue;
            }

            $segment = ($c['segment'] ?? 'A') === 'B' ? 'B' : 'A';
            $score = static fn (string $key): int => max(0, min(2, (int) ($c[$key] ?? 0)));

            $problem = trim((string) ($c['problem_observed'] ?? '')) ?: null;

            $stmt->execute([
                $igAccountId,
                $companyName,
                $segment,
                trim((string) ($c['instagram_url'] ?? '')) ?: null,
                'Busca automática (Claude + web search)',
                $score('score_valor_alto'),
                $score('score_problema'),
                $score('score_momento'),
                $score('score_decisor'),
                $score('score_investimento'),
                $problem,
                trim((string) ($c['next_action'] ?? '')) ?: null,
                trim((string) ($c['consent_context'] ?? '')) ?: null,
                Crm::buildOutreachScript($companyName, $segment, $problem),
            ]);

            if ($stmt->rowCount() > 0) {
                $inserted++;
            }
        }

        return $inserted;
    }
}
