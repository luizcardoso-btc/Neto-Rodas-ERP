<?php
use App\Auth;
use App\Database;

/** @var array|null $sellerAccess definido em index.php quando o acesso e pelo link do vendedor */
$source = 'mobile';
$fixedSeller = $sellerAccess;
$self = url('pdv_mobile', $sellerAccess ? ['t' => $sellerAccess['access_token']] : []);
if (isset($_GET['lookup'])) {
    App\Lookup::respond();
}
require __DIR__ . '/_sale_handler.php';

$sellers = Database::all('SELECT id, name FROM sellers WHERE active = 1 ORDER BY name');
if (!Auth::check()) {
    $layout = 'bare';
}
?>
<?php if (!Auth::check()): ?><div class="m-wrap"><?php endif; ?>
<section class="hero slim"><div><h1>Venda em campo</h1><p>Registre vendas rapidamente pelo celular.</p></div></section>
<?php require __DIR__ . '/_sale_form.php'; ?>
<?php if (!Auth::check()): ?></div><?php endif; ?>
