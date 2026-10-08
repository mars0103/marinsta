<?php
declare(strict_types=1);

/**
 * Envio simples de email via mail() do próprio servidor (cPanel/LiteSpeed já
 * tem o MTA local configurado pro domínio). Sem dependências externas.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $from = 'no-reply@marsdesigner.com.br';
        $headers = implode("\r\n", [
            'From: Marinsta <' . $from . '>',
            'Content-Type: text/plain; charset=UTF-8',
        ]);

        return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    }
}
