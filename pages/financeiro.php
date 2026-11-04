<?php
use App\Auth;
use App\Database;
use App\Finance;
use App\Settings;

const FIN_TABS = ['resumo' => 'Resumo', 'lucro' => 'Lucro estimado', 'despesas' => 'Despesas e impostos', 'credor' => 'Credor', 'receber' => 'A receber', 'custos' => 'Custos e taxas'];

$tab = isset(FIN_TABS[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'resumo';
$pkey = isset(Finance::PERIODS[$_GET['p'] ?? '']) ? $_GET['p'] : 'mes';
$cm = preg_match('/^\d{4}-\d{2}$/', $_GET['cm'] ?? '') ? $_GET['cm'] : date('Y-m');

// ------------------------------------------------------------------ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $back = ['tab' => isset(FIN_TABS[$_POST['tab'] ?? '']) ? $_POST['tab'] : 'resumo', 'p' => $pkey, 'cm' => $cm];
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['due_date'] ?? '') ? $_POST['due_date'] : null;
    try {
        if ($action === 'add_expense' || $action === 'add_tax' || $action === 'add_asset') {
            $amount = parse_money($_POST['amount'] ?? 0);
            if ($action === 'add_tax') {
                $comp = preg_match('/^\d{4}-\d{2}$/', $_POST['competence'] ?? '') ? $_POST['competence'] : date('Y-m');
                $desc = upper((string) ($_POST['tax_type'] ?? 'IMPOSTO')) . ' - ' . substr($comp, 5, 2) . '/' . substr($comp, 0, 4);
                $cat = 'IMPOSTOS';
                $kind = 'imposto';
                $repeat = 1;
            } else {
                $desc = upper((string) ($_POST['description'] ?? ''));
                $cat = upper((string) ($_POST['category'] ?? '')) ?: null;
                $kind = $action === 'add_asset' ? 'ativo' : 'despesa';
                $repeat = max(1, min(12, (int) ($_POST['repeat'] ?? 1)));
            }
            if ($desc === '' || $amount <= 0) {
                throw new DomainException('Informe descricao e valor.');
            }
            $paidNow = !empty($_POST['paid']);
            for ($i = 0; $i < $repeat; $i++) {
                $due = $date ? date('Y-m-d', strtotime("$date +$i month")) : null;
                $paidAt = $paidNow && $i === 0 ? ($due ? $due . ' 12:00:00' : date('Y-m-d H:i:s')) : null;
                Database::insert(
                    'INSERT INTO accounts_payable (description, category, kind, amount, due_date, paid_at) VALUES (?,?,?,?,?,?)',
                    [mb_substr($desc, 0, 200), $cat, $kind, $amount, $due, $paidAt]
                );
            }
            flash('ok', $kind === 'imposto' ? 'Pagamento de imposto lancado.' : ($kind === 'ativo' ? 'Boleto de mercadoria lancado (fica fora das despesas operacionais).' : ($repeat > 1 ? "Despesa lancada ($repeat meses)." : 'Despesa lancada.')));
        } elseif ($action === 'add_payroll') {
            $amount = parse_money($_POST['amount'] ?? 0);
            $paidOn = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['paid_on'] ?? '') ? $_POST['paid_on'] : date('Y-m-d');
            $week = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['week_start'] ?? '') ? $_POST['week_start'] : null;
            $sid = (int) ($_POST['seller_id'] ?? 0);
            $name = $sid ? (string) Database::value('SELECT name FROM sellers WHERE id = ?', [$sid]) : upper((string) ($_POST['employee'] ?? ''));
            if ($name === '' || $amount <= 0) {
                throw new DomainException('Escolha o funcionario e informe o valor.');
            }
            Database::run('INSERT INTO payroll_payments (seller_id, employee, amount, week_start, paid_on) VALUES (?,?,?,?,?)', [$sid ?: null, mb_substr($name, 0, 120), $amount, $week, $paidOn]);
            flash('ok', 'Pagamento da folha lancado.');
        } elseif ($action === 'payroll_all') {
            $paidOn = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['paid_on'] ?? '') ? $_POST['paid_on'] : date('Y-m-d');
            $week = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['week_start'] ?? '') ? $_POST['week_start'] : null;
            $n = 0;
            foreach (Database::all('SELECT id, name, weekly_pay FROM sellers WHERE active = 1 AND weekly_pay > 0') as $sl) {
                Database::run('INSERT INTO payroll_payments (seller_id, employee, amount, week_start, paid_on) VALUES (?,?,?,?,?)', [$sl['id'], $sl['name'], $sl['weekly_pay'], $week, $paidOn]);
                $n++;
            }
            if (!$n) {
                throw new DomainException('Nenhum vendedor com pagamento semanal cadastrado (Vendedores).');
            }
            flash('ok', "Folha da semana lancada para $n funcionario(s).");
        } elseif ($action === 'delete_payroll') {
            Database::run('DELETE FROM payroll_payments WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
            flash('ok', 'Pagamento da folha removido.');
        } elseif ($action === 'edit_payable') {
            $id = (int) ($_POST['id'] ?? 0);
            $row = Database::one('SELECT * FROM accounts_payable WHERE id = ?', [$id]);
            $amount = parse_money($_POST['amount'] ?? 0);
            $desc = upper((string) ($_POST['description'] ?? ''));
            if (!$row || $desc === '' || $amount <= 0) {
                throw new DomainException('Informe descricao e valor.');
            }
            $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['due_date'] ?? '') ? $_POST['due_date'] : null;
            $paid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['paid_date'] ?? '') ? $_POST['paid_date'] . ' 12:00:00' : null;
            if ($paid !== null && substr((string) $row['paid_at'], 0, 10) === substr($paid, 0, 10)) {
                $paid = $row['paid_at']; // mesma data: preserva a hora
            }
            $kind = $row['kind'] === 'imposto' ? 'imposto' : (($_POST['kind'] ?? '') === 'ativo' ? 'ativo' : 'despesa');
            Database::run('UPDATE accounts_payable SET description = ?, category = ?, kind = ?, amount = ?, due_date = ?, paid_at = ? WHERE id = ?',
                [mb_substr($desc, 0, 200), upper((string) ($_POST['category'] ?? '')) ?: null, $kind, $amount, $due, $paid, $id]);
            flash('ok', 'Lancamento atualizado.');
        } elseif ($action === 'set_kind') {
            $k = in_array($_POST['kind'] ?? '', ['despesa', 'ativo'], true) ? $_POST['kind'] : 'despesa';
            Database::run("UPDATE accounts_payable SET kind = ? WHERE id = ? AND kind <> 'imposto'", [$k, (int) ($_POST['id'] ?? 0)]);
            flash('ok', $k === 'ativo' ? 'Movido para Mercadorias (ativos): sai das despesas operacionais.' : 'Movido para Despesas operacionais.');
        } elseif ($action === 'wipe_fin' && Auth::isAdmin()) {
            if (strtoupper(trim((string) ($_POST['confirm'] ?? ''))) !== 'APAGAR') {
                throw new DomainException('Digite APAGAR para confirmar.');
            }
            $d = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['since'] ?? '') ? $_POST['since'] : date('Y-m-d');
            // so lancamentos feitos a partir da data: despesas, impostos, mercadorias e folha. Vendas nao sao tocadas.
            $n1 = Database::run('DELETE FROM accounts_payable WHERE created_at >= ?', [$d . ' 00:00:00']);
            $n2 = Database::run('DELETE FROM payroll_payments WHERE created_at >= ?', [$d . ' 00:00:00']);
            flash('ok', "Apagados $n1 lancamento(s) de despesas/impostos/mercadorias e $n2 pagamento(s) de folha lancados desde " . date('d/m/Y', strtotime($d)) . '. As vendas foram mantidas.');
        } elseif ($action === 'add_receivable') {
            $amount = parse_money($_POST['amount'] ?? 0);
            $desc = upper((string) ($_POST['description'] ?? ''));
            if ($desc === '' || $amount <= 0) {
                throw new DomainException('Informe descricao e valor do recebimento.');
            }
            Database::insert('INSERT INTO accounts_receivable (description, amount, due_date) VALUES (?,?,?)', [mb_substr($desc, 0, 200), $amount, $date]);
            flash('ok', 'Conta a receber cadastrada.');
        } elseif ($action === 'pay' && in_array($_POST['table'] ?? '', ['accounts_payable', 'accounts_receivable'], true)) {
            $t = $_POST['table'];
            Database::run("UPDATE $t SET paid_at = NOW() WHERE id = ? AND paid_at IS NULL", [(int) $_POST['id']]);
            if ($t === 'accounts_receivable' && ($sid = Database::value('SELECT sale_id FROM accounts_receivable WHERE id = ?', [(int) $_POST['id']]))) {
                if (!Database::value('SELECT COUNT(*) FROM accounts_receivable WHERE sale_id = ? AND paid_at IS NULL', [$sid])) {
                    Database::run("UPDATE sales SET payment_status = 'paid' WHERE id = ?", [$sid]);
                }
            }
            flash('ok', 'Baixa registrada.');
        } elseif ($action === 'delete' && in_array($_POST['table'] ?? '', ['accounts_payable', 'accounts_receivable'], true)) {
            Database::run('DELETE FROM ' . $_POST['table'] . ' WHERE id = ? AND paid_at IS NULL', [(int) $_POST['id']]);
            flash('ok', 'Lancamento removido.');
        } elseif ($action === 'add_creditor') {
            Finance::addCreditorPayment(
                (int) ($_POST['seller_id'] ?? 0) ?: null,
                parse_money($_POST['amount'] ?? 0),
                (string) ($_POST['paid_on'] ?? ''),
                upper((string) ($_POST['note'] ?? '')),
                !empty($_POST['confirm_dup']),
                Auth::user()['username'] ?? null
            );
            flash('ok', 'Repasse ao credor registrado.');
        } elseif ($action === 'delete_creditor' && Auth::isAdmin()) {
            Database::run("DELETE FROM creditor_payments WHERE id = ? AND source = 'manual'", [(int) $_POST['id']]);
            flash('ok', 'Repasse removido.');
        } elseif ($action === 'save_rates' && Auth::isAdmin()) {
            App\CardFees::saveRates($_POST);
            flash('ok', 'Tabela de taxas das máquinas atualizada (vale para as próximas vendas).');
        } elseif ($action === 'save_costs' && Auth::isAdmin()) {
            Settings::save(array_intersect_key($_POST, array_flip(['tax_rate', 'card_fee_rate', 'creditor_name', 'creditor_monthly_goal'])));
            flash('ok', 'Custos e taxas atualizados.');
        }
    } catch (DomainException $e) {
        flash('err', $e->getMessage());
    }
    redirect(url('financeiro', $back));
}

