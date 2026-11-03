<?php
/** Orcamentos: lista, criar/editar, visualizar (imprimir, PDF, WhatsApp) e dados da loja (admin). */
use App\Auth;
use App\Database;
use App\Quote;
use App\Settings;

if (isset($_GET['lookup'])) {
    App\Lookup::respond();
}

// ---------- POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_quote') {
        $qid = (int) ($_POST['id'] ?? 0) ?: null;
        try {
            $id = Quote::save($qid, $_POST, Auth::user()['username'] ?? null);
            flash('ok', 'Orcamento #' . $id . ' salvo.');
            redirect(url('orcamento', ['view' => $id]));
        } catch (DomainException $e) {
            flash('err', $e->getMessage());
            $_GET[$qid ? 'edit' : 'new'] = $qid ?: 1;
            $formData = $_POST;
        }
    } elseif ($action === 'approve_quote') {
        $qid = (int) ($_POST['id'] ?? 0);
        $qq = Quote::find($qid);
        if (!$qq) {
            flash('err', 'Orcamento nao encontrado.');
            redirect(url('orcamento'));
        }
        if ($qq['sale_id']) {
            flash('err', 'Este orcamento ja virou a venda #' . (int) $qq['sale_id'] . '.');
            redirect(url('orcamento', ['view' => $qid]));
        }
        Database::run("UPDATE quotes SET status = 'aprovado', approved_at = COALESCE(approved_at, NOW()) WHERE id = ?", [$qid]);
        flash('ok', 'Orcamento #' . Quote::number($qq) . ' aprovado. Confira os dados e finalize a venda.');
        redirect(url('pdv', ['quote' => $qid]));
    } elseif ($action === 'delete_quote' && Auth::isAdmin()) {
        Database::run('DELETE FROM quotes WHERE id = ?', [(int) $_POST['id']]);
        flash('ok', 'Orcamento excluido.');
        redirect(url('orcamento'));
    } elseif ($action === 'save_settings' && Auth::isAdmin()) {
        Settings::save($_POST);
        flash('ok', 'Dados da loja atualizados.');
        redirect(url('orcamento'));
    }
}

$sellers = Database::all('SELECT id, name FROM sellers WHERE active = 1 ORDER BY name');

// ---------- Dados da loja ----------
if (isset($_GET['settings']) && Auth::isAdmin()) {
    $st = Settings::all();
    ?>
<section class="hero slim"><div><h1>Dados da loja</h1><p>Aparecem no cabecalho e no rodape de todos os orcamentos em PDF.</p></div></section>
<form class="card caps stack" method="post" style="max-width:640px"><?= App\Csrf::field() ?><input type="hidden" name="action" value="save_settings">
  <div><label class="f" for="store_name">Nome da loja</label><input id="store_name" name="store_name" required value="<?= e($st['store_name']) ?>"></div>
  <div class="row c2"><div><label class="f" for="store_doc">CNPJ / CPF</label><input id="store_doc" name="store_doc" value="<?= e($st['store_doc']) ?>"></div>
    <div><label class="f" for="store_phone">Telefone / WhatsApp</label><input id="store_phone" name="store_phone" value="<?= e($st['store_phone']) ?>"></div></div>
  <div><label class="f" for="store_address">Endereco</label><input id="store_address" name="store_address" value="<?= e($st['store_address']) ?>"></div>
  <div><label class="f" for="quote_validity_days">Validade padrao (dias)</label><input id="quote_validity_days" name="quote_validity_days" type="number" min="1" max="365" value="<?= e($st['quote_validity_days']) ?>"></div>
  <div><label class="f" for="quote_terms">Texto do rodape (condicoes)</label><textarea id="quote_terms" name="quote_terms"><?= e($st['quote_terms']) ?></textarea></div>
  <div class="actions"><button class="btn green" type="submit">Salvar</button><a class="btn ghost" href="<?= e(url('orcamento')) ?>">Cancelar</a></div>
</form>
    <?php
    return;
}

