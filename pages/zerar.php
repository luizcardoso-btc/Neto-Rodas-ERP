<?php
use App\Auth;
use App\Database;

if (!Auth::isAdmin()) {
    http_response_code(403);
    exit('Acesso restrito a administradores.');
}

// Data de corte: tudo ANTES dela e considerado teste. Padrao: dia 1 do mes atual (zera o mes passado e anteriores).
$cut = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_REQUEST['cut'] ?? '')) ? (string) $_REQUEST['cut'] : date('Y-m-01');
$cutAt = $cut . ' 00:00:00';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (strtoupper(trim((string) ($_POST['confirm'] ?? ''))) !== 'ZERAR') {
        flash('err', 'Digite ZERAR para confirmar.');
        redirect(url('zerar', ['cut' => $cut]));
    }
    $pdo = Database::pdo();
    $pdo->beginTransaction();
    try {
        $ids = array_map('intval', array_column(Database::all('SELECT id FROM sales WHERE created_at < ?', [$cutAt]), 'id'));
        $in = $ids ? implode(',', $ids) : '0';
        // 1) desfaz no estoque o efeito das vendas de teste (baixas e entradas de troca)
        Database::run("UPDATE products p JOIN (SELECT product_id, SUM(quantity) q FROM stock_movements WHERE sale_id IN ($in) GROUP BY product_id) m ON m.product_id = p.id SET p.stock = GREATEST(0, p.stock - m.q)");
        Database::run("DELETE FROM stock_movements WHERE sale_id IN ($in)");
        // 2) desfaz as entradas/ajustes manuais de estoque anteriores ao corte
        Database::run('UPDATE products p JOIN (SELECT product_id, SUM(quantity) q FROM stock_movements WHERE created_at < ? GROUP BY product_id) m ON m.product_id = p.id SET p.stock = GREATEST(0, p.stock - m.q)', [$cutAt]);
        Database::run('DELETE FROM stock_movements WHERE created_at < ?', [$cutAt]);
        // 3) apaga as vendas de teste e tudo que depende delas
        Database::run("DELETE FROM creditor_payments WHERE sale_id IN ($in)");
        Database::run("DELETE FROM accounts_receivable WHERE sale_id IN ($in)");
        Database::run("DELETE FROM sale_audit WHERE sale_id IN ($in)");
        Database::run("DELETE FROM sale_payments WHERE sale_id IN ($in)");
        Database::run("DELETE FROM sale_items WHERE sale_id IN ($in)");
        Database::run("DELETE FROM sales WHERE id IN ($in)");
        // 4) usado de troca que ficou sem saldo, sem historico e sem venda some
        Database::run("DELETE FROM products WHERE condition_type = 'usado' AND stock = 0
            AND id NOT IN (SELECT product_id FROM stock_movements) AND id NOT IN (SELECT product_id FROM sale_items)");
        if (!empty($_POST['customers'])) {
            Database::run('DELETE FROM customers WHERE created_at < ? AND id NOT IN (SELECT customer_id FROM sales WHERE customer_id IS NOT NULL)', [$cutAt]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    flash('ok', 'Dados de teste anteriores a ' . date('d/m/Y', strtotime($cut)) . ' zerados. Vendas a partir dessa data foram mantidas.');
    redirect(url('dashboard'));
}

$nOld = (int) Database::value('SELECT COUNT(*) FROM sales WHERE created_at < ?', [$cutAt]);
$nKeep = (int) Database::value('SELECT COUNT(*) FROM sales WHERE created_at >= ?', [$cutAt]);
$kept = (float) Database::value('SELECT COALESCE(SUM(total),0) FROM sales WHERE created_at >= ?', [$cutAt]);
$nMov = (int) Database::value('SELECT COUNT(*) FROM stock_movements WHERE created_at < ? AND sale_id IS NULL', [$cutAt]);
$nCus = (int) Database::value('SELECT COUNT(*) FROM customers WHERE created_at < ? AND id NOT IN (SELECT customer_id FROM sales WHERE customer_id IS NOT NULL AND created_at >= ?)', [$cutAt, $cutAt]);
?>
<div class="page-head"><div><h1>Zerar dados de teste</h1><p>Apaga só o que veio antes da data de corte. O que for dessa data em diante não é tocado.</p></div></div>
<form class="card stack" method="get" style="max-width:560px;margin-bottom:16px">
  <input type="hidden" name="page" value="zerar">
  <div><label class="f" for="cut">Zerar tudo anterior a</label><input id="cut" type="date" name="cut" value="<?= e($cut) ?>" onchange="this.form.submit()"></div>
</form>
<form class="card stack" method="post" style="max-width:560px">
  <?= App\Csrf::field() ?><input type="hidden" name="cut" value="<?= e($cut) ?>">
  <h2>Antes de <?= e(date('d/m/Y', strtotime($cut))) ?></h2>
  <p>Serão apagadas <b><?= $nOld ?> venda(s)</b> de teste, com itens, pagamentos, contas a receber, repasses ao credor e histórico de edição.</p>
  <p>No estoque, saem as baixas das vendas de teste, as entradas e os ajustes feitos antes dessa data (<b><?= $nMov ?></b> lançamento(s) manual(is)). Os produtos e preços continuam cadastrados, e usados de troca que ficarem sem saldo e sem histórico são removidos.</p>
  <p style="background:#dcfce7;border-radius:10px;padding:10px 12px"><b>Fica intacto:</b> <?= $nKeep ?> venda(s) a partir de <?= e(date('d/m/Y', strtotime($cut))) ?> (<?= money($kept) ?>), com seus lançamentos de estoque.</p>
  <p class="muted">Despesas, impostos e vendedores não são apagados.</p>
  <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="customers" value="1" style="width:auto"> Apagar também os <?= $nCus ?> cliente(s) de teste (sem compra a partir do corte)</label>
  <div><label class="f" for="confirm">Digite ZERAR para confirmar</label><input id="confirm" name="confirm" autocomplete="off" required></div>
  <button class="btn green" type="submit" onclick="return confirm('Isto não pode ser desfeito. Continuar?')">Zerar dados anteriores</button>
</form>
