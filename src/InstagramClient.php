<?php
declare(strict_types=1);

/**
 * Fina camada sobre a "Instagram API with Instagram Login" da Meta.
 * Docs: https://developers.facebook.com/docs/instagram-platform
 */
final class InstagramClient
{
    private const AUTH_BASE = 'https://www.instagram.com/oauth/authorize';
    private const TOKEN_URL = 'https://api.instagram.com/oauth/access_token';
    private const GRAPH_BASE = 'https://graph.instagram.com';
    private const GRAPH_VERSION = 'v21.0';

    public static function getAuthorizationUrl(string $state): string
    {
        $params = [
            'client_id' => config('IG_APP_ID'),
            'redirect_uri' => config('IG_REDIRECT_URI'),
            'response_type' => 'code',
            'scope' => 'instagram_business_basic,instagram_business_manage_comments,instagram_business_manage_messages,instagram_business_manage_insights',
            'state' => $state,
        ];
        return self::AUTH_BASE . '?' . http_build_query($params);
    }

    /**
     * Troca o "code" do callback por um token de curta duração.
     * @return array{access_token:string,user_id:string}
     */
    public static function exchangeCodeForToken(string $code): array
    {
        $fields = [
            'client_id' => config('IG_APP_ID'),
            'client_secret' => config('IG_APP_SECRET'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('IG_REDIRECT_URI'),
            'code' => $code,
        ];
        return self::request('POST', self::TOKEN_URL, $fields, false);
    }

    /**
     * Troca um token de curta duração por um de longa duração (~60 dias).
     * @return array{access_token:string,expires_in:int}
     */
    public static function exchangeForLongLivedToken(string $shortLivedToken): array
    {
        $url = self::GRAPH_BASE . '/access_token';
        $params = [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => config('IG_APP_SECRET'),
            'access_token' => $shortLivedToken,
        ];
        return self::request('GET', $url, $params, false);
    }

    /**
     * Renova um token de longa duração antes que ele expire.
     * @return array{access_token:string,expires_in:int}
     */
    public static function refreshLongLivedToken(string $token): array
    {
        $url = self::GRAPH_BASE . '/refresh_access_token';
        $params = [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $token,
        ];
        return self::request('GET', $url, $params, false);
    }

    /**
     * @return array{user_id:string,username:string}
     */
    public static function getProfile(string $accessToken): array
    {
        $url = self::GRAPH_BASE . '/' . self::GRAPH_VERSION . '/me';
        $params = [
            'fields' => 'user_id,username',
            'access_token' => $accessToken,
        ];
        return self::request('GET', $url, $params, false);
    }

    public static function replyToComment(string $commentId, string $accessToken, string $message): array
    {
        $url = self::GRAPH_BASE . '/' . self::GRAPH_VERSION . '/' . rawurlencode($commentId) . '/replies';
        $fields = [
            'message' => $message,
            'access_token' => $accessToken,
        ];
        return self::request('POST', $url, $fields, false);
    }

    /**
     * Manda uma DM (private reply) para quem deixou um comentário. Só funciona
     * dentro da janela de tempo permitida pela Meta após o comentário (poucos dias).
     * Suporta links, diferente da resposta pública no comentário.
     */
    public static function sendPrivateReply(string $commentId, string $accessToken, string $message): array
    {
        $url = self::GRAPH_BASE . '/' . self::GRAPH_VERSION . '/me/messages';
        $fields = [
            'recipient' => json_encode(['comment_id' => $commentId]),
            'message' => json_encode(['text' => $message]),
            'access_token' => $accessToken,
        ];
        return self::request('POST', $url, $fields, false);
    }

    /**
     * Mesma resposta privada, mas com um botão clicável em vez de link em texto.
     * Limites da Meta: corpo até 640 caracteres, texto do botão até 20 caracteres.
     */
    public static function sendPrivateReplyWithButton(
        string $commentId,
        string $accessToken,
        string $bodyText,
        string $buttonLabel,
        string $buttonUrl
    ): array {
        $url = self::GRAPH_BASE . '/' . self::GRAPH_VERSION . '/me/messages';
        $message = [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'button',
                    'text' => mb_substr($bodyText, 0, 640),
                    'buttons' => [
                        [
                            'type' => 'web_url',
                            'url' => $buttonUrl,
                            'title' => mb_substr($buttonLabel, 0, 20),
                        ],
                    ],
                ],
            ],
        ];
        $fields = [
            'recipient' => json_encode(['comment_id' => $commentId]),
            'message' => json_encode($message),
            'access_token' => $accessToken,
        ];
        return self::request('POST', $url, $fields, false);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function getRecentMedia(string $accessToken, int $limit = 8): array
    {
        $url = self::GRAPH_BASE . '/' . self::GRAPH_VERSION . '/me/media';
        $params = [
            'fields' => 'id,timestamp',
            'limit' => $limit,
            'access_token' => $accessToken,
        ];
        $result = self::request('GET', $url, $params, false);
        return $result['data'] ?? [];
    }

