<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Security.php';

use House\Security;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FALHA: {$message}\n");
        exit(1);
    }
}

$hash = password_hash('Senha-segura-123!', PASSWORD_ARGON2ID);
check(is_string($hash), 'não foi possível gerar hash Argon2id');
check(password_verify('Senha-segura-123!', $hash), 'a senha correta não foi validada');
check(!password_verify('senha-incorreta', $hash), 'uma senha incorreta foi aceita');

$escaped = Security::e('<script>alert(1)</script>');
check($escaped === '&lt;script&gt;alert(1)&lt;/script&gt;', 'escape HTML não bloqueou markup executável');

echo "Security smoke tests: OK\n";

