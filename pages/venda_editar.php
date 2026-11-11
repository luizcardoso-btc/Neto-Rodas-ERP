<?php
/** Edicao completa de venda (somente admin). Incluida por vendas.php com ?edit=ID. */
use App\Auth;
use App\Database;
use App\SaleService;

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div class="card"><h2>Acesso restrito</h2><p>Somente o administrador pode editar vendas.</p><p><a href="' . e(url('vendas')) . '">Voltar</a></p></div>';
    return;
}

$saleId = (int) ($_GET['edit'] ?? 0);
$sale = Database::one('SELECT * FROM sales WHERE id = ?', [$saleId]);
if (!$sale) {
    flash('err', 'Venda nao encontrada.');
    redirect(url('vendas'));
}

$posted = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_sale') {
    $posted = $_POST;
    $rows = fn ($k) => array_values(array_filter((array) ($_POST[$k] ?? []), 'is_array'));
    try {
        SaleService::update($saleId, [
            'created_at' => (string) ($_POST['created_at'] ?? ''),
            'customer_id' => (int) ($_POST['customer_id'] ?? 0) ?: null,
            'seller_id' => (int) ($_POST['seller_id'] ?? 0) ?: null,
            'notes' => upper((string) ($_POST['notes'] ?? '')),
            'wants_nfe' => !empty($_POST['wants_nfe']),
            'sale_type' => ($_POST['sale_type'] ?? '') === 'atacado' ? 'atacado' : 'varejo',
            'payment_status' => (string) ($_POST['payment_status'] ?? 'pending'),
            'due_date' => (string) ($_POST['due_date'] ?? ''),
            'items' => array_map(fn ($r) => [
                'id' => (int) ($r['id'] ?? 0),
                'product_id' => (int) ($r['product_id'] ?? 0) ?: null,
                'description' => upper((string) ($r['description'] ?? '')),
                'quantity' => (int) ($r['quantity'] ?? 0),
                'unit_price' => parse_money($r['unit_price'] ?? 0),
                'unit_cost' => ($r['unit_cost'] ?? '') === '' ? null : parse_money($r['unit_cost']),
            ], $rows('items')),
            'payments' => array_map(fn ($r) => [
                'id' => (int) ($r['id'] ?? 0),
                'method' => (string) ($r['method'] ?? ''),
                'amount' => parse_money($r['amount'] ?? 0),
                'note' => upper((string) ($r['note'] ?? '')),
                'trade_qty' => (int) ($r['trade_qty'] ?? 1),
                'trade_category' => (string) ($r['trade_category'] ?? 'pneu'),
                'to_creditor' => !empty($r['to_creditor']),
                'machine_id' => (int) ($r['machine_id'] ?? 0),
                'card_brand' => (string) ($r['card_brand'] ?? 'vm'),
                'card_inst' => (string) ($r['card_inst'] ?? ''),
            ], $rows('payments')),
        ], Auth::user());
        flash('ok', "Venda #$saleId atualizada.");
        redirect(url('vendas', ['id' => $saleId]));
    } catch (DomainException $e) {
        flash('err', $e->getMessage());
    }
}

$customers = Database::all("SELECT id, CONCAT_WS(' ', first_name, last_name) name, phone FROM customers ORDER BY first_name, last_name");
$sellers = Database::all('SELECT id, name FROM sellers ORDER BY name');
$products = Database::all('SELECT id, name, stock, condition_type FROM products WHERE active = 1 OR id IN (SELECT product_id FROM sale_items WHERE sale_id = ' . (int) $saleId . ') ORDER BY name');
$items = Database::all('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id', [$saleId]);
$pays = Database::all('SELECT * FROM sale_payments WHERE sale_id = ? ORDER BY id', [$saleId]);
$due = Database::value('SELECT due_date FROM accounts_receivable WHERE sale_id = ? ORDER BY id LIMIT 1', [$saleId]);
$audit = Database::all('SELECT * FROM sale_audit WHERE sale_id = ? ORDER BY id DESC LIMIT 10', [$saleId]);