// ------------------------------------------------------------------ dados
$range = Finance::range($pkey);
$s = Finance::summary($range['from'], $range['to'], $range['days']);
$cfg = Settings::all();
$fq = fn (array $o = []) => url('financeiro', array_merge(['tab' => $tab, 'p' => $pkey, 'cm' => $cm], $o));
$pct = fn (float $v) => $s['revenue'] > 0 ? round(abs($v) / $s['revenue'] * 100, 1) : 0.0;
$H = fn (string $t, string $sub = '') => print('<h2>' . e($t) . '</h2>' . ($sub !== '' ? '<p class="sub">' . e($sub) . '</p>' : ''));

function fin_period_bar(string $tab, string $pkey, string $cm, array $range): void
{ ?>
  <div class="fin-periods"><?php foreach (App\Finance::PERIODS as $k => $l): ?>
    <a class="pill <?= $k === $pkey ? 'on' : '' ?>" href="<?= e(url('financeiro', ['tab' => $tab, 'p' => $k, 'cm' => $cm])) ?>"><?= e($l) ?></a><?php endforeach; ?>
    <span class="small muted" style="align-self:center"><?= e(fmt_date($range['from'])) ?> a <?= e(fmt_date($range['to'])) ?></span></div>
<?php }

function fin_kpis(array $s, string $base): void
{ ?>
  <div class="fin-kpis">
    <div class="kpi"><span>Receita bruta</span><strong><?= money($s['revenue']) ?></strong><small><?= (int) $s['sales'] ?> venda(s) · ticket <?= money($s['ticket']) ?></small></div>
    <div class="kpi"><span>Despesas de ativos</span><strong><?= money($s['assets']) ?></strong><small>boletos de pneus/rodas pagos no período</small></div>
    <div class="kpi"><span>Receita − ativos</span><strong class="<?= $s['after_assets'] < 0 ? 'neg-t' : '' ?>"><?= money($s['after_assets']) ?></strong><small>receita bruta <?= money($s['revenue']) ?> − despesas de ativos <?= money($s['assets']) ?></small></div>
    <div class="kpi"><span>Lucro bruto</span><strong class="ok"><?= money($s['gross']) ?></strong><small>receita − custo das mercadorias vendidas − taxas − impostos</small></div>
    <div class="kpi"><span>Despesas operacionais</span><strong><?= money($s['expenses'] + $s['payroll']) ?></strong><small>despesas <?= money($s['expenses']) ?> + folha semanal <?= money($s['payroll']) ?></small></div>
    <div class="kpi final <?= $s['profit'] < 0 ? 'neg' : '' ?>"><span>Lucro estimado final</span><strong class="<?= $s['profit'] < 0 ? 'neg' : 'ok' ?>"><?= money($s['profit']) ?></strong><small>margem <?= number_format($s['margin'], 1, ',', '.') ?>%</small></div>
  </div>
<?php }

