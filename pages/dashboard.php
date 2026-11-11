<?php
use App\Database;
use App\Finance;
use App\Reports;

$month = preg_match('/^\d{4}-\d{2}$/', $_GET['m'] ?? '') ? $_GET['m'] : date('Y-m');
$s = Reports::summary($month);
$chart = Reports::daily($month);

$bySeller = Database::all(
    "SELECT sel.name, SUM(s.total) total FROM sales s JOIN sellers sel ON sel.id = s.seller_id
     WHERE DATE_FORMAT(s.created_at,'%Y-%m') = ? GROUP BY sel.id, sel.name ORDER BY total DESC",
    [$month]
);
$byPay = Database::all(
    "SELECT p.method, SUM(p.amount) total FROM sale_payments p JOIN sales s ON s.id = p.sale_id
     WHERE DATE_FORMAT(s.created_at,'%Y-%m') = ? GROUP BY p.method ORDER BY total DESC",
    [$month]
);
$recent = Database::all(
    "SELECT s.id, s.total, s.created_at, s.payment_status, CONCAT_WS(' ', c.first_name, c.last_name) customer
     FROM sales s LEFT JOIN customers c ON c.id = s.customer_id ORDER BY s.id DESC LIMIT 6"
);
$openAr = (float) Database::value('SELECT COALESCE(SUM(amount),0) FROM accounts_receivable WHERE paid_at IS NULL');
$fr = Finance::range('mes');
$alerts = array_values(array_filter(
    Finance::alerts(Finance::summary($fr['from'], $fr['to'], $fr['days'])),
    fn ($a) => in_array($a['level'], ['red', 'orange'], true)
));

$months = [];
for ($k = 0; $k < 12; $k++) {
    $months[] = date('Y-m', strtotime("first day of -$k month"));
}
$payLabel = ['dinheiro' => 'Dinheiro', 'pix' => 'Pix', 'cartao' => 'Cartão', 'prazo' => 'A prazo', 'troca' => 'Troca (usados)'];
?>
<div class="page-head">
  <div><h1>Dashboard</h1><p>Faturamento, vendas e lucro do mês.</p></div>
  <form method="get"><input type="hidden" name="page" value="dashboard">
    <select name="m" onchange="this.form.submit()" aria-label="Mês"><?php foreach ($months as $m): ?><option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= date('m/Y', strtotime($m . '-01')) ?></option><?php endforeach; ?></select></form>
</div>

<div class="m-cards">
  <div class="m-card dark"><span>Faturamento</span><strong><?= money($s['revenue']) ?></strong></div>
  <div class="m-card"><span>Lucro líquido estimado</span><strong class="<?= $s['profit'] < 0 ? 'neg' : '' ?>"><?= money($s['profit']) ?></strong><small>margem <?= number_format($s['margin'], 1, ',', '.') ?>%</small></div>
  <div class="m-card"><span>Vendas</span><strong><?= (int) $s['sales'] ?></strong></div>
  <div class="m-card"><span>Atacado</span><strong><?= money($s['wholesale']) ?></strong><small><?= (int) $s['wholesale_n'] ?> venda(s) · varejo <?= money($s['revenue'] - $s['wholesale']) ?></small></div>
  <div class="m-card"><span>A receber</span><strong><?= money($openAr) ?></strong></div>
</div>

<div class="two">
  <div class="card"><h2>Faturamento por vendedor</h2>
    <?php foreach ($bySeller as $r): ?><div class="lrow"><span><?= e($r['name']) ?></span><b><?= money($r['total']) ?></b></div><?php endforeach; ?>
    <?php if (!$bySeller): ?><p class="muted" style="margin-top:12px">Sem vendas com vendedor neste mês.</p><?php endif; ?></div>
  <div class="card"><h2>Recebido por forma de pagamento</h2>
    <?php foreach ($byPay as $r): ?><div class="lrow"><span><?= e($payLabel[$r['method']] ?? $r['method']) ?></span><b><?= money($r['total']) ?></b></div><?php endforeach; ?>
    <?php if (!$byPay): ?><p class="muted" style="margin-top:12px">Sem pagamentos neste mês.</p><?php endif; ?></div>