    /**
     * Mídias com curtidas/comentários e tipo de conteúdo, pra análise de performance.
     * Pagina pelo histórico inteiro da conta (não só os últimos N posts), até o limite
     * de $maxPages páginas, pra dar pra filtrar por qualquer período sem recarregar.
     * Não inclui alcance/impressões — isso exige a permissão instagram_business_manage_insights,
     * que não foi concedida (precisaria reconectar a conta pedindo esse escopo extra).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function getMediaWithEngagement(string $accessToken, int $perPage = 50, int $maxPages = 20): array
    {
        $url = self::GRAPH_BASE . '/' . self::GRAPH_VERSION . '/me/media';
        $params = [
            'fields' => 'id,timestamp,caption,permalink,media_type,media_product_type,thumbnail_url,media_url,like_count,comments_count',
            'limit' => $perPage,
            'access_token' => $accessToken,
        ];

        $all = [];
        $nextUrl = $url;
        $nextParams = $params;

        for ($page = 0; $page < $maxPages && $nextUrl !== null; $page++) {
            $result = self::request('GET', $nextUrl, $nextParams, false);
            foreach ($result['data'] ?? [] as $item) {
                $all[] = $item;
            }
            $nextUrl = $result['paging']['next'] ?? null;
            $nextParams = []; // a URL de "next" já vem com todos os parâmetros, inclusive o token
        }

        return $all;
    }

    /**
     * Versão com mais campos, usada pro seletor de "publicação específica" no formulário.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function getMediaForPicker(string $accessToken, int $limit = 15): array
    {
        $url = self::GRAPH_BASE . '/' . self::GRAPH_VERSION . '/me/media';
        $params = [
            'fields' => 'id,timestamp,caption,permalink,media_type,thumbnail_url,media_url',
            'limit' => $limit,
            'access_token' => $accessToken,
        ];
        $result = self::request('GET', $url, $params, false);
        return $result['data'] ?? [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function getComments(string $mediaId, string $accessToken, int $limit = 50): array
    {
        $url = self::GRAPH_BASE . '/' . self::GRAPH_VERSION . '/' . rawurlencode($mediaId) . '/comments';
        $params = [
            'fields' => 'id,text,username,timestamp',
            'limit' => $limit,
            'access_token' => $accessToken,
        ];
        $result = self::request('GET', $url, $params, false);
        return $result['data'] ?? [];
    }

    public static function verifySignature(string $rawBody, ?string $signatureHeader, string $appSecret): bool
    {
        if (!$signatureHeader || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        return hash_equals($expected, $signatureHeader);
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private static function request(string $method, string $url, array $params, bool $asJsonBody): array
    {
        if ($method === 'GET' && $params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
        }

        $attempts = 3;
        $errno = 0;
        $error = '';
        $response = false;
        $status = 0;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_DNS_CACHE_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            }

            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // erro de conexão (ex: DNS falhando por instabilidade da hospedagem) —
            // tenta de novo em vez de desistir na primeira falha transitória.
            $isConnectionError = in_array($errno, [
                CURLE_COULDNT_RESOLVE_HOST, // 6
                CURLE_COULDNT_CONNECT,      // 7
                CURLE_OPERATION_TIMEDOUT,   // 28
            ], true);

            if ($errno === 0 || !$isConnectionError || $attempt === $attempts) {
                break;
            }

            usleep(500000 * $attempt); // 0.5s, depois 1s
        }

        if ($errno !== 0) {
            throw new RuntimeException('Falha de conexão com a API do Instagram: ' . $error);
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida da API do Instagram (HTTP ' . $status . ').');
        }

        if ($status >= 400) {
            $message = $decoded['error_message']
                ?? $decoded['error']['message']
                ?? ('Erro HTTP ' . $status);
            throw new RuntimeException('Erro da API do Instagram: ' . $message);
        }

        return $decoded;
    }
}
