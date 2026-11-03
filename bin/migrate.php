<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

try {
    $applied = (new App\Migrator(App\Database::pdo(), __DIR__ . '/../migrations'))->run();
} catch (PDOException $e) {
    fwrite(STDERR, '[ERP] Falha no banco (codigo ' . $e->getCode() . '): ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
echo $applied ? 'Aplicadas: ' . implode(', ', $applied) . PHP_EOL : "Nada a aplicar.\n";
