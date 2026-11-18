<?php
use App\Database;
use App\Loans;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $back = url('emprestimos', array_filter(['ver' => $_GET['ver'] ?? null, 'dir' => $_GET['dir'] ?? null]));
    try {
        $u = App\Auth::user();
        if ($action === 'loan_new') {
            $id = Loans::create($_POST, $u['username'] ?? null);
            flash('ok', "Emprestimo #$id registrado." . (!empty($_POST['product_id']) && (($_POST['direction'] ?? '') === 'out' || !empty($_POST['add_stock'])) ? ' Estoque atualizado.' : ''));
        } elseif ($action === 'loan_settle') {
            Loans::settle((int) $_POST['id'], $_POST, $u);
            flash('ok', 'Baixa registrada.' . (($_POST['kind'] ?? '') === 'pagamento' ? ' O valor foi lancado (venda de atacado ou conta paga no Financeiro).' : ''));
        } elseif ($action === 'loan_edit') {
            Loans::update((int) $_POST['id'], $_POST);
            flash('ok', 'Emprestimo atualizado.');
        } elseif ($action === 'loan_undo') {
            if (!App\Auth::isAdmin()) {
                throw new DomainException('Somente o administrador pode desfazer baixas.');
            }
            Loans::undoSettlement((int) $_POST['sid']);
            flash('ok', 'Baixa desfeita: o emprestimo voltou a ficar em aberto.');
        } elseif ($action === 'loan_delete') {
            if (!App\Auth::isAdmin()) {
                throw new DomainException('Somente o administrador pode apagar emprestimos.');
            }
            Loans::delete((int) $_POST['id']);
            flash('ok', 'Emprestimo apagado. Estoque, baixas e lancamentos gerados por ele foram desfeitos.');
        }
    } catch (DomainException $ex) {
        flash('err', $ex->getMessage());
        $_SESSION['loan_old'] = $action === 'loan_new' ? $_POST : null;
    }
    redirect($back);
}

$ver = in_array($_GET['ver'] ?? '', ['aberto', 'fechado', 'todos'], true) ? $_GET['ver'] : 'aberto';
$dir = in_array($_GET['dir'] ?? '', ['in', 'out'], true) ? $_GET['dir'] : '';
$rows = Loans::list($ver === 'todos' ? '' : $ver, $dir);
$tot = Loans::totals();
$old = $_SESSION['loan_old'] ?? [];
unset($_SESSION['loan_old']);
$o = fn ($k, $d = '') => e((string) ($old[$k] ?? $d));
$products = Database::all("SELECT id, name, stock, condition_type, category FROM products WHERE active = 1 AND category IN ('pneu','roda','parafuso') ORDER BY category, name");
$sett = [];
if ($rows) {
    foreach (Database::all('SELECT * FROM loan_settlements WHERE loan_id IN (' . implode(',', array_map(fn ($r) => (int) $r['id'], $rows)) . ') ORDER BY id') as $s) {
        $sett[(int) $s['loan_id']][] = $s;
    }
}
$condLabel = ['novo' => '', 'usado' => ' (USADO)', 'atacado' => ' (ATACADO)'];
?>
<section class="hero slim"><div><h1>Empréstimos com parceiros</h1><p>Pneus e rodas que a loja pegou emprestado ou emprestou para outras empresas: quantidade, prazo de devolução e acerto.</p></div></section>

<div class="kpis">
  <div class="kpi"><span>Nós devemos</span><strong class="bad"><?= $tot['owe'] ?></strong><small>unidade(s) pegas emprestado</small></div>
  <div class="kpi"><span>Nos devem</span><strong class="ok"><?= $tot['owed'] ?></strong><small>unidade(s) emprestadas</small></div>
  <div class="kpi"><span>Com devolução atrasada</span><strong class="<?= $tot['late'] ? 'bad' : '' ?>"><?= $tot['late'] ?></strong></div>
</div>

