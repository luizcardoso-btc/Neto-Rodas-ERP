<?php
declare(strict_types=1);

namespace App;

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => is_https(),
            ]);
            session_start();
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        $u = Database::one('SELECT * FROM users WHERE username = ? AND active = 1', [trim($username)]);
        if (!$u || !password_verify($password, $u['password_hash'])) {
            // Custo constante aproximado contra enumeracao de usuarios.
            password_verify($password, '$2y$10$usesomesillystringforsaltDUMMYDUMMYDUMMYDUMMYDUMMYDUMMY');
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int) $u['id'], 'username' => $u['username'], 'role' => $u['role']];
        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);
        return true;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
