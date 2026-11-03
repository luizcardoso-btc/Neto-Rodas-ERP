<?php
declare(strict_types=1);

namespace App;

use PDO;

/** Executa migracoes SQL versionadas (migrations/NNN_nome.sql), uma unica vez cada. */
final class Migrator
{
    public function __construct(private PDO $pdo, private string $dir)
    {
    }

    /** @return string[] migracoes aplicadas nesta execucao */
    public function run(): array
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(190) PRIMARY KEY,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB');

        $done = $this->pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob(rtrim($this->dir, '/') . '/*.sql') ?: [];
        sort($files);

        $applied = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (in_array($version, $done, true)) {
                continue;
            }
            foreach ($this->statements((string) file_get_contents($file)) as $sql) {
                $this->pdo->exec($sql);
            }
            $st = $this->pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
            $st->execute([$version]);
            $applied[] = $version;
        }
        return $applied;
    }

    /** @return string[] */
    private function statements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        return array_values(array_filter(array_map('trim', explode(';', $sql)), fn ($s) => $s !== ''));
    }
}