<div class="cols loan-cols">
  <form class="card" method="post" id="loan-form"><?= App\Csrf::field() ?><input type="hidden" name="action" value="loan_new">
    <h2>Novo empréstimo</h2>
    <div class="loan-dir" role="radiogroup" aria-label="Tipo do empréstimo">
      <label><input type="radio" name="direction" value="in" <?= ($old['direction'] ?? '') === 'in' ? 'checked' : '' ?> required><span><b>Pegamos emprestado</b><small>o parceiro nos passou</small></span></label>
      <label><input type="radio" name="direction" value="out" <?= ($old['direction'] ?? '') === 'out' ? 'checked' : '' ?>><span><b>Emprestamos</b><small>saiu da loja para o parceiro</small></span></label>
    </div>
    <div class="stack">
      <div class="row c2"><input name="partner" required placeholder="Empresa parceira *" value="<?= $o('partner') ?>"><input name="partner_phone" inputmode="tel" placeholder="Telefone do parceiro" value="<?= $o('partner_phone') ?>"></div>
      <label class="f" for="loan-product">Produto do estoque (opcional)</label>
      <select id="loan-product" name="product_id"><option value="">— Não está no estoque / descrever abaixo —</option>
        <?php $lastCat = ''; foreach ($products as $p): if ($p['category'] !== $lastCat): ?><?= $lastCat ? '</optgroup>' : '' ?><optgroup label="<?= e(ucfirst($p['category'])) ?>"><?php $lastCat = $p['category']; endif; ?>
          <option value="<?= (int) $p['id'] ?>" <?= (string) ($old['product_id'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name'] . ($condLabel[$p['condition_type']] ?? '')) ?> · estoque <?= (int) $p['stock'] ?></option>
        <?php endforeach; ?><?= $lastCat ? '</optgroup>' : '' ?></select>
      <input name="description" placeholder="Descrição: medida, marca (ex.: 175/70 R14 PIRELLI)" value="<?= $o('description') ?>">
      <div class="row c2"><input name="quantity" type="number" min="1" required placeholder="Quantidade *" value="<?= $o('quantity') ?>"><input name="unit_value" inputmode="decimal" placeholder="Valor combinado por unidade (R$)" value="<?= $o('unit_value') ?>"></div>
      <div class="row c2"><label class="birth"><span>Data</span><input type="date" name="loan_date" value="<?= $o('loan_date', date('Y-m-d')) ?>"></label><label class="birth"><span>Devolver até</span><input type="date" name="due_date" value="<?= $o('due_date') ?>"></label></div>
      <label class="chk" id="add-stock"><input type="checkbox" name="add_stock" value="1" <?= !empty($old['add_stock']) || !$old ? 'checked' : '' ?>> Colocar no nosso estoque para vender no PDV (só para "pegamos emprestado" com produto escolhido)</label>
      <input name="notes" placeholder="Observações" value="<?= $o('notes') ?>">
      <p class="small muted" style="margin:0"><b>Emprestamos</b> com produto escolhido: sai do estoque agora e volta na devolução. Se o parceiro <b>pagar</b>, vira uma venda de atacado (entra na receita). <b>Pegamos emprestado</b>: se pagarmos, vira uma conta paga de mercadoria no Financeiro.</p>
      <button class="btn green block" type="submit">Registrar empréstimo</button>
    </div>
  </form>

  <div class="card">
    <div class="actions" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <h2>Empréstimos</h2>
      <nav class="fin-tabs">
        <?php foreach (['aberto' => 'Em aberto', 'fechado' => 'Fechados', 'todos' => 'Todos'] as $k => $l): ?><a class="pill <?= $ver === $k ? 'on' : '' ?>" href="<?= e(url('emprestimos', array_filter(['ver' => $k, 'dir' => $dir]))) ?>"><?= $l ?></a><?php endforeach; ?>
        <?php foreach (['' => 'Ambos', 'in' => 'Pegamos', 'out' => 'Emprestamos'] as $k => $l): ?><a class="pill <?= $dir === $k ? 'on' : '' ?>" href="<?= e(url('emprestimos', array_filter(['ver' => $ver, 'dir' => $k]))) ?>"><?= $l ?></a><?php endforeach; ?>
      </nav>
    </div>
    <div class="loan-list">
    <?php foreach ($rows as $r): $late = $r['status'] === 'aberto' && $r['due_date'] && $r['due_date'] < date('Y-m-d'); $isIn = $r['direction'] === 'in'; ?>
      <article class="loan <?= $isIn ? 'in' : 'out' ?> <?= $r['status'] ?>">
        <header>
          <span class="tag <?= $isIn ? 'warn' : 'blue' ?>"><?= $isIn ? 'PEGAMOS DE' : 'EMPRESTAMOS PARA' ?></span>
          <b><?= e($r['partner']) ?></b>
          <?php if ($r['partner_phone']): ?><a class="small" href="<?= e(whatsapp_link($r['partner_phone'])) ?>" target="_blank" rel="noopener"><?= e($r['partner_phone']) ?></a><?php endif; ?>
          <span class="small muted">#<?= (int) $r['id'] ?> · <?= e(fmt_date($r['loan_date'])) ?></span>
          <?= $r['status'] === 'fechado' ? '<span class="tag green">fechado</span>' : ($late ? '<span class="tag red">atrasado</span>' : '') ?>
        </header>
        <p><b><?= (int) $r['quantity'] ?>x</b> <?= e($r['description']) ?><?= $r['condition_type'] === 'atacado' ? ' <span class="dot orange-s"></span>' : '' ?>
          <?= $r['unit_value'] !== null ? ' · ' . money($r['unit_value']) . '/un.' : '' ?>
          <?= $r['due_date'] ? '<br><span class="small ' . ($late ? 'bad' : 'muted') . '">devolver até ' . e(fmt_date($r['due_date'])) . '</span>' : '' ?>
          <?= $r['stock_moved'] ? '<br><span class="small muted">' . ($isIn ? 'entrou no nosso estoque' : 'saiu do nosso estoque') . '</span>' : '' ?>
          <?= $r['notes'] ? '<br><span class="small">' . e($r['notes']) . '</span>' : '' ?></p>
        <?php if ($r['status'] === 'aberto'): ?><p class="small"><b>Falta acertar: <?= (int) $r['pending'] ?></b> de <?= (int) $r['quantity'] ?></p><?php endif; ?>
        <?php foreach ($sett[(int) $r['id']] ?? [] as $s): ?>
          <div class="small muted loan-ev">✓ <?= e(fmt_date($s['settle_date'])) ?>: <?= $s['kind'] === 'devolucao' ? ((int) $s['quantity'] . ' devolvido(s)') : ((int) $s['quantity'] . ' pago(s), ' . money($s['amount']) . ($s['sale_id'] ? ' · <a href="' . e(url('vendas', ['id' => $s['sale_id']])) . '">venda #' . (int) $s['sale_id'] . '</a>' : ' · conta paga no Financeiro')) ?>
            <?php if (App\Auth::isAdmin()): ?><form method="post" style="display:inline" onsubmit="return confirm('Desfazer esta baixa?<?= $s['sale_id'] ? ' A venda gerada por ela sera apagada.' : '' ?>')"><?= App\Csrf::field() ?><input type="hidden" name="action" value="loan_undo"><input type="hidden" name="sid" value="<?= (int) $s['id'] ?>"><button class="linklike danger-t" type="submit">desfazer</button></form><?php endif; ?></div>
        <?php endforeach; ?>
        <?php if ($r['status'] === 'aberto'): ?>
        <details class="loan-settle"><summary class="btn sm">Dar baixa (devolução ou pagamento)</summary>
          <form method="post" class="stack" style="margin-top:10px"><?= App\Csrf::field() ?><input type="hidden" name="action" value="loan_settle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <div class="row c2">
              <select name="kind" class="settle-kind"><option value="devolucao"><?= $isIn ? 'Devolvemos o pneu' : 'O parceiro devolveu' ?></option><option value="pagamento"><?= $isIn ? 'Pagamos em dinheiro' : 'O parceiro pagou' ?></option></select>
              <input type="number" name="quantity" min="1" max="<?= (int) $r['pending'] ?>" value="<?= (int) $r['pending'] ?>" aria-label="Quantidade">
            </div>
            <div class="row c2 pay-only" hidden>
              <input name="amount" class="settle-amount" data-unit="<?= $r['unit_value'] !== null ? e((string) $r['unit_value']) : '' ?>" inputmode="decimal" placeholder="Valor total pago (R$)" value="<?= $r['unit_value'] !== null ? e(number_format((float) $r['unit_value'] * (int) $r['pending'], 2, ',', '')) : '' ?>">
              <?php if (!$isIn): ?><select name="method"><option value="pix">PIX</option><option value="dinheiro">Dinheiro</option><option value="prazo">A receber</option></select><?php endif; ?>
            </div>
            <div class="row c2"><label class="birth"><span>Data</span><input type="date" name="settle_date" value="<?= date('Y-m-d') ?>"></label><input name="notes" placeholder="Observação"></div>
            <button class="btn green" type="submit">Confirmar baixa</button>
          </form>
        </details>
        <?php endif; ?>
        <div class="loan-tools">
          <details class="loan-edit"><summary class="btn ghost sm">Editar</summary>
            <form method="post" class="stack" style="margin-top:10px"><?= App\Csrf::field() ?><input type="hidden" name="action" value="loan_edit"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <div class="row c2"><input name="partner" required value="<?= e($r['partner']) ?>" aria-label="Empresa parceira"><input name="partner_phone" value="<?= e((string) $r['partner_phone']) ?>" placeholder="Telefone" aria-label="Telefone"></div>
              <input name="description" value="<?= e($r['description']) ?>" aria-label="Descricao">
              <div class="row c2"><label class="birth"><span>Quantidade</span><input type="number" name="quantity" min="<?= max(1, (int) $r['settled']) ?>" value="<?= (int) $r['quantity'] ?>"></label><label class="birth"><span>Valor por unidade</span><input name="unit_value" inputmode="decimal" value="<?= $r['unit_value'] !== null ? e(number_format((float) $r['unit_value'], 2, ',', '')) : '' ?>"></label></div>
              <div class="row c2"><label class="birth"><span>Data</span><input type="date" name="loan_date" value="<?= e($r['loan_date']) ?>"></label><label class="birth"><span>Devolver até</span><input type="date" name="due_date" value="<?= e((string) $r['due_date']) ?>"></label></div>
              <input name="notes" value="<?= e((string) $r['notes']) ?>" placeholder="Observações" aria-label="Observacoes">
              <?php if ($r['stock_moved']): ?><p class="small muted" style="margin:0">Mudou a quantidade? O estoque é ajustado junto.</p><?php endif; ?>
              <button class="btn" type="submit">Salvar alterações</button>
            </form></details>
          <?php if (App\Auth::isAdmin()): ?>
          <form method="post" onsubmit="return confirm('Apagar o emprestimo #<?= (int) $r['id'] ?>? O estoque volta como estava<?= (int) $r['settled'] ? ' e as baixas (devolucoes, vendas e contas geradas) serao desfeitas' : '' ?>. Isso nao pode ser desfeito.')"><?= App\Csrf::field() ?><input type="hidden" name="action" value="loan_delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn danger sm" type="submit">Apagar</button></form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$rows): ?><p class="muted">Nenhum empréstimo <?= $ver === 'aberto' ? 'em aberto' : 'neste filtro' ?>.</p><?php endif; ?>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('.settle-kind').forEach(function (s) {
  var f = s.form.querySelector('.pay-only');
  var sync = function () { f.hidden = s.value !== 'pagamento'; f.querySelector('input').required = s.value === 'pagamento'; };
  s.addEventListener('change', sync); sync();
  var q = s.form.querySelector('input[name=quantity]'), a = s.form.querySelector('.settle-amount');
  if (a && a.dataset.unit) q.addEventListener('input', function () { a.value = ((parseInt(q.value, 10) || 0) * parseFloat(a.dataset.unit)).toFixed(2).replace('.', ','); });
});
(function () {
  var f = document.getElementById('loan-form'), box = document.getElementById('add-stock');
  var sync = function () { var d = f.querySelector('input[name=direction]:checked'); box.hidden = !(d && d.value === 'in' && f.product_id.value); };
  f.addEventListener('change', sync); sync();
})();
</script>