function fin_alerts(array $alerts, int $limit = 99): void
{
    $dot = ['red' => '🔴', 'orange' => '🟠', 'blue' => '🔵', 'green' => '🟢']; ?>
  <div class="alert-grid"><?php foreach (array_slice($alerts, 0, $limit) as $a): ?>
    <div class="alert <?= e($a['level']) ?>"><b><?= $dot[$a['level']] ?> <?= e($a['title']) ?></b><span><?= e($a['text']) ?></span></div><?php endforeach; ?>
  <?php if (!$alerts): ?><div class="alert green"><b>🟢 Tudo em ordem</b><span>Nenhum alerta no momento.</span></div><?php endif; ?></div>
<?php }

function fin_edit_form(array $r, string $tab): void
{ ?>
  <details class="edit-pay"><summary class="btn ghost sm">Editar</summary>
    <form method="post" class="stack edit-pay-form"><?= App\Csrf::field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><input type="hidden" name="action" value="edit_payable"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <label class="f">Descrição</label><input name="description" value="<?= e($r['description']) ?>" required>
      <div class="row c2"><div><label class="f">Valor</label><input name="amount" inputmode="decimal" value="<?= e(number_format((float) $r['amount'], 2, ',', '')) ?>" required></div><div><label class="f">Categoria</label><input name="category" value="<?= e($r['category'] ?? '') ?>"></div></div>
      <div class="row c2"><div><label class="f">Vencimento</label><input type="date" name="due_date" value="<?= e((string) $r['due_date']) ?>"></div><div><label class="f">Pago em (vazio = em aberto)</label><input type="date" name="paid_date" value="<?= e($r['paid_at'] ? substr($r['paid_at'], 0, 10) : '') ?>"></div></div>
      <?php if (($r['kind'] ?? '') !== 'imposto'): ?><label class="f">Tipo</label><select name="kind"><option value="despesa" <?= ($r['kind'] ?? '') === 'despesa' ? 'selected' : '' ?>>Despesa operacional</option><option value="ativo" <?= ($r['kind'] ?? '') === 'ativo' ? 'selected' : '' ?>>Mercadoria (ativo / boleto)</option></select><?php endif; ?>
      <div class="actions"><button class="btn green sm" type="submit">Salvar</button><button class="btn ghost sm" type="button" onclick="this.closest('details').open=false">Cancelar</button></div></form></details>
<?php }

function fin_table(array $rows, string $table, bool $hasCustomer, string $tab): void
{ ?>
  <div class="scroll"><table><thead><tr><th>Descricao</th><th>Vencimento</th><th class="num">Valor</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): $late = !$r['paid_at'] && $r['due_date'] && $r['due_date'] < date('Y-m-d'); ?>
    <tr style="<?= $r['paid_at'] ? 'opacity:.55' : '' ?>"><td><?= e($r['description']) ?><?= isset($r['kind']) && $r['kind'] === 'imposto' ? ' <span class="tag warn">imposto</span>' : (($r['kind'] ?? '') === 'ativo' ? ' <span class="tag blue">mercadoria</span>' : '') ?><?= $hasCustomer && !empty($r['customer']) ? '<br><span class="small muted">' . e($r['customer']) . '</span>' : '' ?><?= !empty($r['category']) && ($r['kind'] ?? '') !== 'imposto' ? '<br><span class="small muted">' . e($r['category']) . '</span>' : '' ?></td>
      <td><?= e(fmt_date($r['due_date'])) ?> <?php if ($r['paid_at']): ?><span class="tag green">pago</span><?php elseif ($late): ?><span class="tag red">atrasado</span><?php endif; ?></td>
      <td class="num"><b><?= money($r['amount']) ?></b></td>
      <td class="actions"><?php if ($table === 'accounts_payable'): fin_edit_form($r, $tab); endif; ?><?php if (!$r['paid_at']): ?>
        <form method="post"><?= App\Csrf::field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><input type="hidden" name="action" value="pay"><input type="hidden" name="table" value="<?= $table ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn green sm" type="submit">Dar baixa</button></form>
        <form method="post" onsubmit="return confirm('Remover lancamento?')"><?= App\Csrf::field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="<?= $table ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn danger sm" type="submit">Remover</button></form>
      <?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="4" class="muted">Nenhum lancamento.</td></tr><?php endif; ?></tbody></table></div>
