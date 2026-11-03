<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Env;

const PAGES = [
    'dashboard' => 'Dashboard', 'estoque' => 'Estoque', 'pdv' => 'PDV', 'pdv_mobile' => 'PDV Mobile', 'orcamento' => 'Orçamento',
    'vendas' => 'Vendas', 'crm' => 'CRM', 'financeiro' => 'Financeiro', 'vendedores' => 'Vendedores', 'senha' => 'Minha senha', 'zerar' => 'Zerar vendas',
];

Auth::start();

try {

    $page = (string) ($_GET['page'] ?? 'dashboard');
    if ($page === 'logout') {
        Auth::logout();
        redirect('index.php?page=login');
    }
    if ($page !== 'login' && !array_key_exists($page, PAGES)) {
        http_response_code(404);
        $page = 'dashboard';
    }

    // PDF do orcamento: link publico protegido por token aleatorio (cliente abre sem login).
    if ($page === 'orcamento' && !empty($_GET['pdf'])) {
        App\Quote::servePdf((string) $_GET['pdf'], !empty($_GET['download']));
    }

    // PDV Mobile: vendedor entra pelo link pessoal (?t=token) sem login; demais paginas exigem login.
    $sellerAccess = null;
    if ($page === 'pdv_mobile' && !empty($_GET['t'])) {
        $sellerAccess = Database::one('SELECT * FROM sellers WHERE access_token = ? AND active = 1', [(string) $_GET['t']]);
    }
    if ($page !== 'login' && !Auth::check() && !$sellerAccess) {
        redirect('index.php?page=login');
    }
    if (in_array($page, ['vendedores', 'zerar'], true) && !Auth::isAdmin()) {
        http_response_code(403);
        exit('Acesso restrito a administradores.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::verify()) {
        http_response_code(419);
        exit('Sessao expirada. Volte e recarregue a pagina.');
    }

    $layout = 'app';
    $title = PAGES[$page] ?? 'Entrar';
    ob_start();
    require __DIR__ . '/../pages/' . $page . '.php';
    $content = ob_get_clean();

    require __DIR__ . '/../pages/_layout_' . $layout . '.php';
} catch (PDOException $e) {
    App\Diagnostics::dbProblem($e);
}