$num = fn ($v) => number_format((float) $v, 2, ',', '');
$pv = fn (string $k, $d = '') => $posted ? (string) ($posted[$k] ?? '') : (string) $d;
$itemRows = $posted ? array_values(array_filter((array) ($posted['items'] ?? []), 'is_array')) : array_map(fn ($i) => [
    'id' => $i['id'], 'product_id' => $i['product_id'], 'description' => $i['description'], 'quantity' => $i['quantity'],
    'unit_price' => $num($i['unit_price']), 'unit_cost' => $num($i['unit_cost']),
], $items);
$payRows = $posted ? array_values(array_filter((array) ($posted['payments'] ?? []), 'is_array')) : array_map(fn ($p) => [
    'id' => $p['id'], 'method' => $p['method'], 'amount' => $num($p['amount']), 'note' => $p['note'], 'trade_qty' => $p['trade_qty'], 'trade_category' => $p['trade_category'] ?: 'pneu', 'to_creditor' => (int) $p['to_creditor'],
    'machine_id' => $p['machine_id'], 'card_brand' => $p['card_brand'] ?: 'vm', 'card_inst' => $p['card_kind'] === 'debito' ? 'd' : (string) ($p['card_installments'] ?? ''),
], $pays);
$status = $pv('payment_status', $sale['payment_status']);
$nfe = $posted ? !empty($posted['wants_nfe']) : (bool) $sale['wants_nfe'];
?>
<section class="hero slim"><div><h1>Editar venda #<?= (int) $saleId ?></h1><p>Controle do administrador: altere itens, valores, troca, taxas de servico, cliente e data. Estoque e contas a receber sao reajustados automaticamente.</p></div></section>
<form method="post" class="stack caps" id="edit-form"><?= App\Csrf::field() ?><input type="hidden" name="action" value="edit_sale">
  <div class="card"><h2>Dados da venda</h2>
    <div class="row c2" style="margin-top:12px">
      <div><label class="f" for="created_at">Data e hora</label><input id="created_at" type="datetime-local" name="created_at" value="<?= e($pv('created_at', date('Y-m-d\TH:i', strtotime($sale['created_at'])))) ?>"></div>
      <div><label class="f" for="payment_status">Situacao do pagamento</label><select id="payment_status" name="payment_status"><option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Pago</option><option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pendente (a prazo)</option></select></div>
      <div><label class="f" for="customer_id">Cliente</label><select id="customer_id" name="customer_id"><option value="">Sem cliente</option>
        <?php $cid = (int) $pv('customer_id', $sale['customer_id']); foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cid === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name'] . ($c['phone'] ? ' - ' . $c['phone'] : '')) ?></option><?php endforeach; ?></select></div>
      <div><label class="f" for="seller_id">Vendedor</label><select id="seller_id" name="seller_id"><option value="">Sem vendedor</option>
        <?php $sid = (int) $pv('seller_id', $sale['seller_id']); foreach ($sellers as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $sid === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
      <div><label class="f" for="due_date">Vencimento (parte a prazo)</label><input id="due_date" type="date" name="due_date" value="<?= e($pv('due_date', (string) $due)) ?>"></div>
      <div><label class="f" for="sale_type">Tipo da venda</label><select id="sale_type" name="sale_type"><option value="varejo" <?= $pv('sale_type', $sale['sale_type'] ?? 'varejo') !== 'atacado' ? 'selected' : '' ?>>Varejo</option><option value="atacado" <?= $pv('sale_type', $sale['sale_type'] ?? 'varejo') === 'atacado' ? 'selected' : '' ?>>Atacado</option></select></div>
      <div><label class="f" for="notes">Observacoes</label><input id="notes" name="notes" maxlength="255" value="<?= e($pv('notes', $sale['notes'] ?? '')) ?>"></div>
    </div>
    <label style="display:flex;gap:10px;align-items:center;margin-top:10px"><input type="checkbox" name="wants_nfe" value="1" style="width:auto" <?= $nfe ? 'checked' : '' ?>> NF-e (exige CPF/CNPJ e endereco no cadastro do cliente)</label>
  </div>

  <div class="card">
    <div class="actions" style="justify-content:space-between;align-items:center"><div><h2>Itens e servicos</h2><p class="sub" style="margin:0">Deixe o produto em "Servico / taxa" para lancar taxa de servico (nao mexe no estoque).</p></div>
      <button class="btn ghost sm" type="button" id="add-item">+ Item / servico</button></div>
    <div id="items" style="margin-top:12px"></div>
    <div class="totalbar"><span class="muted">Total</span><b id="total">R$ 0,00</b></div>
  </div>

  <div class="card">
    <div class="actions" style="justify-content:space-between;align-items:center"><div><h2>Pagamentos</h2><p class="sub" style="margin:0"><span class="dot"></span>Troca = pneu usado: o valor, a descricao (medida) e a quantidade atualizam o estoque de usados.</p></div>
      <button class="btn ghost sm" type="button" id="add-pay">+ Pagamento</button></div>
    <div id="pays" style="margin-top:12px"></div>
    <div class="sumbar"><div>Total<b id="s-total">R$ 0,00</b></div><div>Pago<b id="s-paid">R$ 0,00</b></div><div id="s-box">Falta<b id="s-diff">R$ 0,00</b></div></div>
  </div>

  <div class="actions"><button class="btn green" type="submit" style="font-size:15px;padding:12px 22px">Salvar alteracoes</button><a class="btn ghost" href="<?= e(url('vendas', ['id' => $saleId])) ?>" style="padding:12px 22px">Cancelar</a></div>
</form>
<?php if ($audit): ?><div class="card"><h2>Historico de edicoes</h2><table><tbody>
  <?php foreach ($audit as $a): ?><tr><td><?= e(fmt_date($a['created_at'], true)) ?></td><td><?= e($a['username'] ?: '-') ?></td><td><?= e($a['summary']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<script src="<?= e(asset('cardfees.js')) ?>"></script>
<script>
(function () {
  const PRODUCTS = <?= json_encode(array_map(fn ($p) => ['id' => (int) $p['id'], 'label' => $p['name'] . ($p['condition_type'] === 'usado' ? ' (usado)' : '') . ' - estoque ' . (int) $p['stock']], $products), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  const ITEMS = <?= json_encode($itemRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  const PAYS = <?= json_encode($payRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  const CARD = <?= json_encode(['machines' => App\CardFees::machines(), 'rates' => App\CardFees::rateMap() ?: new stdClass()], JSON_HEX_TAG) ?>;
  const METHODS = ['dinheiro', 'pix', 'cartao', 'prazo', 'troca'];
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const cents = (v) => { v = String(v || '').trim().replace(/[^\d,.\-]/g, ''); if (v.includes(',')) v = v.replace(/\./g, '').replace(',', '.'); const n = parseFloat(v); return isNaN(n) ? 0 : Math.round(n * 100); };
  const brl = (c) => (c / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const el = (h) => { const t = document.createElement('template'); t.innerHTML = h.trim(); return t.content.firstChild; };
  const itemsBox = document.getElementById('items'), paysBox = document.getElementById('pays');
  let n = 0;
  function addItem(d = {}) {
    const i = n++;
    const opts = '<option value="">Servico / taxa (sem produto)</option>' + PRODUCTS.map((p) => `<option value="${p.id}" ${String(d.product_id) === String(p.id) ? 'selected' : ''}>${esc(p.label)}</option>`).join('');
    const line = el(`<div class="item-line"><input type="hidden" name="items[${i}][id]" value="${esc(d.id)}">
      <div class="grid"><select name="items[${i}][product_id]" aria-label="Produto">${opts}</select>
        <input name="items[${i}][description]" placeholder="Descricao (ex.: Taxa de servico)" value="${esc(d.description)}">
        <input name="items[${i}][quantity]" type="number" min="1" placeholder="Qtd." value="${esc(d.quantity || 1)}">
        <input name="items[${i}][unit_price]" inputmode="decimal" placeholder="Preco unitario" value="${esc(d.unit_price)}">
        <input name="items[${i}][unit_cost]" inputmode="decimal" placeholder="Custo unit. (opcional)" value="${esc(d.unit_cost)}"></div>
      <div class="line-foot"><span class="small muted"></span><button type="button" class="rm" style="color:var(--danger)">Remover</button></div></div>`);
    line.querySelector('.rm').onclick = () => { line.remove(); calc(); };
    line.addEventListener('input', calc);
    itemsBox.appendChild(line);
  }
  function addPay(d = {}) {
    const i = n++;
    const line = el(`<div class="pay-line"><input type="hidden" name="payments[${i}][id]" value="${esc(d.id)}">
      <div class="grid"><select name="payments[${i}][method]">${METHODS.map((m) => `<option value="${m}" ${d.method === m ? 'selected' : ''}>${m}</option>`).join('')}</select>
        <input name="payments[${i}][amount]" inputmode="decimal" placeholder="Valor" value="${esc(d.amount)}">
        <input name="payments[${i}][note]" placeholder="Obs. (na troca: medida do pneu, ex.: 175/65 R14)" value="${esc(d.note)}">
        <select name="payments[${i}][trade_category]" class="tc"><option value="pneu" ${d.trade_category !== 'roda' ? 'selected' : ''}>Pneu · usado</option><option value="roda" ${d.trade_category === 'roda' ? 'selected' : ''}>Roda · usada</option></select>
        <input name="payments[${i}][trade_qty]" type="number" min="1" placeholder="Qtd. (troca)" value="${esc(d.trade_qty || 1)}"></div>
      ${CardUI.html(i, d, CARD)}
      <label class="small" style="display:flex;gap:6px;align-items:center"><input type="checkbox" class="cred" name="payments[${i}][to_creditor]" value="1" style="width:auto" ${d.to_creditor ? 'checked' : ''}> Enviado ao credor</label>
      <div class="line-foot"><span class="small trade-hint"></span><button type="button" class="rm" style="color:var(--danger)">Remover</button></div></div>`);
    const sel = line.querySelector('select'), hint = line.querySelector('.trade-hint'), qty = line.querySelector('[name$="[trade_qty]"]'), tc = line.querySelector('.tc');
    const cardUpd = CardUI.wire(line, CARD, () => cents(line.querySelector('[name$="[amount]"]').value));
    line.addEventListener('input', () => cardUpd(sel.value === 'cartao'));
    const sync = () => { cardUpd(sel.value === 'cartao'); const t = sel.value === 'troca'; line.classList.toggle('trade-line', t); qty.hidden = !t; tc.hidden = !t; hint.innerHTML = t ? '<span class="dot"></span>Usado: entra na categoria e no estoque de usados' : ''; };
    sel.onchange = () => { sync(); calc(); }; sync();
    line.querySelector('.rm').onclick = () => { line.remove(); calc(); };
    line.addEventListener('input', calc);
    paysBox.appendChild(line);
  }
  function calc() {
    let total = 0, paid = 0;
    itemsBox.querySelectorAll('.item-line').forEach((l) => { total += cents(l.querySelector('[name$="[unit_price]"]').value) * (parseInt(l.querySelector('[name$="[quantity]"]').value) || 0); });
    paysBox.querySelectorAll('.pay-line').forEach((l) => { paid += cents(l.querySelector('[name$="[amount]"]').value); });
    document.getElementById('total').textContent = brl(total); document.getElementById('s-total').textContent = brl(total);
    document.getElementById('s-paid').textContent = brl(paid); document.getElementById('s-diff').textContent = brl(total - paid);
    document.getElementById('s-box').className = total === paid ? 'ok' : 'bad';
  }
  document.getElementById('add-item').onclick = () => addItem();
  document.getElementById('add-pay').onclick = () => addPay();
  (ITEMS.length ? ITEMS : [{}]).forEach(addItem);
  (PAYS.length ? PAYS : [{}]).forEach(addPay);
  calc();
})();
</script>
