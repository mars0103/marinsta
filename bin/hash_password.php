<?php
declare(strict_types=1);

// Uso: php bin/hash_password.php "sua-senha-aqui"
// Cole o resultado em ADMIN_PASS_HASH no arquivo .env

if ($argc < 2) {
    fwrite(STDERR, "Uso: php bin/hash_password.php \"sua-senha\"\n");
    exit(1);
}

echo password_hash($argv[1], PASSWORD_DEFAULT) . "\n";
