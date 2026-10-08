<?php
declare(strict_types=1);

final class Crm
{
    public const STATUSES = [
        'pesquisado' => 'Pesquisado',
        'selecionado' => 'Selecionado',
        'contato_feito' => 'Contato feito',
        'respondeu' => 'Respondeu',
        'qualificado' => 'Qualificado',
        'reuniao_marcada' => 'Reunião marcada',
        'proposta_enviada' => 'Proposta enviada',
        'negociacao' => 'Negociação',
        'ganho' => 'Ganho',
        'perdido' => 'Perdido',
        'pausado' => 'Pausado',
    ];

    public const SEGMENTS = [
        'A' => 'A — Indústria / B2B',
        'B' => 'B — Marca / produto premium',
    ];

    /**
     * Critérios de pontuação (0 a 2 cada), como definidos no plano comercial.
     * @return array<string,string>
     */
    public const SCORE_CRITERIA = [
        'score_valor_alto' => 'Produto/serviço de alto valor e operação estabelecida',
        'score_problema' => 'Problema concreto na apresentação ou jornada do site',
        'score_momento' => 'Momento: lançamento, expansão ou campanha identificável',
        'score_decisor' => 'Responsável pela decisão acessível',
        'score_investimento' => 'Sinais de investimento em marca e comercial',
    ];

    /**
     * Nome curto pra exibir em tabela/listagem — corta no primeiro parêntese
     * explicativo (ex: "DJ Japa NK (Adenilton Sapucaia...)" -> "DJ Japa NK").
     */
    public static function shortCompanyName(string $name): string
    {
        $pos = mb_strpos($name, ' (');
        if ($pos !== false) {
            return mb_substr($name, 0, $pos);
        }
        return mb_strimwidth($name, 0, 40, '…');
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public static function segmentLabel(string $segment): string
    {
        return self::SEGMENTS[$segment] ?? $segment;
    }

    /** @return array{0:string,1:string} cor forte e cor de fundo claro */
    public static function statusPalette(string $status): array
    {
        return match ($status) {
            'selecionado' => ['#6366f1', '#e0e7ff'],
            'contato_feito' => ['#0891b2', '#cffafe'],
            'respondeu' => ['#0d9488', '#ccfbf1'],
            'qualificado' => ['#16a34a', '#dcfce7'],
            'reuniao_marcada' => ['#7c3aed', '#ede9fe'],
            'proposta_enviada' => ['#d97706', '#fef3c7'],
            'negociacao' => ['#ea580c', '#ffedd5'],
            'ganho' => ['#059669', '#d1fae5'],
            'perdido' => ['#dc2626', '#fee2e2'],
            'pausado' => ['#64748b', '#f1f5f9'],
            default => ['#4f46e5', '#eef0ff'],
        };
    }

    /** @return array{0:string,1:string} */
    public static function segmentPalette(string $segment): array
    {
        return $segment === 'B' ? ['#be185d', '#fce7f3'] : ['#0f766e', '#ccfbf1'];
    }

    /** @return array{0:string,1:string} */
    public static function scorePalette(int $score): array
    {
        return match (true) {
            $score >= 8 => ['#166534', '#dcfce7'],
            $score >= 6 => ['#92400e', '#fef3c7'],
            default => ['#991b1b', '#fee2e2'],
        };
    }

    /**
     * Script de primeira mensagem, montado a partir dos dados já cadastrados.
     * Sem IA: texto fixo com o nome, segmento e o problema observado.
     */
    public static function buildOutreachScript(string $companyName, string $segment, ?string $problem): string
    {
        $name = self::shortCompanyName($companyName);
        $problemText = trim((string) $problem);
        if ($problemText === '') {
            $problemText = 'a apresentação digital de vocês não acompanha o tamanho do trabalho que fazem';
        } else {
            $problemText = mb_strtolower(mb_substr($problemText, 0, 1)) . mb_substr($problemText, 1);
            $problemText = rtrim($problemText, '.');
        }

        if ($segment === 'B') {
            return "Oi, {$name}! Tudo bem? Aqui é o Matheus, da Mars Design. Acompanho o trabalho de vocês e percebi que {$problemText}.\n\n"
                . 'A gente cria sites e identidades visuais pra marcas que já têm público e querem ser levadas a sério no digital. '
                . 'Posso te mandar um diagnóstico rápido, sem compromisso?';
        }

        return "Olá, tudo bem? Aqui é o Matheus, da Mars Design (estúdio de identidade visual e sites). Pesquisei a {$name} e percebi que {$problemText}.\n\n"
            . 'Pra uma empresa com a estrutura de vocês, a apresentação digital pesa quando um cliente novo avalia a marca. '
            . 'Posso te enviar um diagnóstico rápido do site, sem compromisso?';
    }

    public static function segmentShortLabel(string $segment): string
    {
        return match ($segment) {
            'A' => 'Indústria / B2B',
            'B' => 'Marca premium',
            default => $segment,
        };
    }

    /**
     * Classe CSS do pill de status, agrupando por "temperatura" do funil.
     */
    public static function statusClass(string $status): string
    {
        return match ($status) {
            'ganho' => 'status-connected',
            'perdido' => 'status-disconnected',
            default => 'tag',
        };
    }
}