// ---------- Visualizar ----------
if (!empty($_GET['view'])) {
    $q = Quote::find((int) $_GET['view']);
    if ($q) {
        $st = Settings::all();
        $wa = Quote::whatsappLink($q);
        $pdfUrl = url('orcamento', ['pdf' => $q['token']]);
        ?>
<section class="hero slim no-print"><div><h1>Orcamento #<?= e(Quote::number($q)) ?></h1><p><?= e($q['customer_name']) ?> · <?= money($q['total']) ?> · valido ate <?= e(fmt_date($q['valid_until'])) ?></p></div></section>
<div class="card no-print">
  <?php if ($q['sale_id']): ?>
    <div class="flash ok" style="margin:0 0 12px">Orcamento aprovado e vendido: <b>venda #<?= (int) $q['sale_id'] ?></b>. Edicao da venda: somente administrador.</div>
  <?php elseif ($q['status'] === 'aprovado'): ?>
    <div class="flash ok" style="margin:0 0 12px">Cliente aprovou este orcamento<?= $q['approved_at'] ? ' em ' . e(fmt_date($q['approved_at'], true)) : '' ?>. Falta finalizar a venda no PDV.</div>
  <?php endif; ?>
  <div class="actions" style="gap:10px;flex-wrap:wrap">
  <?php if ($q['sale_id']): ?>
    <?php if (Auth::isAdmin()): ?><a class="btn green" href="<?= e(url('vendas', ['edit' => $q['sale_id']])) ?>">Editar venda #<?= (int) $q['sale_id'] ?></a><?php endif; ?>
    <a class="btn ghost" href="<?= e(url('vendas', ['id' => $q['sale_id']])) ?>">Ver venda #<?= (int) $q['sale_id'] ?></a>
  <?php else: ?>
    <form method="post" style="display:inline"><?= App\Csrf::field() ?><input type="hidden" name="action" value="approve_quote"><input type="hidden" name="id" value="<?= (int) $q['id'] ?>"><button class="btn green" type="submit"><?= $q['status'] === 'aprovado' ? 'Levar ao PDV' : 'Cliente aprovou: levar ao PDV' ?></button></form>
  <?php endif; ?>
  <a class="btn" href="<?= e($wa) ?>" target="_blank" rel="noopener" id="wa-link">Enviar PDF por WhatsApp</a>
  <a class="btn ghost" href="<?= e($pdfUrl) ?>" target="_blank" rel="noopener">Abrir / baixar PDF</a>
  <button class="btn ghost" type="button" onclick="window.print()">Imprimir</button>
  <a class="btn ghost" href="<?= e(url('orcamento', ['edit' => $q['id']])) ?>">Editar orcamento</a>
  <a class="btn ghost" href="<?= e(url('orcamento')) ?>">Voltar</a>
  <?php if (Auth::isAdmin() && !$q['sale_id']): ?><form method="post" onsubmit="return confirm('Excluir este orcamento?')" style="display:inline"><?= App\Csrf::field() ?><input type="hidden" name="action" value="delete_quote"><input type="hidden" name="id" value="<?= (int) $q['id'] ?>"><button class="btn danger" type="submit">Excluir</button></form><?php endif; ?>
</div>
<p class="small muted" style="margin:10px 0 0">No celular, "Enviar PDF por WhatsApp" abre o compartilhamento ja com o PDF anexado e o texto pronto. No computador, o PDF e baixado e o WhatsApp abre com o texto: e so anexar o arquivo baixado.</p></div>

<div class="card quote-sheet">
  <div class="quote-head"><div><img src="<?= e(asset('logo.webp')) ?>" alt="<?= e($st['store_name']) ?>" style="height:84px;width:auto;display:block;margin:-6px 0 4px">
      <div class="small"><?= e(trim(($st['store_doc'] !== '' ? 'CNPJ/CPF ' . $st['store_doc'] . ' · ' : '') . $st['store_phone'], ' ·')) ?></div><div class="small"><?= e($st['store_address']) ?></div></div>
    <div style="text-align:right"><div class="qs-tag">ORCAMENTO</div><div class="qs-num">N <?= e(Quote::number($q)) ?></div><div class="small">Emitido em <?= e(fmt_date($q['created_at'])) ?></div></div></div>
  <div class="quote-cust"><b><?= e($q['customer_name']) ?></b>
    <div class="small"><?= e(implode(' · ', array_filter([$q['customer_phone'], $q['customer_tax_id'] ? 'CPF/CNPJ ' . $q['customer_tax_id'] : '', $q['customer_car'], $q['customer_email']]))) ?></div></div>
  <table><thead><tr><th>Categoria</th><th>Descricao</th><th class="num">Qtd.</th><th class="num">Unitario</th><th class="num">Subtotal</th></tr></thead><tbody>
    <?php foreach ($q['items'] as $i): ?><tr><td><span class="tag"><?= e(Quote::CATEGORIES[$i['category']] ?? $i['category']) ?></span></td><td><?= e($i['description']) ?></td><td class="num"><?= (int) $i['quantity'] ?></td><td class="num"><?= money($i['unit_price']) ?></td><td class="num"><b><?= money($i['unit_price'] * $i['quantity']) ?></b></td></tr><?php endforeach; ?></tbody></table>
  <div class="quote-tot"><div>Subtotal <b><?= money($q['subtotal']) ?></b></div>
    <?php if ((float) $q['discount'] > 0): ?><div>Desconto <b style="color:var(--danger)">- <?= money($q['discount']) ?></b></div><?php endif; ?>
    <div class="grand">TOTAL <b><?= money($q['total']) ?></b></div></div>
  <p class="small"><b>Validade:</b> ate <?= e(fmt_date($q['valid_until'])) ?><?php if (Quote::paymentLine($q) !== ''): ?> · <b>Pagamento:</b> <?= e(Quote::paymentLine($q)) ?><?php endif; ?><?php if ($q['seller']): ?> · <b>Vendedor:</b> <?= e($q['seller']) ?><?php endif; ?></p>
  <?php if ($q['notes']): ?><p class="small"><b>Observacoes:</b> <?= e($q['notes']) ?></p><?php endif; ?>
  <p class="small muted" style="text-align:center;margin-top:18px"><?= e($st['quote_terms']) ?></p>
</div>
<script>
(function () {
  const link = document.getElementById('wa-link');
  const text = <?= json_encode(Quote::whatsappText($q), JSON_UNESCAPED_UNICODE) ?>;
  const fname = 'ORCAMENTO-<?= e(Quote::number($q)) ?>.pdf';
  link.addEventListener('click', async (ev) => {
    ev.preventDefault();
    const orig = link.textContent; link.textContent = 'Preparando PDF...';
    try {
      const blob = await (await fetch(<?= json_encode($pdfUrl) ?>, { credentials: 'same-origin' })).blob();
      const file = new File([blob], fname, { type: 'application/pdf' });
      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        try { await navigator.share({ files: [file], text }); link.textContent = orig; return; } catch (e) { if (e && e.name === 'AbortError') { link.textContent = orig; return; } }
      }
      // Sem compartilhamento de arquivo neste navegador: baixa o PDF e abre o WhatsApp so com o texto (sem link do sistema).
      const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = fname; document.body.appendChild(a); a.click(); a.remove();
      window.open(link.href, '_blank', 'noopener');
    } catch (e) { window.open(link.href, '_blank', 'noopener'); }
    link.textContent = orig;
  });
})();
</script>
        <?php
        return;
    }
    flash('err', 'Orcamento nao encontrado.');
}