<?php } ?>
<section class="hero slim"><div><h1>Financeiro</h1><p>Resultado da loja, despesas e impostos, repasses ao credor e o contador virtual avisando o que precisa de atenção.</p></div></section>
<div class="fin-tabs"><?php foreach (FIN_TABS as $k => $l): ?><a class="pill <?= $k === $tab ? 'dark' : '' ?>" href="<?= e(url('financeiro', ['tab' => $k, 'p' => $pkey, 'cm' => $cm])) ?>"><?= e($l) ?></a><?php endforeach; ?></div>

<?php if ($tab === 'resumo'): ?>
  <?php fin_period_bar($tab, $pkey, $cm, $range); fin_kpis($s, ''); ?>
  <div class="card"><h2>🧮 Alertas do contador</h2><p class="sub">O contador virtual confere vendas, contas, impostos, estoque e repasses e avisa o que merece atenção.</p>
    <?php fin_alerts(Finance::alerts($s)); ?></div>
  <?php
    $openAr = (float) Database::value('SELECT COALESCE(SUM(amount),0) FROM accounts_receivable WHERE paid_at IS NULL');
    $openAp = (float) Database::value('SELECT COALESCE(SUM(amount),0) FROM accounts_payable WHERE paid_at IS NULL');
    $st = Database::one("SELECT COALESCE(SUM(stock),0) units, COALESCE(SUM(stock*cost_price),0) capital FROM products WHERE active = 1 AND stock > 0 AND condition_type = 'novo'");
    $cr = Finance::creditor(date('Y-m'));
  ?>
  <div class="cols fin-cols">
    <div class="card"><h2>Situação do negócio</h2>
      <div class="tiles">
        <div class="tile"><span>A receber</span><b><?= money($openAr) ?></b><small>vendas a prazo e lançamentos</small></div>
        <div class="tile"><span>A pagar</span><b><?= money($openAp) ?></b><small>contas em aberto</small></div>
        <div class="tile"><span>Estoque novo</span><b><?= (int) $st['units'] ?> un.</b><small>capital parado <?= money($st['capital']) ?></small></div>
        <div class="tile"><span>Ponto de equilíbrio</span><b><?= $s['breakeven'] === null ? '-' : money($s['breakeven']) ?></b><small>faturamento para cobrir custos fixos do período</small></div>
        <div class="tile"><span><?= e($cr['name']) ?> no mês</span><b><?= money($cr['received']) ?></b><small><?= $cr['goal'] > 0 ? 'meta ' . money($cr['goal']) : 'repassado pelos vendedores' ?></small></div>
        <div class="tile"><span>Saldo previsto</span><b><?= money($openAr - $openAp) ?></b><small>a receber − a pagar</small></div>
      </div></div>
    <div class="card"><h2>Movimentações recentes</h2><p class="sub">Vendas, despesas, impostos, repasses, ajustes e edições.</p>
      <?php $kinds = ['venda' => ['green', 'venda'], 'despesa' => ['warn', 'despesa'], 'imposto' => ['warn', 'imposto'], 'ativo' => ['blue', 'mercadoria'], 'credor' => ['blue', 'credor'], 'estoque' => ['', 'estoque'], 'edicao' => ['red', 'edição']]; ?>
      <table><tbody><?php foreach (Finance::movements(12) as $m): ?><tr><td><span class="tag <?= $kinds[$m['kind']][0] ?>"><?= e($kinds[$m['kind']][1]) ?></span></td><td><?= e($m['text']) ?><br><span class="small muted"><?= e(fmt_date($m['ts'], true)) ?></span></td><td class="num"><?= $m['amount'] === null ? '' : '<b>' . money($m['amount']) . '</b>' ?></td></tr><?php endforeach; ?></tbody></table></div>
  </div>

<?php elseif ($tab === 'lucro'): ?>
  <?php fin_period_bar($tab, $pkey, $cm, $range); fin_kpis($s, '');
  $parts = Finance::breakdown($s); ?>
  <div class="card"><h2>Para onde vai cada R$ da receita</h2>
    <div class="bar-stack" role="img" aria-label="Distribuição da receita"><?php foreach ($parts as $p): if ($p['value'] > 0 && $s['revenue'] > 0): ?><i style="width:<?= min(100, $pct($p['value'])) ?>%;background:<?= $p['color'] ?>" title="<?= e($p['label']) ?>"></i><?php endif; endforeach; ?></div>
    <?php if ($s['revenue'] > 0 && $s['profit'] < 0): ?><p class="neg-note"><b>Resultado negativo:</b> custos e despesas passam da receita em <?= money(abs($s['profit'])) ?>.</p><?php endif; ?>
    <?php if ($s['revenue'] <= 0): ?><p class="muted">Sem vendas no período selecionado.</p><?php endif; ?>
    <div class="legend-grid"><?php foreach ($parts as $p): ?>
      <div><i style="background:<?= $p['color'] ?>"></i><span class="lg-l"><?= e($p['label']) ?><small><?= e($p['hint']) ?></small></span><span class="lg-v"><?= $p['label'] === 'Lucro estimado final' ? '' : '- ' ?><b class="<?= $p['label'] === 'Lucro estimado final' && $p['value'] < 0 ? 'neg-t' : '' ?>"><?= $p['label'] === 'Lucro estimado final' ? money($p['value']) : money(abs($p['value'])) ?></b><small><?= number_format($pct($p['value']), 1, ',', '.') ?>%</small></span></div>
    <?php endforeach; ?></div>
    <p class="small muted" style="margin-top:14px">Valores repassados ao credor (<?= money($s['creditor']) ?> no período) <b>não alteram</b> faturamento nem lucro: a venda continua inteira e o repasse aparece só na aba Credor.</p>
  </div>
  <div class="card"><h2>🧮 Alertas do contador</h2><?php fin_alerts(Finance::alerts($s)); ?></div>

