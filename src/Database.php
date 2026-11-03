<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    /** Credenciais: MYSQL_URL/DATABASE_URL (Railway) tem prioridade; depois DB_*; depois MYSQLHOST etc.; por fim padroes locais. */
    public static function config(): array
    {
        $url = Env::get('MYSQL_URL') ?? Env::get('DATABASE_URL');
        if ($url && ($u = parse_url($url)) && !empty($u['host'])) {
            return [
                'host' => $u['host'], 'port' => (string) ($u['port'] ?? 3306),
                'name' => ltrim($u['path'] ?? '', '/') ?: 'railway',
                'user' => urldecode($u['user'] ?? 'root'), 'pass' => urldecode($u['pass'] ?? ''),
            ];
        }
        return [
            'host' => Env::get('DB_HOST') ?? Env::get('MYSQLHOST') ?? '127.0.0.1',
            'port' => Env::get('DB_PORT') ?? Env::get('MYSQLPORT') ?? '3306',
            'name' => Env::get('DB_NAME') ?? Env::get('MYSQLDATABASE') ?? 'erp',
            'user' => Env::get('DB_USER') ?? Env::get('MYSQLUSER') ?? 'root',
            'pass' => Env::get('DB_PASS') ?? Env::get('MYSQLPASSWORD') ?? '',
        ];
    }

    /** True quando alguma configuracao de banco foi fornecida (nao apenas os padroes locais). */
    public static function isConfigured(): bool
    {
        foreach (['DB_HOST', 'MYSQL_URL', 'DATABASE_URL', 'MYSQLHOST'] as $k) {
            if (Env::get($k)) {
                return true;
            }
        }
        return false;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = self::config();
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']);
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        }
        return self::$pdo;
    }

    public static function use(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function run(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }
}
