<?php
use App\Database;

$source = 'pdv';
$fixedSeller = null;
$self = url('pdv');
if (isset($_GET['lookup'])) {
    App\Lookup::respond();
}
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
  <div class="card"><h2>Ultimas vendas</h2>
    <?php foreach ($recent as $r): ?>
      <p style="display:flex;justify-content:space-between;gap:8px;margin:10px 0;border-bottom:1px solid #f1f5f9;padding-bottom:8px">
        <span><b>#<?= (int) $r['id'] ?></b> <?= e($r['customer'] ?: 'Sem cliente') ?><br><span class="small muted"><?= e(fmt_date($r['created_at'], true)) ?></span></span>
        <b><?= money($r['total']) ?></b></p>
    <?php endforeach; ?>
    <?php if (!$recent): ?><p class="muted">Nenhuma venda nos ultimos 3 dias.</p><?php endif; ?>
  </div>
</div>