<?php elseif ($tab === 'despesas'): ?>
  <?php fin_period_bar($tab, $pkey, $cm, $range);
  $cats = Finance::expensesByCategory($range['from'], $range['to']);
  $items = Database::all("SELECT * FROM accounts_payable WHERE paid_at >= ? AND paid_at < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY paid_at DESC LIMIT 40", [$range['from'], $range['to']]);
  $maxItem = max(1, ...array_map(fn ($i) => (float) $i['amount'], $items ?: [['amount' => 1]]));
  $ap = Database::all('SELECT * FROM accounts_payable WHERE paid_at IS NULL ORDER BY due_date IS NULL, due_date LIMIT 100'); ?>
  <div class="fin-kpis">
    <div class="kpi"><span>Mercadorias pagas (ativos)</span><strong><?= money($s['assets']) ?></strong><small>controle de compras: não entra como despesa operacional</small></div>
    <div class="kpi"><span>Despesas operacionais pagas</span><strong><?= money($s['expenses']) ?></strong><small><?= count($cats) ?> categoria(s)</small></div>
    <div class="kpi"><span>Impostos pagos no período</span><strong class="bad"><?= money($s['taxes']) ?></strong><small>estimado: <?= money($s['tax_estimated']) ?> (<?= number_format((float) $cfg['tax_rate'], 2, ',', '.') ?>%)</small></div>
    <div class="kpi"><span>Folha dos vendedores</span><strong><?= money($s['payroll']) ?></strong><small>pagos no período (previsto por semana: <?= money($s['weekly_total']) ?>)</small></div>
  </div>
  <div class="cols fin-cols">
    <div class="card caps"><h2>Lançar despesa</h2><p class="sub">Aluguel, energia, marketing… (mercadoria de fornecedor vai em "Boletos de mercadoria"). Marque "já paga" para entrar no lucro do período.</p>
      <form method="post" class="stack"><?= App\Csrf::field() ?><input type="hidden" name="action" value="add_expense"><input type="hidden" name="tab" value="despesas">
        <input name="description" placeholder="DESCRIÇÃO *" required>
        <div class="row c2"><input name="category" list="exp-cats" placeholder="CATEGORIA"><input name="amount" inputmode="decimal" placeholder="VALOR *" required></div>
        <datalist id="exp-cats"><?php foreach (Finance::EXPENSE_CATEGORIES as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
        <div class="row c2"><div><label class="f" for="due1">Vencimento / data</label><input id="due1" type="date" name="due_date"></div><div><label class="f" for="rep">Repetir (meses)</label><input id="rep" type="number" name="repeat" min="1" max="12" value="1"></div></div>
        <label class="chk"><input type="checkbox" name="paid" value="1"> Já paga</label>
        <button class="btn green block" type="submit">Lançar despesa</button></form></div>
    <div class="card caps"><h2>Boletos de mercadoria (ativos)</h2><p class="sub">Compra de pneus, rodas e parafusos dos fornecedores. Fica separado das despesas operacionais (controle de compras); o custo no lucro vem das mercadorias vendidas.</p>
      <form method="post" class="stack"><?= App\Csrf::field() ?><input type="hidden" name="action" value="add_asset"><input type="hidden" name="tab" value="despesas">
        <input name="description" placeholder="FORNECEDOR / DESCRIÇÃO *" required>
        <div class="row c2"><input name="category" list="ast-cats" placeholder="TIPO (PNEUS, RODAS…)"><input name="amount" inputmode="decimal" placeholder="VALOR *" required></div>
        <datalist id="ast-cats"><?php foreach (Finance::ASSET_CATEGORIES as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
        <div class="row c2"><div><label class="f" for="due3">Vencimento / data</label><input id="due3" type="date" name="due_date"></div><div><label class="f" for="rep3">Parcelas (boletos mensais)</label><input id="rep3" type="number" name="repeat" min="1" max="12" value="1"></div></div>
        <label class="chk"><input type="checkbox" name="paid" value="1"> Já paga</label>
        <button class="btn block" type="submit">Lançar boleto de mercadoria</button></form></div>
    <div class="card caps"><h2>Pagamento de impostos</h2><p class="sub">Registre as guias pagas (DAS, ICMS, ISS…). Elas entram no lucro e alimentam o contador virtual.</p>
      <form method="post" class="stack"><?= App\Csrf::field() ?><input type="hidden" name="action" value="add_tax"><input type="hidden" name="tab" value="despesas">
        <select name="tax_type" aria-label="Imposto"><?php foreach (Finance::TAX_TYPES as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select>
        <div class="row c2"><div><label class="f" for="comp">Competência</label><input id="comp" type="month" name="competence" value="<?= e(date('Y-m')) ?>"></div><div><label class="f" for="taxv">Valor</label><input id="taxv" name="amount" inputmode="decimal" placeholder="VALOR *" required></div></div>
        <div><label class="f" for="due2">Vencimento / data de pagamento</label><input id="due2" type="date" name="due_date" value="<?= e(date('Y-m-d')) ?>"></div>
        <label class="chk"><input type="checkbox" name="paid" value="1" checked> Já paga</label>
        <button class="btn block" type="submit">Registrar imposto</button></form></div>
  </div>
  <?php $sellersAll = Database::all('SELECT id, name, weekly_pay FROM sellers WHERE active = 1 ORDER BY name');
  $pr = Database::all('SELECT * FROM payroll_payments WHERE paid_on >= ? AND paid_on <= ? ORDER BY paid_on DESC, id DESC LIMIT 60', [$range['from'], $range['to']]); ?>
  <div class="cols fin-cols" style="margin-top:20px">
    <div class="card caps"><h2>Folha de pagamento (semanal)</h2><p class="sub">Lance o pagamento semanal dos funcionários. Entra nas despesas operacionais e abate do lucro final.</p>
      <form method="post" class="stack"><?= App\Csrf::field() ?><input type="hidden" name="action" value="payroll_all"><input type="hidden" name="tab" value="despesas">
        <div class="row c2"><div><label class="f" for="pw1">Semana iniciada em</label><input id="pw1" type="date" name="week_start" value="<?= e(date('Y-m-d', strtotime('monday this week'))) ?>"></div><div><label class="f" for="pp1">Pago em</label><input id="pp1" type="date" name="paid_on" value="<?= e(date('Y-m-d')) ?>"></div></div>
        <button class="btn block" type="submit">Lançar semana de todos (<?= money(array_sum(array_column($sellersAll, 'weekly_pay'))) ?>)</button></form>
      <form method="post" class="stack" style="margin-top:14px"><?= App\Csrf::field() ?><input type="hidden" name="action" value="add_payroll"><input type="hidden" name="tab" value="despesas">
        <div class="row c2"><select name="seller_id" aria-label="Funcionário"><option value="">OUTRO FUNCIONÁRIO…</option><?php foreach ($sellersAll as $sl): ?><option value="<?= (int) $sl['id'] ?>"><?= e($sl['name']) ?></option><?php endforeach; ?></select><input name="employee" placeholder="NOME (se outro)"></div>
        <div class="row c2"><input name="amount" inputmode="decimal" placeholder="VALOR *" required><input type="date" name="paid_on" value="<?= e(date('Y-m-d')) ?>" aria-label="Pago em"></div>
        <button class="btn green block" type="submit">Lançar pagamento</button></form>
      <h3 style="margin:16px 0 6px;font-size:14px">Pagos no período: <?= money($s['payroll']) ?></h3>
      <?php foreach ($pr as $x): ?><div class="exp-row"><div><b><?= e($x['employee']) ?></b><br><span class="small muted">pago em <?= e(fmt_date($x['paid_on'])) ?><?= $x['week_start'] ? ' · semana de ' . e(fmt_date($x['week_start'])) : '' ?></span></div><b><?= money($x['amount']) ?></b><form method="post" style="margin:0" onsubmit="return confirm('Remover pagamento?')"><?= App\Csrf::field() ?><input type="hidden" name="tab" value="despesas"><input type="hidden" name="action" value="delete_payroll"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>"><button class="btn danger sm" type="submit">×</button></form></div><?php endforeach; ?>
      <?php if (!$pr): ?><p class="muted">Nenhum pagamento de folha neste período.</p><?php endif; ?></div>
    <?php if (Auth::isAdmin()): ?><div class="card"><h2>Zerar lançamentos de hoje</h2><p class="sub">Apaga despesas, impostos, boletos de mercadoria e folha <b>lançados</b> a partir da data abaixo. <b>Vendas não são alteradas.</b></p>
      <form method="post" class="stack" onsubmit="return confirm('Apagar os lancamentos financeiros desde a data escolhida? Vendas serao mantidas.')"><?= App\Csrf::field() ?><input type="hidden" name="action" value="wipe_fin"><input type="hidden" name="tab" value="despesas">
        <div class="row c2"><div><label class="f" for="wsince">Lançados desde</label><input id="wsince" type="date" name="since" value="<?= e(date('Y-m-d')) ?>"></div><div><label class="f" for="wconf">Digite APAGAR</label><input id="wconf" name="confirm" autocomplete="off" required></div></div>
        <button class="btn danger" type="submit">Apagar lançamentos financeiros</button></form></div><?php endif; ?>
    <div class="card"><h2>Despesas do período</h2><p class="sub"><?= count($items) ?> lançamento(s) pago(s) entre <?= e(fmt_date($range['from'])) ?> e <?= e(fmt_date($range['to'])) ?></p>
      <?php foreach ($items as $i): ?><div class="exp-row"><div><b><?= e($i['description']) ?></b> <?= $i['kind'] === 'imposto' ? '<span class="tag warn">imposto</span>' : ($i['kind'] === 'ativo' ? '<span class="tag blue">mercadoria</span>' : '') ?><br><span class="small muted"><?= e($i['category'] ?: 'sem categoria') ?> · <?= e(fmt_date($i['paid_at'])) ?></span></div><b><?= money($i['amount']) ?></b><?php fin_edit_form($i, 'despesas'); ?><form method="post" style="margin:0"><?= App\Csrf::field() ?><input type="hidden" name="tab" value="despesas"><input type="hidden" name="action" value="set_kind"><input type="hidden" name="id" value="<?= (int) $i['id'] ?>"><?php if ($i['kind'] === 'despesa'): ?><input type="hidden" name="kind" value="ativo"><button class="btn ghost sm" type="submit" title="É compra de mercadoria? Sai das despesas operacionais">→ mercadoria</button><?php elseif ($i['kind'] === 'ativo'): ?><input type="hidden" name="kind" value="despesa"><button class="btn ghost sm" type="submit">→ despesa</button><?php endif; ?></form><i style="width:<?= round((float) $i['amount'] / $maxItem * 100) ?>%"></i></div><?php endforeach; ?>
      <?php if (!$items): ?><p class="muted">Nenhuma despesa paga neste período.</p><?php endif; ?>
      <?php if ($cats): ?><h3 style="margin:18px 0 6px;font-size:14px">Por categoria</h3><table><tbody><?php foreach ($cats as $c): ?><tr><td><?= e($c['cat']) ?></td><td class="num"><b><?= money($c['total']) ?></b></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
    <div class="card"><h2>Contas a pagar (em aberto)</h2><p class="sub">Dê baixa quando pagar: o valor passa a contar no lucro.</p><?php fin_table($ap, 'accounts_payable', false, 'despesas'); ?></div>
  </div>

<?php elseif ($tab === 'credor'): ?>
  <?php $c = Finance::creditor($cm);
  $months = [];
  for ($k = 0; $k < 12; $k++) { $months[] = date('Y-m', strtotime("first day of -$k month")); }
  $sellers = Database::all('SELECT id, name FROM sellers WHERE active = 1 ORDER BY name'); ?>
  <div class="card"><div class="actions" style="justify-content:space-between;align-items:center">
    <div><h2>Repasses ao credor: <?= e($c['name']) ?></h2><p class="sub" style="margin:0">Quando o vendedor recebe na conta própria e envia ao credor. Marcado no PDV, o repasse entra aqui sozinho e o faturamento da venda não muda.</p></div>
    <form method="get"><input type="hidden" name="page" value="financeiro"><input type="hidden" name="tab" value="credor"><select name="cm" onchange="this.form.submit()" aria-label="Mês"><?php foreach ($months as $m): ?><option value="<?= $m ?>" <?= $m === $c['month'] ? 'selected' : '' ?>><?= date('m/Y', strtotime($m . '-01')) ?></option><?php endforeach; ?></select></form></div></div>
  <div class="fin-kpis">
    <div class="kpi"><span>Recebido no mês</span><strong class="ok"><?= money($c['received']) ?></strong><small>somando todos os vendedores</small></div>
    <div class="kpi"><span>Meta do mês</span><strong><?= $c['goal'] > 0 ? money($c['goal']) : '-' ?></strong><small><?= $c['goal'] > 0 ? 'definida em Custos e taxas' : 'defina em Custos e taxas' ?></small></div>
    <div class="kpi"><span>Falta para a meta</span><strong class="<?= $c['goal'] > 0 && $c['remaining'] > 0 ? 'bad' : 'ok' ?>"><?= $c['goal'] > 0 ? money($c['remaining']) : '-' ?></strong><small><?= $c['pct'] !== null ? number_format($c['pct'], 1, ',', '.') . '% atingido' : '' ?></small></div>
    <div class="kpi"><span>Total já repassado</span><strong><?= money($c['all_time']) ?></strong><small>desde o início</small></div>
  </div>
  <?php if ($c['pct'] !== null): ?><div class="card"><div class="prog"><i style="width:<?= $c['pct'] ?>%"></i></div><p class="small muted" style="margin:8px 0 0"><?= $c['received'] >= $c['goal'] ? '✅ ' . e($c['name']) . ' já recebeu a meta deste mês.' : e($c['name']) . ' ainda não recebeu a meta deste mês.' ?></p></div><?php endif; ?>
  <div class="cols fin-cols">
    <div class="card caps"><h2>Registrar repasse avulso</h2><p class="sub">Use só para repasses que NÃO foram marcados no PDV. O sistema avisa se já existir um igual (mesmo vendedor, valor e data) para evitar pagamento duplicado.</p>
      <form method="post" class="stack"><?= App\Csrf::field() ?><input type="hidden" name="action" value="add_creditor"><input type="hidden" name="tab" value="credor"><input type="hidden" name="cm" value="<?= e($c['month']) ?>">
        <select name="seller_id" required aria-label="Vendedor"><option value="">Vendedor que enviou *</option><?php foreach ($sellers as $sl): ?><option value="<?= (int) $sl['id'] ?>"><?= e($sl['name']) ?></option><?php endforeach; ?></select>
        <div class="row c2"><input name="amount" inputmode="decimal" placeholder="VALOR *" required><input name="paid_on" type="date" value="<?= e(date('Y-m-d')) ?>" aria-label="Data"></div>
        <input name="note" placeholder="OBSERVAÇÃO (EX.: PIX COMPROVANTE)">
        <label class="chk"><input type="checkbox" name="confirm_dup" value="1"> Lançar mesmo assim, se já existir repasse igual</label>
        <button class="btn green block" type="submit">Registrar repasse</button></form>
      <h3 style="margin:18px 0 6px;font-size:14px">Por vendedor (<?= date('m/Y', strtotime($c['month'] . '-01')) ?>)</h3>
      <table><tbody><?php foreach ($c['by_seller'] as $b): ?><tr><td><?= e($b['name']) ?> <span class="small muted"><?= (int) $b['n'] ?> repasse(s)</span></td><td class="num"><b><?= money($b['total']) ?></b></td></tr><?php endforeach; ?>
      <?php if (!$c['by_seller']): ?><tr><td class="muted">Nenhum repasse neste mês.</td></tr><?php endif; ?></tbody></table></div>
    <div class="card"><h2>Extrato do mês</h2>
      <div class="scroll"><table><thead><tr><th>Data</th><th>Vendedor</th><th>Origem</th><th class="num">Valor</th><th></th></tr></thead><tbody>
      <?php foreach ($c['ledger'] as $l): ?><tr><td><?= e(fmt_date($l['paid_on'])) ?></td><td><?= e($l['seller'] ?: '-') ?></td>
        <td><?php if ($l['sale_id']): ?><a href="<?= e(url('vendas', ['id' => $l['sale_id']])) ?>"><span class="tag green">venda #<?= (int) $l['sale_id'] ?></span></a><?php else: ?><span class="tag">avulso</span><?php endif; ?><span class="small muted"><?= e($l['source'] === 'manual' ? ($l['note'] ?? '') : '') ?></span></td>
        <td class="num"><b><?= money($l['amount']) ?></b></td>
        <td class="actions"><?php if ($l['source'] === 'manual' && Auth::isAdmin()): ?><form method="post" onsubmit="return confirm('Remover este repasse?')"><?= App\Csrf::field() ?><input type="hidden" name="action" value="delete_creditor"><input type="hidden" name="tab" value="credor"><input type="hidden" name="cm" value="<?= e($c['month']) ?>"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>"><button class="btn danger sm" type="submit">Remover</button></form><?php endif; ?></td></tr><?php endforeach; ?>
      <?php if (!$c['ledger']): ?><tr><td colspan="5" class="muted">Nenhum repasse neste mês.</td></tr><?php endif; ?></tbody></table></div>
      <p class="small muted" style="margin-top:12px">Repasses ligados a uma venda só mudam editando a venda (admin), para nunca contar em duplicidade.</p></div>
  </div>

<?php elseif ($tab === 'receber'):
  $ar = Database::all("SELECT a.*, CONCAT_WS(' ', c.first_name, c.last_name) customer FROM accounts_receivable a LEFT JOIN customers c ON c.id = a.customer_id ORDER BY (a.paid_at IS NOT NULL), a.due_date IS NULL, a.due_date LIMIT 100");
  $openAr = (float) Database::value('SELECT COALESCE(SUM(amount),0) FROM accounts_receivable WHERE paid_at IS NULL');
  $lateAr = (float) Database::value('SELECT COALESCE(SUM(amount),0) FROM accounts_receivable WHERE paid_at IS NULL AND due_date < CURDATE()'); ?>
  <div class="fin-kpis"><div class="kpi"><span>A receber em aberto</span><strong class="ok"><?= money($openAr) ?></strong></div><div class="kpi"><span>Em atraso</span><strong class="bad"><?= money($lateAr) ?></strong></div></div>
  <div class="card caps"><h2>Contas a receber</h2>
    <form method="post" class="row c4" style="margin:12px 0"><?= App\Csrf::field() ?><input type="hidden" name="action" value="add_receivable"><input type="hidden" name="tab" value="receber">
      <input name="description" placeholder="DESCRIÇÃO" required style="grid-column:span 2"><input name="amount" inputmode="decimal" placeholder="VALOR" required><input name="due_date" type="date" aria-label="Vencimento"><button class="btn green sm" type="submit" style="grid-column:span 4">Adicionar</button></form>
    <?php fin_table($ar, 'accounts_receivable', true, 'receber'); ?></div>

<?php else: /* custos */ ?>
  <?php fin_period_bar('custos', $pkey, $cm, $range); $byM = App\CardFees::byMachine($range['from'], $range['to']); $machines = App\CardFees::machines(false); $rm = App\CardFees::rateMap(); ?>
  <div class="card"><h2>Taxas das máquinas no período</h2><p class="sub">O cliente paga o valor cheio; a máquina repassa menos. A diferença abaixo já é abatida do lucro no balanço da loja.</p>
    <div class="tiles"><?php foreach ($byM as $m): ?><div class="tile"><span><?= e($m['name']) ?></span><b><?= money((float) $m['fees']) ?></b><small><?= (int) $m['n'] ?> pagamento(s) · passou <?= money((float) $m['gross']) ?> · loja recebe <?= money((float) $m['gross'] - (float) $m['fees']) ?></small></div><?php endforeach; ?>
      <div class="tile"><span>Total de taxas (resultado)</span><b><?= money($s['card_fees']) ?></b><small>já descontado em Lucro estimado</small></div></div></div>
  <?php if (Auth::isAdmin()): ?>
  <form method="post" class="card stack"><?= App\Csrf::field() ?><input type="hidden" name="action" value="save_rates"><input type="hidden" name="tab" value="custos">
    <h2>Tabela de taxas por máquina</h2><p class="sub">Percentual cobrado por bandeira e parcela. Alterar vale só para vendas novas; as já feitas guardam a taxa da época. Deixe em branco o que não existir.</p>
    <?php foreach ($machines as $m): ?><details <?= $m === $machines[0] ? 'open' : '' ?> style="margin-bottom:10px"><summary><b><?= e($m['name']) ?></b></summary>
      <div class="row c2" style="margin:10px 0"><div><label class="f">Nome da máquina</label><input name="machine_name[<?= (int) $m['id'] ?>]" value="<?= e($m['name']) ?>"></div></div>
      <div style="overflow-x:auto"><table><thead><tr><th>Bandeira</th><th>Débito</th><?php for ($n = 1; $n <= 12; $n++): ?><th><?= $n ?>x</th><?php endfor; ?></tr></thead><tbody>
      <?php foreach (App\CardFees::BRANDS as $bk => $bl): ?><tr><td><?= e($bl) ?></td>
        <?php foreach (array_merge(['d'], range(1, 12)) as $c): $v = $rm[$m['id']][$bk][(string) $c] ?? null; ?><td><input style="width:64px" inputmode="decimal" name="rate[<?= (int) $m['id'] ?>][<?= $bk ?>][<?= $c ?>]" value="<?= $v === null ? '' : e(number_format($v, 2, ',', '')) ?>"></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></details><?php endforeach; ?>
    <button class="btn green" type="submit">Salvar tabela de taxas</button></form>
  <?php endif; ?>
  <div class="card caps" style="max-width:680px"><h2>Custos e taxas</h2><p class="sub">Parâmetros usados nas estimativas do financeiro e nos alertas do contador virtual.</p>
    <?php if (!Auth::isAdmin()): ?><p class="muted">Somente o administrador pode alterar.</p><?php endif; ?>
    <form method="post" class="stack"><?= App\Csrf::field() ?><input type="hidden" name="action" value="save_costs"><input type="hidden" name="tab" value="custos">
      <div class="row c2"><div><label class="f" for="tr">Imposto sobre vendas (%)</label><input id="tr" name="tax_rate" inputmode="decimal" value="<?= e(number_format((float) $cfg['tax_rate'], 2, ',', '')) ?>" <?= Auth::isAdmin() ? '' : 'disabled' ?>></div>
        <div><label class="f" for="cf">Taxa geral do cartão (%) — só vendas antigas sem máquina</label><input id="cf" name="card_fee_rate" inputmode="decimal" value="<?= e(number_format((float) $cfg['card_fee_rate'], 2, ',', '')) ?>" <?= Auth::isAdmin() ? '' : 'disabled' ?>></div></div>
      <div class="row c2"><div><label class="f" for="cn">Nome do credor</label><input id="cn" name="creditor_name" value="<?= e($cfg['creditor_name']) ?>" <?= Auth::isAdmin() ? '' : 'disabled' ?>></div>
        <div><label class="f" for="cg">Meta mensal do credor (R$)</label><input id="cg" name="creditor_monthly_goal" inputmode="decimal" value="<?= e(number_format((float) $cfg['creditor_monthly_goal'], 2, ',', '')) ?>" <?= Auth::isAdmin() ? '' : 'disabled' ?>></div></div>
      <p class="small muted">A folha dos vendedores vem do pagamento semanal cadastrado em Vendedores (proporcional aos dias do período). Taxa do cartão incide sobre pagamentos em cartão; imposto estimado = % sobre a receita (o lucro usa os impostos efetivamente pagos).</p>
      <?php if (Auth::isAdmin()): ?><button class="btn green" type="submit">Salvar</button><?php endif; ?></form></div>
<?php endif; ?>
