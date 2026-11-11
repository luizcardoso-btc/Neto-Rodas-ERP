<?php
use App\Database;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_paid') {
    $id = (int) $_POST['id'];
    Database::run("UPDATE sales SET payment_status = 'paid' WHERE id = ?", [$id]);
    Database::run('UPDATE accounts_receivable SET paid_at = NOW() WHERE sale_id = ? AND paid_at IS NULL', [$id]);
    flash('ok', "Venda #$id marcada como paga.");
    redirect(url('vendas', ['id' => $id]));
}

if (!empty($_GET['edit'])) {
    require __DIR__ . '/venda_editar.php';
    return;
}

if (!empty($_GET['id'])) {
    $sale = Database::one(
        "SELECT s.*, CONCAT_WS(' ', c.first_name, c.last_name) customer, c.phone, sel.name seller
         FROM sales s LEFT JOIN customers c ON c.id = s.customer_id LEFT JOIN sellers sel ON sel.id = s.seller_id WHERE s.id = ?",
        [(int) $_GET['id']]
    );
    if ($sale) {
        $items = Database::all('SELECT * FROM sale_items WHERE sale_id = ?', [$sale['id']]);
        $pays = Database::all('SELECT * FROM sale_payments WHERE sale_id = ?', [$sale['id']]);
        ?>
<section class="hero slim"><div><h1>Venda #<?= (int) $sale['id'] ?></h1><p><?= e(fmt_date($sale['created_at'], true)) ?> · <?= $sale['sale_type'] === 'atacado' ? 'ATACADO · ' : '' ?><?= e($sale['customer'] ?: 'Sem cliente') ?> · Vendedor: <?= e($sale['seller'] ?: '-') ?></p></div>
  <div class="big"><small>Total</small><strong><?= money($sale['total']) ?></strong></div></section>
<div class="cols" style="grid-template-columns:1fr 1fr">
  <div class="card"><h2>Itens</h2><table><thead><tr><th>Produto</th><th class="num">Qtd.</th><th class="num">Unitario</th><th class="num">Subtotal</th></tr></thead><tbody>
    <?php foreach ($items as $i): ?><tr><td><?= e($i['description']) ?></td><td class="num"><?= (int) $i['quantity'] ?></td><td class="num"><?= money($i['unit_price']) ?></td><td class="num"><?= money($i['quantity'] * $i['unit_price']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  <div class="card"><h2>Pagamentos</h2><table><tbody>
    <?php foreach ($pays as $p): ?><tr><td><?= $p['method'] === 'troca' ? '<span class="dot" title="Pneu usado: entrou no estoque de usados"></span>' : '' ?><span class="tag <?= $p['method'] === 'troca' ? 'red' : 'blue' ?>"><?= e($p['method']) ?></span> <span class="small muted"><?= e($p['note'] ?? '') ?></span><?= !empty($p['to_creditor']) ? ' <span class="tag warn">enviado ao credor</span>' : '' ?></td><td class="num"><?= money($p['amount']) ?></td></tr><?php endforeach; ?></tbody></table>
    <?php if ($sale['payment_status'] === 'pending'): ?>
      <form method="post" style="margin-top:14px"><?= App\Csrf::field() ?><input type="hidden" name="action" value="mark_paid"><input type="hidden" name="id" value="<?= (int) $sale['id'] ?>"><button class="btn green" type="submit">Marcar como paga</button></form>
    <?php endif; ?>
    <p style="margin-top:14px"><?php if (App\Auth::isAdmin()): ?><a class="btn" href="<?= e(url('vendas', ['edit' => $sale['id']])) ?>">Editar venda</a> <?php endif; ?><a href="<?= e(url('vendas')) ?>">Voltar para vendas</a></p></div>
</div>
        <?php
        return;
    }
    flash('err', 'Venda nao encontrada.');
}

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
$sellerId = (int) ($_GET['seller'] ?? 0);
$status = $_GET['status'] ?? '';
$stype = in_array($_GET['tipo'] ?? '', ['varejo', 'atacado'], true) ? $_GET['tipo'] : '';
$q = trim((string) ($_GET['q'] ?? ''));

$where = ['s.created_at >= ?', 's.created_at < DATE_ADD(?, INTERVAL 1 DAY)'];
$args = [$from, $to];
if ($sellerId) { $where[] = 's.seller_id = ?'; $args[] = $sellerId; }
if (in_array($status, ['paid', 'pending'], true)) { $where[] = 's.payment_status = ?'; $args[] = $status; }
if ($stype !== '') { $where[] = 's.sale_type = ?'; $args[] = $stype; }
if ($q !== '') { $where[] = "CONCAT_WS(' ', c.first_name, c.last_name, c.phone) LIKE ?"; $args[] = '%' . addcslashes($q, '%_\\') . '%'; }
$w = implode(' AND ', $where);

$sum = Database::one("SELECT COUNT(*) n, COALESCE(SUM(s.total),0) total FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE $w", $args);
$pg = max(1, (int) ($_GET['p'] ?? 1));
$per = 40;
$rows = Database::all(
    "SELECT s.*, CONCAT_WS(' ', c.first_name, c.last_name) customer, sel.name seller,
            (SELECT GROUP_CONCAT(method SEPARATOR ', ') FROM sale_payments WHERE sale_id = s.id) methods
     FROM sales s LEFT JOIN customers c ON c.id = s.customer_id LEFT JOIN sellers sel ON sel.id = s.seller_id
     WHERE $w ORDER BY s.id DESC LIMIT $per OFFSET " . (($pg - 1) * $per),
    $args
);
$sellers = Database::all('SELECT id, name FROM sellers ORDER BY name');
$qs = ['from' => $from, 'to' => $to, 'seller' => $sellerId ?: '', 'status' => $status, 'tipo' => $stype, 'q' => $q];
?>
<section class="hero slim"><div><h1>Vendas</h1><p>Historico completo com filtros por periodo, vendedor e situacao do pagamento.</p></div>
  <div class="big"><small><?= (int) $sum['n'] ?> venda(s) no periodo</small><strong><?= money($sum['total']) ?></strong></div></section>
<div class="card">
  <form class="filters" method="get"><input type="hidden" name="page" value="vendas">
    <div><label class="f" for="from">De</label><input id="from" type="date" name="from" value="<?= e($from) ?>"></div>
    <div><label class="f" for="to">Ate</label><input id="to" type="date" name="to" value="<?= e($to) ?>"></div>
    <div><label class="f" for="seller">Vendedor</label><select id="seller" name="seller"><option value="">Todos</option><?php foreach ($sellers as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $sellerId === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="f" for="status">Pagamento</label><select id="status" name="status"><option value="">Todos</option><option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Pago</option><option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pendente (a prazo)</option></select></div>
    <div><label class="f" for="tipo">Tipo</label><select id="tipo" name="tipo"><option value="">Todos</option><option value="varejo" <?= $stype === 'varejo' ? 'selected' : '' ?>>Varejo</option><option value="atacado" <?= $stype === 'atacado' ? 'selected' : '' ?>>Atacado</option></select></div>
    <div><label class="f" for="q">Cliente</label><input id="q" name="q" placeholder="Nome ou telefone" value="<?= e($q) ?>"></div>
    <div style="align-self:end"><button class="btn" type="submit">Filtrar</button></div>
  </form>
  <div class="scroll"><table><thead><tr><th>#</th><th>Data</th><th>Cliente</th><th>Vendedor</th><th>Pagamento</th><th class="num">Total</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td>#<?= (int) $r['id'] ?><?= $r['sale_type'] === 'atacado' ? ' <span class="tag atac">atacado</span>' : '' ?></td><td><?= e(fmt_date($r['created_at'], true)) ?></td><td><?= e($r['customer'] ?: '-') ?></td><td><?= e($r['seller'] ?: '-') ?></td>
      <td><?= e($r['methods']) ?> <span class="tag <?= $r['payment_status'] === 'paid' ? 'green' : 'warn' ?>"><?= $r['payment_status'] === 'paid' ? 'pago' : 'pendente' ?></span></td>
      <td class="num"><b><?= money($r['total']) ?></b></td><td class="actions"><a class="btn ghost sm" href="<?= e(url('vendas', ['id' => $r['id']])) ?>">Detalhes</a><?php if (App\Auth::isAdmin()): ?><a class="btn ghost sm" href="<?= e(url('vendas', ['edit' => $r['id']])) ?>">Editar</a><?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted">Nenhuma venda neste filtro.</td></tr><?php endif; ?></tbody></table></div>
  <?php $pages = (int) ceil($sum['n'] / $per); if ($pages > 1): ?>
    <div class="pager"><?php if ($pg > 1): ?><a class="btn ghost sm" href="<?= e(url('vendas', $qs + ['p' => $pg - 1])) ?>">Anterior</a><?php endif; ?><span class="small">Pagina <?= $pg ?> de <?= $pages ?></span><?php if ($pg < $pages): ?><a class="btn ghost sm" href="<?= e(url('vendas', $qs + ['p' => $pg + 1])) ?>">Proxima</a><?php endif; ?></div>
  <?php endif; ?>
</div>

<?php if (App\Auth::isAdmin()): ?><p class="small" style="text-align:right;margin-top:14px"><a href="<?= e(url('zerar')) ?>" class="muted">Zerar vendas de teste</a></p><?php endif; ?>
