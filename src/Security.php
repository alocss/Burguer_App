<?php
declare(strict_types=1);

namespace House;

final class Security
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        session_name(Config::get('SESSION_NAME', 'house_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Config::get('APP_ENV', 'production') === 'production',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
        $now = time();
        $idle = (int) Config::get('SESSION_IDLE_MINUTES', '30') * 60;
        $absolute = (int) Config::get('SESSION_LIFETIME_HOURS', '12') * 3600;
        if ((isset($_SESSION['last_activity']) && $now - (int) $_SESSION['last_activity'] > $idle) ||
            (isset($_SESSION['created_at']) && $now - (int) $_SESSION['created_at'] > $absolute)) {
            self::logout();
            session_start();
        }
        $_SESSION['created_at'] ??= $now;
        $_SESSION['last_activity'] = $now;
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function csrf(): string { return (string) ($_SESSION['csrf'] ?? ''); }

    public static function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($token) || !hash_equals(self::csrf(), $token)) {
            http_response_code(419);
            exit('Sessão expirada. Atualize a página e tente novamente.');
        }
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function headers(): void
    {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('X-Frame-Options: DENY');
        if (Config::get('APP_ENV', 'production') === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }
}