// ---------- Criar / editar ----------
if (isset($_GET['new']) || isset($_GET['edit'])) {
    $editId = (int) ($_GET['edit'] ?? 0);
    $q = $editId ? Quote::find($editId) : null;
    if ($editId && !$q) {
        flash('err', 'Orcamento nao encontrado.');
        redirect(url('orcamento'));
    }
    $days = (int) Settings::all()['quote_validity_days'];
    $f = isset($formData) ? $formData : ($q ? [
        'customer_name' => $q['customer_name'], 'customer_phone' => $q['customer_phone'], 'customer_email' => $q['customer_email'],
        'customer_tax_id' => $q['customer_tax_id'], 'customer_car' => $q['customer_car'], 'seller_id' => $q['seller_id'],
        'discount' => number_format((float) $q['discount'], 2, ',', ''), 'valid_until' => $q['valid_until'],
        'payment_terms' => $q['payment_terms'], 'notes' => $q['notes'], 'pay_method' => $q['pay_method'], 'installments' => $q['installments'],
        'items' => array_map(fn ($i) => ['category' => $i['category'], 'description' => $i['description'], 'quantity' => $i['quantity'], 'unit_price' => number_format((float) $i['unit_price'], 2, ',', '')], $q['items']),
    ] : ['valid_until' => date('Y-m-d', strtotime("+$days days"))]);
    $v = fn (string $k) => e((string) ($f[$k] ?? ''));
    ?>
<section class="hero slim"><div><h1><?= $q ? 'Editar orcamento #' . e(Quote::number($q)) : 'Novo orcamento' ?></h1><p>Monte o orcamento da loja; depois imprima ou envie o PDF pelo WhatsApp.</p></div></section>
<form method="post" class="caps" id="quote-form" data-lookup="<?= e(url('orcamento') . '&lookup=') ?>"><?= App\Csrf::field() ?><input type="hidden" name="action" value="save_quote"><input type="hidden" name="id" value="<?= (int) $editId ?>">
  <div class="card"><h2>Cliente</h2>
    <div class="suggest" style="margin:10px 0"><input id="q-customer-search" placeholder="BUSCAR CLIENTE CADASTRADO (NOME, TELEFONE OU CPF)" autocomplete="off"><ul id="q-customer-results" hidden></ul></div>
    <div class="row c2"><input name="customer_name" placeholder="NOME DO CLIENTE *" required value="<?= $v('customer_name') ?>"><input name="customer_phone" inputmode="tel" placeholder="TELEFONE / WHATSAPP" value="<?= $v('customer_phone') ?>"></div>
    <div class="row c2"><input name="customer_email" type="email" placeholder="E-MAIL" value="<?= $v('customer_email') ?>"><input name="customer_tax_id" inputmode="numeric" placeholder="CPF/CNPJ" value="<?= $v('customer_tax_id') ?>"></div>
    <div class="row c2"><input name="customer_car" placeholder="CARRO" value="<?= $v('customer_car') ?>">
      <select name="seller_id" aria-label="Vendedor"><option value="">Vendedor (opcional)</option><?php foreach ($sellers as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) ($f['seller_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
  </div>
  <div class="card">
    <div class="actions" style="justify-content:space-between;align-items:center"><div><h2>Itens do orcamento</h2><p class="sub" style="margin:0">Pneus e Roda: busque no estoque para puxar o preco, ou digite livremente. Servicos: descreva e informe o valor.</p></div><button class="btn ghost sm" type="button" id="q-add">+ Item</button></div>
    <div id="q-items" style="margin-top:12px"></div>
    <div class="totalbar"><span class="muted">Total</span><b id="q-total">R$ 0,00</b></div>
  </div>
  <div class="card"><h2>Condicoes</h2>
    <div class="row c3" style="margin-top:10px"><div><label class="f" for="discount">Desconto (R$)</label><input id="discount" name="discount" inputmode="decimal" placeholder="0,00" value="<?= $v('discount') ?>"></div>
      <div><label class="f" for="valid_until">Valido ate</label><input id="valid_until" type="date" name="valid_until" value="<?= $v('valid_until') ?>"></div>
      <div><label class="f" for="pay_method">Forma de pagamento</label><select id="pay_method" name="pay_method"><option value="">A combinar</option><?php foreach (Quote::PAY_METHODS as $k => $lbl): ?><option value="<?= e($k) ?>" <?= ($f['pay_method'] ?? '') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select></div></div>
    <div class="row c2" id="inst-row" style="margin-top:10px" hidden>
      <div><label class="f" for="installments">Parcelas no cartao</label><select id="installments" name="installments"><?php for ($n = 1; $n <= 12; $n++): ?><option value="<?= $n ?>" <?= (int) ($f['installments'] ?? 1) === $n ? 'selected' : '' ?>><?= $n === 1 ? 'A vista (1x)' : $n . 'x' ?></option><?php endfor; ?></select></div>
      <div><label class="f">Valor da parcela</label><div id="inst-preview" class="card" style="padding:10px 14px;margin:0;font-weight:700">-</div></div></div>
    <div style="margin:10px 0"><label class="f" for="payment_terms">Observacao do pagamento (opcional)</label><input id="payment_terms" name="payment_terms" maxlength="255" placeholder="EX.: ENTRADA DE 50% E O RESTO NA ENTREGA" value="<?= $v('payment_terms') ?>"></div>
    <textarea name="notes" placeholder="OBSERVACOES"><?= $v('notes') ?></textarea>
  </div>
  <div class="actions"><button class="btn green" type="submit" style="font-size:15px;padding:12px 22px">Salvar orcamento</button><a class="btn ghost" href="<?= e(url('orcamento')) ?>" style="padding:12px 22px">Cancelar</a></div>
</form>
<script>
(function () {
  const form = document.getElementById('quote-form'), box = document.getElementById('q-items'), lookup = form.dataset.lookup;
  const ITEMS = <?= json_encode(array_values((array) ($f['items'] ?? [])), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const cents = (v) => { v = String(v || '').trim().replace(/[^\d,.\-]/g, ''); if (v.includes(',')) v = v.replace(/\./g, '').replace(',', '.'); const n = parseFloat(v); return isNaN(n) ? 0 : Math.round(n * 100); };
  const brl = (c) => (c / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const el = (h) => { const t = document.createElement('template'); t.innerHTML = h.trim(); return t.content.firstChild; };
  const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
  const PH = { pneu: 'MEDIDA (EX.: 175/65 R14) - BUSCA NO ESTOQUE', roda: 'RODA (NOME, MARCA OU ARO) - BUSCA NO ESTOQUE', servico: 'DESCRICAO DO SERVICO' };
  let n = 0;
  async function find(kind, q, cat) { const r = await fetch(lookup + kind + '&q=' + encodeURIComponent(q) + (cat ? '&cat=' + cat : ''), { credentials: 'same-origin' }); return r.ok ? r.json() : []; }
  function calc() {
    let t = 0;
    box.querySelectorAll('.item-line').forEach((l) => { t += cents(l.querySelector('[name$="[unit_price]"]').value) * (parseInt(l.querySelector('[name$="[quantity]"]').value) || 0); });
    const d = Math.min(cents(form.discount.value), t);
    document.getElementById('q-total').textContent = brl(t - d);
    total = t - d; inst();
  }
  let total = 0;
  const pm = document.getElementById('pay_method'), ni = document.getElementById('installments');
  function inst() {
    document.getElementById('inst-row').hidden = pm.value !== 'credito';
    const n = parseInt(ni.value) || 1, base = Math.floor(total / n), rest = total - base * n;
    document.getElementById('inst-preview').textContent = !total ? '-' : (n === 1 ? brl(total) + ' a vista' : rest === 0 ? n + 'x de ' + brl(base) : (n - 1) + 'x de ' + brl(base) + ' + 1x de ' + brl(base + rest));
  }
  pm.addEventListener('change', inst); ni.addEventListener('change', inst);
  function add(d = {}) {
    const i = n++, cat = PH[d.category] ? d.category : 'pneu';
    const line = el(`<div class="item-line"><div class="grid cat-grid">
      <select name="items[${i}][category]" class="cat-sel"><option value="pneu" ${cat === 'pneu' ? 'selected' : ''}>Pneus</option><option value="roda" ${cat === 'roda' ? 'selected' : ''}>Roda</option><option value="servico" ${cat === 'servico' ? 'selected' : ''}>Servicos</option></select>
      <div class="suggest"><input name="items[${i}][description]" autocomplete="off" value="${esc(d.description)}"><ul hidden></ul></div>
      <input name="items[${i}][quantity]" type="number" min="1" placeholder="QTD." value="${esc(d.quantity || 1)}">
      <input name="items[${i}][unit_price]" inputmode="decimal" placeholder="PRECO UNITARIO" value="${esc(d.unit_price)}"></div>
      <div class="line-foot"><span class="small muted"></span><button type="button" class="rm" style="color:var(--danger)">Remover</button></div></div>`);
    const sel = line.querySelector('.cat-sel'), desc = line.querySelector('.suggest input'), list = line.querySelector('ul'), price = line.querySelector('[name$="[unit_price]"]');
    const ph = () => { desc.placeholder = PH[sel.value]; }; ph(); sel.onchange = () => { ph(); list.hidden = true; };
    desc.addEventListener('input', debounce(async () => {
      if (sel.value === 'servico') return;
      const rows = await find('product', desc.value, sel.value);
      list.innerHTML = rows.map((p, k) => `<li data-k="${k}"><b>${esc(p.label)}</b> <small>${p.condition_type === 'usado' ? 'usado · ' : ''}estoque ${p.stock} · ${brl(Math.round(p.price * 100))}</small></li>`).join('');
      list.hidden = !rows.length || desc.value.trim().length < 2;
      list.onclick = (ev) => { const li = ev.target.closest('li'); if (!li) return; const p = rows[+li.dataset.k];
        desc.value = (p.label + (p.condition_type === 'usado' ? ' (USADO)' : '')).toUpperCase(); price.value = Number(p.price).toFixed(2).replace('.', ','); list.hidden = true; calc(); };
    }, 200));
    desc.addEventListener('blur', () => setTimeout(() => (list.hidden = true), 150));
    line.querySelector('.rm').onclick = () => { line.remove(); if (!box.children.length) add(); calc(); };
    line.addEventListener('input', calc); box.appendChild(line);
  }
  form.addEventListener('input', (ev) => { const t = ev.target; if (!t.matches('input,textarea') || t.matches('input[type=hidden],input[type=number],input[type=date]')) return;
    const u = t.value.toUpperCase(); if (u === t.value) return; let a = null, b = null; try { a = t.selectionStart; b = t.selectionEnd; } catch (e) {} t.value = u; if (a !== null) { try { t.setSelectionRange(a, b); } catch (e) {} } }, true);
  document.getElementById('q-add').onclick = () => add();
  (ITEMS.length ? ITEMS : [{}]).forEach(add); calc(); form.discount.addEventListener('input', calc);
  // cliente cadastrado
  const cs = document.getElementById('q-customer-search'), cr = document.getElementById('q-customer-results');
  cs.addEventListener('input', debounce(async () => {
    const rows = await find('customer', cs.value);
    cr.innerHTML = rows.map((c, k) => `<li data-k="${k}">${esc(c.first_name + ' ' + c.last_name)} <small>${esc(c.phone || '')} ${esc(c.car || '')}</small></li>`).join(''); cr.hidden = !rows.length;
    cr.onclick = (ev) => { const li = ev.target.closest('li'); if (!li) return; const c = rows[+li.dataset.k];
      form.customer_name.value = (c.first_name + ' ' + c.last_name).trim().toUpperCase(); form.customer_phone.value = c.phone || ''; form.customer_email.value = (c.email || '').toUpperCase();
      form.customer_tax_id.value = c.tax_id || ''; form.customer_car.value = (c.car || '').toUpperCase(); cs.value = ''; cr.hidden = true; };
  }, 250));
})();
</script>
    <?php
    return;
}

// ---------- Lista ----------
$q = trim((string) ($_GET['q'] ?? ''));
$args = [];
$where = '1=1';
if ($q !== '') {
    $where = "(q.customer_name LIKE ? OR q.customer_phone LIKE ? OR q.id = ?)";
    $args = ['%' . addcslashes($q, '%_\\') . '%', '%' . addcslashes($q, '%_\\') . '%', (int) $q];
}
$rows = Database::all("SELECT q.*, s.name seller FROM quotes q LEFT JOIN sellers s ON s.id = q.seller_id WHERE $where ORDER BY q.id DESC LIMIT 100", $args);
?>
<section class="hero slim"><div><h1>Orcamento</h1><p>Orcamentos personalizados da loja, prontos para imprimir ou enviar em PDF pelo WhatsApp.</p></div>
  <div class="actions"><a class="btn green" href="<?= e(url('orcamento', ['new' => 1])) ?>">+ Novo orcamento</a><?php if (Auth::isAdmin()): ?><a class="btn ghost" href="<?= e(url('orcamento', ['settings' => 1])) ?>">Dados da loja</a><?php endif; ?></div></section>
<div class="card">
  <form class="filters" method="get" style="grid-template-columns:1fr auto"><input type="hidden" name="page" value="orcamento"><input name="q" placeholder="Buscar por cliente, telefone ou numero" value="<?= e($q) ?>"><button class="btn" type="submit">Buscar</button></form>
  <div class="scroll"><table><thead><tr><th>#</th><th>Data</th><th>Cliente</th><th>Vendedor</th><th>Validade</th><th>Situacao</th><th class="num">Total</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): $expired = $r['valid_until'] && $r['valid_until'] < date('Y-m-d'); ?>
    <tr><td>#<?= e(Quote::number($r)) ?></td><td><?= e(fmt_date($r['created_at'], true)) ?></td><td><b><?= e($r['customer_name']) ?></b><br><span class="small muted"><?= e($r['customer_phone'] ?? '') ?></span></td><td><?= e($r['seller'] ?: '-') ?></td>
      <td><?= e(fmt_date($r['valid_until'])) ?> <span class="tag <?= $expired ? 'red' : 'green' ?>"><?= $expired ? 'vencido' : 'valido' ?></span></td><td><?php if ($r['sale_id']): ?><span class="tag green">vendido #<?= (int) $r['sale_id'] ?></span><?php elseif ($r['status'] === 'aprovado'): ?><span class="tag warn">aprovado</span><?php else: ?><span class="tag">aberto</span><?php endif; ?></td><td class="num"><b><?= money($r['total']) ?></b></td>
      <td class="actions"><a class="btn ghost sm" href="<?= e(url('orcamento', ['view' => $r['id']])) ?>">Abrir</a><a class="btn ghost sm" href="<?= e(url('orcamento', ['edit' => $r['id']])) ?>">Editar</a></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="muted">Nenhum orcamento ainda. Clique em "+ Novo orcamento".</td></tr><?php endif; ?></tbody></table></div>
</div>
