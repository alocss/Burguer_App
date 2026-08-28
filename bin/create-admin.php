<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') exit("Somente CLI.\n");
$name = $argv[1] ?? '';
$email = mb_strtolower(trim($argv[2] ?? ''));
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Uso: php bin/create-admin.php \"Nome\" email@dominio.com\n");
}

$hidden = PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec');
fwrite(STDOUT, $hidden ? 'Senha: ' : "Senha (entrada visível neste terminal): ");
if ($hidden) @shell_exec('/bin/stty -echo 2>/dev/null');
try {
    $password = trim((string) fgets(STDIN));
} finally {
    if ($hidden) {
        @shell_exec('/bin/stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
    }
}
if (strlen($password) < 12) exit("A senha deve ter pelo menos 12 caracteres.\n");

$stmt = House\Database::connection()->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (:name,:email,:hash,\'admin\') ON DUPLICATE KEY UPDATE name=VALUES(name),password_hash=VALUES(password_hash),role=\'admin\',active=1');
$stmt->execute(['name'=>$name,'email'=>$email,'hash'=>password_hash($password, PASSWORD_ARGON2ID)]);
fwrite(STDOUT, "Administrador criado ou atualizado.\n");

