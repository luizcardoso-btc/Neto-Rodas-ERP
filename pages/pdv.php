<?php
use App\Database;

$source = 'pdv';
$fixedSeller = null;
$self = url('pdv');
if (isset($_GET['lookup'])) {
    App\Lookup::respond();
}
require __DIR__ . '/_receivables_actions.php';
require __DIR__ . '/_sale_handler.php';

$sellers = Database::all('SELECT id, name FROM sellers WHERE active = 1 ORDER BY name');
$recent = Database::all(
    "SELECT s.id, s.total, s.created_at, s.payment_status, CONCAT_WS(' ', c.first_name, c.last_name) AS customer
     FROM sales s LEFT JOIN customers c ON c.id = s.customer_id
     WHERE s.created_at >= NOW() - INTERVAL 3 DAY ORDER BY s.id DESC LIMIT 8"
);
?>
<section class="hero slim"><div><h1>PDV</h1><p>Registre a venda no balcao: cliente, itens e forma de pagamento.</p></div></section>
<div class="cols" style="grid-template-columns:1fr 320px">
  <div><?php require __DIR__ . '/_sale_form.php'; ?></div>
  <div style="display:grid;gap:16px;align-content:start">
  <div class="card"><h2>Ultimas vendas</h2>
    <?php foreach ($recent as $r): ?>
      <div style="display:flex;justify-content:space-between;gap:8px;margin:10px 0;border-bottom:1px solid #f1f5f9;padding-bottom:8px">
        <span><b>#<?= (int) $r['id'] ?></b> <?= e($r['customer'] ?: 'Sem cliente') ?><br><span class="small muted"><?= e(fmt_date($r['created_at'], true)) ?></span></span>
        <span style="text-align:right;white-space:nowrap"><b><?= money($r['total']) ?></b><?php if (App\Auth::isAdmin()): ?><br><a class="small" href="<?= e(url('vendas', ['edit' => $r['id']])) ?>">Editar</a> · <form method="post" action="index.php?page=vendas" style="display:inline" onsubmit="return confirm('Apagar a venda #<?= (int) $r['id'] ?>? Os itens voltam ao estoque. Isso nao pode ser desfeito.')"><?= App\Csrf::field() ?><input type="hidden" name="action" value="delete_sale"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="back" value="<?= e(url('pdv')) ?>"><button class="linklike danger-t" type="submit">Apagar</button></form><?php endif; ?></span></div>
    <?php endforeach; ?>
    <?php if (!$recent): ?><p class="muted">Nenhuma venda nos ultimos 3 dias.</p><?php endif; ?>
  </div>
  <div class="card pdv-ar"><h2>A receber (a prazo)</h2>
    <p class="small muted" style="margin:4px 0 10px">Clientes que ficaram de pagar depois. Edite a data, registre o recebimento (total ou parte) ou apague.</p>
    <?php $arRows = App\Receivables::list(true, 15); $arBack = url('pdv'); require __DIR__ . '/_receivables.php'; ?>
    <p class="small" style="margin:10px 0 0"><a href="<?= e(url('financeiro', ['tab' => 'receber'])) ?>">Ver todas no Financeiro</a></p>
  </div>
  </div>
</div>
