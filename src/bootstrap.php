<?php
declare(strict_types=1);

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Auth.php';

use House\Config;
use House\Security;

Config::load(dirname(__DIR__) . '/.env');
date_default_timezone_set(Config::get('APP_TIMEZONE', 'America/Sao_Paulo'));
set_exception_handler(static function (Throwable $e): void {
    $id = bin2hex(random_bytes(6));
    error_log("[{$id}] " . $e);
    http_response_code(500);
    if (House\Config::bool('APP_DEBUG')) {
        echo '<pre>' . House\Security::e((string) $e) . '</pre>';
    } else {
        echo 'Não foi possível concluir a operação. Código: ' . House\Security::e($id);
    }
});

if (Config::get('APP_ENV', 'production') === 'production' && strlen((string) Config::get('APP_KEY')) < 32) {
    throw new RuntimeException('APP_KEY deve possuir ao menos 32 caracteres em produção.');
}
Security::headers();
Security::startSession();

