<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Database;
use App\Env;

/*
 * Mantem o usuario administrador sincronizado com as variaveis de ambiente:
 *  - sem nenhum usuario: cria ADMIN_USER com ADMIN_PASSWORD;
 *  - com usuarios e ADMIN_PASSWORD definida: garante que ADMIN_USER existe e usa essa senha;
 *  - com usuarios e ADMIN_PASSWORD vazia: nao mexe em nada (senhas trocadas no sistema continuam valendo).
 */
$user = trim((string) Env::get('ADMIN_USER', 'admin'));
$pass = trim((string) Env::get('ADMIN_PASSWORD', ''));
$count = (int) Database::value('SELECT COUNT(*) FROM users');
$force = Env::get('RESET_ADMIN') !== null && in_array(strtolower((string) Env::get('RESET_ADMIN')), ['1', 'true', 'sim', 'yes'], true)
    || in_array('--reset', $argv ?? [], true);

if ($pass === '') {
    if ($count === 0) {
        fwrite(STDERR, "[ERP] Nenhum usuario cadastrado. Defina ADMIN_USER e ADMIN_PASSWORD (minimo 6 caracteres).\n");
        exit(1);
    }
    echo "[ERP] Usuarios ja cadastrados; ADMIN_PASSWORD vazia: nada a fazer.\n";
    exit(0);
}
if ($user === '' || strlen($pass) < 6) {
    fwrite(STDERR, "[ERP] ADMIN_USER vazio ou ADMIN_PASSWORD com menos de 6 caracteres.\n");
    exit(1);
}

$existing = Database::one('SELECT id, password_hash, active, role FROM users WHERE username = ?', [$user]);
if ($existing && !$force && $existing['active'] && $existing['role'] === 'admin' && password_verify($pass, $existing['password_hash'])) {
    echo "[ERP] Usuario '$user' ja esta com a senha de ADMIN_PASSWORD.\n";
    exit(0);
}

Database::run(
    "INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = 'admin', active = 1",
    [$user, password_hash($pass, PASSWORD_DEFAULT)]
);
echo $existing ? "[ERP] Senha do usuario '$user' redefinida a partir de ADMIN_PASSWORD.\n" : "[ERP] Usuario '$user' criado.\n";
if (strlen($pass) < 10) {
    echo "[ERP] AVISO: senha curta. Quando entrar, troque em 'Minha senha' e remova ADMIN_PASSWORD das variaveis.\n";
}
echo "[ERP] Depois do primeiro acesso, remova ADMIN_PASSWORD das variaveis para a senha trocada no sistema nao ser sobrescrita.\n";
