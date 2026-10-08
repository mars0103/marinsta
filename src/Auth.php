<?php
declare(strict_types=1);

final class Auth
{
    private const REMEMBER_COOKIE = 'marinsta_remember';
    private const REMEMBER_DAYS = 60;

    public static function attempt(string $user, string $pass): bool
    {
        $expectedUser = (string) config('ADMIN_USER', 'admin');
        $expectedHash = (string) config('ADMIN_PASS_HASH', '');

        if ($expectedHash === '' || !hash_equals($expectedUser, $user)) {
            return false;
        }
        if (!password_verify($pass, $expectedHash)) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        self::issueRememberCookie();
        return true;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['admin_logged_in']);
    }

    public static function requireLogin(): void
    {
        if (self::check()) {
            return;
        }
        if (self::attemptRememberLogin()) {
            return;
        }
        redirect('/login');
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        self::clearRememberCookie();
    }

    /**
     * Cookie "continuar logado": validade de 60 dias, assinada com a hash da
     * senha atual — então trocar a senha invalida sozinho qualquer cookie antiga.
     */
    private static function issueRememberCookie(): void
    {
        $expires = time() + self::REMEMBER_DAYS * 86400;
        $sig = hash_hmac('sha256', (string) $expires, self::rememberSecret());
        setcookie(self::REMEMBER_COOKIE, $expires . '.' . $sig, [
            'expires' => $expires,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function clearRememberCookie(): void
    {
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function attemptRememberLogin(): bool
    {
        $cookie = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        if ($cookie === '' || !str_contains($cookie, '.')) {
            return false;
        }

        [$expires, $sig] = explode('.', $cookie, 2);
        if (!ctype_digit($expires) || (int) $expires < time()) {
            return false;
        }

        $expected = hash_hmac('sha256', $expires, self::rememberSecret());
        if (!hash_equals($expected, $sig)) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        return true;
    }

    private static function rememberSecret(): string
    {
        $hash = (string) config('ADMIN_PASS_HASH', '');
        return $hash !== '' ? $hash : 'marinsta-sem-senha-configurada';
    }

    public static function verifyCurrentPassword(string $pass): bool
    {
        $expectedHash = (string) config('ADMIN_PASS_HASH', '');
        return $expectedHash !== '' && password_verify($pass, $expectedHash);
    }

    private const RESET_LOCK_PATH = __DIR__ . '/../.forgot_password_lock';
    private const RESET_COOLDOWN_SECONDS = 600;

    /**
     * Evita spam/abuso no "esqueci minha senha": só deixa pedir de novo depois
     * de um tempo mínimo desde o último pedido.
     */
    public static function canRequestPasswordReset(): bool
    {
        if (!file_exists(self::RESET_LOCK_PATH)) {
            return true;
        }
        $last = (int) trim((string) file_get_contents(self::RESET_LOCK_PATH));
        return (time() - $last) >= self::RESET_COOLDOWN_SECONDS;
    }

    public static function recordPasswordResetRequest(): void
    {
        file_put_contents(self::RESET_LOCK_PATH, (string) time(), LOCK_EX);
    }

    /**
     * Regrava só a linha ADMIN_PASS_HASH do .env, preservando o resto do arquivo.
     */
    public static function updatePassword(string $newPassword): void
    {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $envPath = __DIR__ . '/../.env';
        $lines = file($envPath, FILE_IGNORE_NEW_LINES);
        $found = false;

        foreach ($lines as $i => $line) {
            if (str_starts_with(trim($line), 'ADMIN_PASS_HASH=')) {
                $lines[$i] = 'ADMIN_PASS_HASH=' . $newHash;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $lines[] = 'ADMIN_PASS_HASH=' . $newHash;
        }

        file_put_contents($envPath, implode("\n", $lines) . "\n", LOCK_EX);
        putenv('ADMIN_PASS_HASH=' . $newHash);
        $_ENV['ADMIN_PASS_HASH'] = $newHash;
    }
}
