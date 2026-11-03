<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

// Testes usam banco proprio e o recriam do zero a cada execucao.
putenv('MYSQL_URL=');
putenv('DATABASE_URL=');
putenv('DB_NAME=' . (getenv('TEST_DB_NAME') ?: 'erp_test'));
if (!str_contains((string) getenv('DB_NAME'), 'test')) {
    fwrite(STDERR, "Recusado: o banco de testes precisa ter 'test' no nome (os testes apagam todas as tabelas).\n");
    exit(1);
}
$pdo = App\Database::pdo();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $pdo->exec("DROP TABLE `$t`");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
(new App\Migrator($pdo, __DIR__ . '/../migrations'))->run();
