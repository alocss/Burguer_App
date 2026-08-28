<?php
declare(strict_types=1);

namespace House;

use PDO;

final class Auth
{
    public static function attempt(string $email, string $password, bool $admin = false): bool
    {
        $pdo = Database::connection();
        $normalizedEmail = mb_strtolower(trim($email));
        $bucket = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'cli') . '|' . $normalizedEmail . '|' . ($admin ? 'admin' : 'customer'));
        $check = $pdo->prepare('SELECT attempts, window_started_at FROM rate_limits WHERE bucket_key=:key');
        $check->execute(['key'=>$bucket]);
        $limit = $check->fetch();
        if ($limit && strtotime($limit['window_started_at']) > time() - 900 && (int)$limit['attempts'] >= 10) return false;
        $pdo->prepare("INSERT INTO rate_limits(bucket_key,attempts,window_started_at) VALUES(:key,1,NOW()) ON DUPLICATE KEY UPDATE attempts=IF(window_started_at<NOW()-INTERVAL 15 MINUTE,1,attempts+1),window_started_at=IF(window_started_at<NOW()-INTERVAL 15 MINUTE,NOW(),window_started_at)")->execute(['key'=>$bucket]);
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role, active FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $normalizedEmail]);
        $user = $stmt->fetch();
        if (!$user || !$user['active'] || !password_verify($password, $user['password_hash'])) return false;
        if ($admin && !in_array($user['role'], ['admin', 'manager'], true)) return false;
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $pdo->prepare('DELETE FROM rate_limits WHERE bucket_key=:key')->execute(['key'=>$bucket]);
        return true;
    }

    public static function user(): ?array { return $_SESSION['user'] ?? null; }
    public static function check(): bool { return self::user() !== null; }
    public static function isAdmin(): bool { return in_array(self::user()['role'] ?? '', ['admin', 'manager'], true); }

    public static function requireUser(): void
    {
        if (!self::check()) self::redirect('/login?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) self::redirect('/admin/login');
        header('Cache-Control: no-store, private');
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . $path, true, 302);
        exit;
    }
}