</div>

<div class="card">
  <div class="actions" style="justify-content:space-between;align-items:center"><h2>Faturamento por dia</h2><div class="chips" id="chips"></div></div>
  <div class="chart-box" style="margin-top:12px;height:300px"><canvas id="chart" aria-label="Faturamento diário" role="img"></canvas></div>
</div>

<?php if ($alerts): ?>
<div class="card"><div class="actions" style="justify-content:space-between;align-items:center"><h2>Avisos do contador</h2><a class="btn ghost sm" href="<?= e(url('financeiro')) ?>">Ver financeiro</a></div>
  <?php foreach (array_slice($alerts, 0, 3) as $a): ?><div class="mini-alert"><i class="<?= e($a['level']) ?>"></i><div><b><?= e($a['title']) ?></b><small><?= e($a['text']) ?></small></div></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card"><div class="actions" style="justify-content:space-between;align-items:center"><h2>Últimas vendas</h2><div class="actions"><a class="btn green sm" href="<?= e(url('pdv')) ?>">Nova venda</a><a class="btn ghost sm" href="<?= e(url('vendas')) ?>">Ver todas</a></div></div>
  <div class="scroll"><table><thead><tr><th>#</th><th>Data</th><th>Cliente</th><th>Pagamento</th><th class="num">Total</th></tr></thead><tbody>
  <?php foreach ($recent as $r): ?><tr><td><a href="<?= e(url('vendas', ['id' => $r['id']])) ?>">#<?= (int) $r['id'] ?></a></td><td><?= e(fmt_date($r['created_at'], true)) ?></td><td><?= e($r['customer'] ?: '-') ?></td>
    <td><span class="tag <?= $r['payment_status'] === 'paid' ? 'green' : 'warn' ?>"><?= $r['payment_status'] === 'paid' ? 'pago' : 'pendente' ?></span></td><td class="num"><b><?= money($r['total']) ?></b></td></tr><?php endforeach; ?>
  <?php if (!$recent): ?><tr><td colspan="5" class="muted">Nenhuma venda ainda.</td></tr><?php endif; ?></tbody></table></div></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
  const d = <?= json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  if (typeof Chart === 'undefined') {
    const max = Math.max(1, ...d.revenue), box = document.querySelector('.chart-box');
    box.innerHTML = '<div class="fb-bars">' + d.revenue.map((v, i) => `<i style="height:${Math.max(1, v / max * 100)}%" title="Dia ${d.labels[i]}: R$ ${v.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}"></i>`).join('') + '</div>';
    return;
  }
  const datasets = [
    { label: 'Faturamento', data: d.revenue, borderColor: '#e0b400', backgroundColor: 'rgba(255,210,26,.22)', fill: true, tension: .25, borderWidth: 2.5, pointRadius: 2 },
    { label: 'Lucro líquido', data: d.profit, borderColor: '#141414', borderDash: [6, 4], tension: .25, pointRadius: 0 },
  ];
  const chart = new Chart(document.getElementById('chart'), {
    type: 'line', data: { labels: d.labels, datasets },
    options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { display: false } },
      scales: { x: { grid: { display: false } }, y: { ticks: { callback: (v) => 'R$ ' + v.toLocaleString('pt-BR') } } } },
  });
  const box = document.getElementById('chips');
  datasets.forEach((ds, i) => {
    const b = document.createElement('button');
    b.type = 'button'; b.className = 'chip'; b.textContent = ds.label; b.style.background = ds.borderColor; if (ds.borderColor === '#e0b400') b.style.color = '#141414';
    b.onclick = () => { chart.setDatasetVisibility(i, !chart.isDatasetVisible(i)); b.classList.toggle('off', !chart.isDatasetVisible(i)); chart.update(); };
    box.appendChild(b);
  });
})();
</script>
