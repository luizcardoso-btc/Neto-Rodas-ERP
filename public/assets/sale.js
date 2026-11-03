(function () {
  'use strict';
  const form = document.getElementById('sale-form');
  if (!form) return;

  const lookupBase = form.dataset.lookup;
  const itemsBox = document.getElementById('items');
  const paysBox = document.getElementById('payments');
  const brl = (c) => (c / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const cents = (v) => {
    v = String(v || '').trim().replace(/[^\d,.\-]/g, '');
    if (v.includes(',')) v = v.replace(/\./g, '').replace(',', '.');
    const n = parseFloat(v);
    return isNaN(n) ? 0 : Math.round(n * 100);
  };
  const el = (html) => { const t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstChild; };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  let seq = 0;

  function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }

  async function search(kind, q, cat) {
    const r = await fetch(lookupBase + kind + '&q=' + encodeURIComponent(q) + (cat ? '&cat=' + cat : ''), { credentials: 'same-origin' });
    return r.ok ? r.json() : [];
  }

  /* ---------- itens ---------- */
  const CATS = {
    pneu: { ph: 'BUSCAR MEDIDA (EX.: 175/65 R14)', empty: 'NENHUMA MEDIDA ENCONTRADA. DIGITE A MEDIDA (EX.: 175/65 R14) OU SO O ARO (R15).' },
    roda: { ph: 'BUSCAR RODA (NOME, MARCA OU ARO)', empty: 'NENHUMA RODA ENCONTRADA NO ESTOQUE. CADASTRE EM ESTOQUE (CATEGORIA RODA).' },
    servico: { ph: 'DESCRICAO DO SERVICO (EX.: ALINHAMENTO)', empty: '' },
  };
  function addItem(data = {}) {
    const i = seq++;
    const cat = CATS[data.category] ? data.category : 'pneu';
    const line = el(`
      <div class="item-line">
        <div class="grid cat-grid">
          <select name="items[${i}][category]" aria-label="Categoria" class="cat-sel">
            <option value="pneu" ${cat === 'pneu' ? 'selected' : ''}>Pneus</option>
            <option value="roda" ${cat === 'roda' ? 'selected' : ''}>Roda</option>
            <option value="servico" ${cat === 'servico' ? 'selected' : ''}>Servicos</option>
          </select>
          <div class="suggest">
            <input name="items[${i}][name]" autocomplete="off" value="${esc(data.name)}">
            <input type="hidden" name="items[${i}][product_id]" value="${esc(data.product_id)}">
            <ul hidden></ul>
          </div>
          <input name="items[${i}][quantity]" type="number" min="1" inputmode="numeric" placeholder="QUANTIDADE" value="${esc(data.quantity)}">
          <input name="items[${i}][unit_price]" inputmode="decimal" placeholder="PRECO UNITARIO" value="${esc(data.unit_price)}">
        </div>
        <div class="line-foot"><span class="small muted stock-info"></span><button type="button" class="rm" style="color:var(--danger)">Remover item</button></div>
      </div>`);
    const sel = line.querySelector('.cat-sel');
    const [name, pid] = line.querySelectorAll('.suggest input');
    const list = line.querySelector('ul');
    const info = line.querySelector('.stock-info');
    const qty = line.querySelector('[name$="[quantity]"]');
    const price = line.querySelector('[name$="[unit_price]"]');
    const applyCat = (reset) => {
      name.placeholder = CATS[sel.value].ph;
      if (reset) { name.value = ''; pid.value = ''; info.textContent = ''; list.hidden = true; }
      if (sel.value === 'servico') pid.value = '';
    };
    applyCat(false);
    sel.addEventListener('change', () => { applyCat(true); recalc(); });

    name.addEventListener('input', debounce(async () => {
      if (sel.value === 'servico') return;
      pid.value = ''; info.textContent = '';
      const rows = await search('product', name.value, sel.value);
      list.innerHTML = rows.length
        ? rows.map((p, k) =>
          `<li data-k="${k}"><b>${esc(p.label)}</b> <small>${p.condition_type === 'usado' ? '<span class="dot"></span>usado · ' : ''}estoque ${p.stock} · ${brl(Math.round(p.price * 100))}</small></li>`).join('')
        : `<li class="empty" style="cursor:default;color:var(--muted)">${CATS[sel.value].empty}</li>`;
      list.hidden = name.value.trim().length < 2;
      list.onclick = (ev) => {
        const li = ev.target.closest('li'); if (!li || li.classList.contains('empty')) return;
        const p = rows[+li.dataset.k];
        name.value = (p.label + (p.condition_type === 'usado' ? ' (usado)' : '')).toUpperCase(); pid.value = p.id;
        price.value = Number(p.price).toFixed(2).replace('.', ',');
        if (!qty.value) qty.value = 1;
        info.textContent = 'Em estoque: ' + p.stock + (p.location ? ' · ' + p.location : '');
        list.hidden = true; recalc();
      };
    }, 200));
    name.addEventListener('blur', () => setTimeout(() => (list.hidden = true), 150));
    line.querySelector('.rm').onclick = () => { line.remove(); if (!itemsBox.children.length) addItem(); recalc(); };
    line.addEventListener('input', recalc);
    itemsBox.appendChild(line);
  }

  /* ---------- pagamentos ---------- */
  function addPayment(data = {}) {
    const i = seq++;
    const sel = (m) => (data.method === m ? 'selected' : '');
    const line = el(`
      <div class="pay-line">
        <div class="grid">
          <select name="payments[${i}][method]" aria-label="Forma de pagamento">
            <option value="dinheiro" ${sel('dinheiro')}>Dinheiro</option><option value="pix" ${sel('pix')}>Pix</option>
            <option value="cartao" ${sel('cartao')}>Cartao</option><option value="prazo" ${sel('prazo')}>A prazo</option>
            <option value="troca" ${sel('troca')}>● Troca (usado)</option>
          </select>
          <input name="payments[${i}][amount]" inputmode="decimal" placeholder="Valor" value="${esc(data.amount)}">
        </div>
        <div class="trade" hidden style="margin-top:10px">
          <div class="trade-badge"><span class="dot"></span><span class="trade-msg"></span></div>
          <div class="grid">
            <select name="payments[${i}][trade_category]" aria-label="Categoria da troca" class="trade-cat">
              <option value="pneu" ${data.trade_category !== 'roda' ? 'selected' : ''}>Pneu · usado</option>
              <option value="roda" ${data.trade_category === 'roda' ? 'selected' : ''}>Roda · usado</option>
            </select>
            <input name="payments[${i}][note]" value="${esc(data.note)}">
            <input name="payments[${i}][trade_qty]" type="number" min="1" placeholder="QTD. RECEBIDA" value="${esc(data.trade_qty || 1)}">
          </div>
        </div>
        <label class="cred-box small" hidden><input type="checkbox" name="payments[${i}][to_creditor]" value="1" ${data.to_creditor ? 'checked' : ''} style="width:auto"> RECEBI NA MINHA CONTA E JA ENVIEI ESTE VALOR AO <b class="cred-name"></b></label>
        <div class="line-foot"><button type="button" class="rest" style="color:var(--brand-d)">= restante</button><button type="button" class="rm" style="color:var(--danger)">Remover</button></div>
      </div>`);
    const method = line.querySelector('select');
    const amount = line.querySelector('[name$="[amount]"]');
    const trade = line.querySelector('.trade');
    const tcat = line.querySelector('.trade-cat');
    const tnote = line.querySelector('[name$="[note]"]');
    const msg = line.querySelector('.trade-msg');
    const sync = () => {
      const isTrade = method.value === 'troca';
      trade.hidden = !isTrade; line.classList.toggle('trade-line', isTrade);
      const roda = tcat.value === 'roda';
      tnote.placeholder = roda ? 'RODA RECEBIDA (EX.: ARO 15 LIGA LEVE 5X100)' : 'PNEU RECEBIDO (EX.: 175/70 R14)';
      msg.textContent = roda ? 'Roda usada: este valor entra na categoria Roda, estoque de usados' : 'Pneu usado: este valor entra na categoria Pneu, estoque de usados';
      tnote.required = isTrade;
      const cb = line.querySelector('.cred-box');
      const canCred = ['dinheiro', 'pix', 'cartao'].includes(method.value);
      cb.hidden = !canCred;
      if (!canCred) cb.querySelector('input').checked = false;
      cb.querySelector('.cred-name').textContent = form.dataset.creditor || 'CREDOR';
      recalc();
    };
    tcat.addEventListener('change', sync);
    method.onchange = sync;
    line.querySelector('.rest').onclick = () => {
      const diff = totals().total - totals().paid + cents(amount.value);
      amount.value = diff > 0 ? (diff / 100).toFixed(2).replace('.', ',') : '';
      recalc();
    };
    line.querySelector('.rm').onclick = () => { line.remove(); if (!paysBox.children.length) addPayment(); recalc(); };
    line.addEventListener('input', recalc);
    paysBox.appendChild(line);
    sync();
  }

  /* ---------- totais ---------- */
  function totals() {
    let total = 0;
    itemsBox.querySelectorAll('.item-line').forEach((l) => {
      total += cents(l.querySelector('[name$="[unit_price]"]').value) * (parseInt(l.querySelector('[name$="[quantity]"]').value, 10) || 0);
    });
    let paid = 0, prazo = false;
    paysBox.querySelectorAll('.pay-line').forEach((l) => {
      paid += cents(l.querySelector('[name$="[amount]"]').value);
      if (l.querySelector('select').value === 'prazo') prazo = true;
    });
    return { total, paid, prazo };
  }
  function recalc() {
    const t = totals();
    document.getElementById('total').textContent = brl(t.total);
    document.getElementById('s-total').textContent = brl(t.total);
    document.getElementById('s-paid').textContent = brl(t.paid);
    const diff = t.total - t.paid;
    const box = document.getElementById('s-box');
    box.className = diff === 0 && t.total > 0 ? 'ok' : 'miss';
    box.firstChild.textContent = diff < 0 ? 'Excedente' : (diff === 0 && t.total > 0 ? 'Fechado' : 'Falta');
    document.getElementById('s-diff').textContent = diff === 0 && t.total > 0 ? 'OK' : brl(Math.abs(diff));
    document.getElementById('due-box').hidden = !t.prazo;
  }

  /* ---------- CAIXA ALTA em todos os campos de texto ---------- */
  const SKIP = 'input[type=hidden],input[type=number],input[type=date],input[type=checkbox],input[type=radio]';
  form.addEventListener('input', (ev) => {
    const t = ev.target;
    if (!t.matches('input,textarea') || t.matches(SKIP)) return;
    const u = t.value.toUpperCase();
    if (u === t.value) return;
    let a = null, b = null;
    try { a = t.selectionStart; b = t.selectionEnd; } catch (e) { /* e-mail nao expoe selecao */ }
    t.value = u;
    if (a !== null) { try { t.setSelectionRange(a, b); } catch (e) { /* ignora */ } }
  }, true);

  /* ---------- cliente ---------- */
  const cs = document.getElementById('customer-search');
  const cr = document.getElementById('customer-results');
  cs.addEventListener('input', debounce(async () => {
    const rows = await search('customer', cs.value);
    cr.innerHTML = rows.map((c, k) => `<li data-k="${k}">${esc(c.first_name + ' ' + c.last_name)} <small>${esc(c.phone || '')} ${esc(c.car || '')}</small></li>`).join('');
    cr.hidden = !rows.length;
    cr.onclick = (ev) => {
      const li = ev.target.closest('li'); if (!li) return;
      const c = rows[+li.dataset.k];
      document.getElementById('customer_id').value = c.id;
      ['first_name', 'last_name', 'email', 'phone', 'tax_id', 'car', 'street', 'number', 'district', 'city', 'state', 'zip']
        .forEach((f) => { const i = form.querySelector(`[name="customer[${f}]"]`); if (i) i.value = String(c[f] || '').toUpperCase(); });
      cs.value = (c.first_name + ' ' + c.last_name).toUpperCase(); cr.hidden = true;
    };
  }, 250));
  cs.addEventListener('blur', () => setTimeout(() => (cr.hidden = true), 150));
  // Editar manualmente o nome desfaz o vinculo com o cliente buscado.
  form.querySelector('[name="customer[first_name]"]').addEventListener('input', () => (document.getElementById('customer_id').value = ''));

  /* ---------- NF-e ---------- */
  const nfe = document.getElementById('wants-nfe');
  nfe.addEventListener('change', () => {
    ['street', 'number', 'district', 'city', 'state'].forEach((f) => {
      form.querySelector(`[name="customer[${f}]"]`).required = nfe.checked;
    });
  });

  form.addEventListener('submit', (ev) => {
    const t = totals();
    const unpicked = [...itemsBox.querySelectorAll('.item-line')].some((l) => {
      const [n, id] = l.querySelectorAll('.suggest input');
      return l.querySelector('.cat-sel').value !== 'servico' && n.value.trim() !== '' && id.value === '';
    });
    if (unpicked) { ev.preventDefault(); alert('Escolha o pneu/roda na lista de busca (para servicos, use a categoria Servicos).'); return; }
    const noDesc = [...itemsBox.querySelectorAll('.item-line')].some((l) => l.querySelector('.cat-sel').value === 'servico' && l.querySelector('.suggest input').value.trim() === '' && (cents(l.querySelector('[name$="[unit_price]"]').value) > 0));
    if (noDesc) { ev.preventDefault(); alert('Descreva o servico.'); return; }
    const sellerEl = form.querySelector('[name="seller_id"]');
    if (form.querySelector('.cred-box input:checked') && sellerEl && !sellerEl.value) { ev.preventDefault(); alert('Selecione o vendedor que recebeu o pagamento enviado ao credor.'); return; }
    if (!t.total) { ev.preventDefault(); alert('Adicione ao menos um item com quantidade e preco.'); return; }
    if (t.total !== t.paid) { ev.preventDefault(); alert('Os pagamentos precisam fechar com o total da venda.'); return; }
    if (!form.checkValidity()) { ev.preventDefault(); form.reportValidity(); return; }
    document.getElementById('submit').disabled = true; // evita duplo clique
  });

  /* ---------- restaurar apos erro ---------- */
  let old = null;
  try { old = JSON.parse(form.dataset.old); } catch (e) { /* sem dados anteriores */ }
  if (old) {
    const set = (n, v) => { const i = form.querySelector(`[name="${n}"]`); if (i && v != null) i.value = v; };
    Object.entries(old.customer || {}).forEach(([k, v]) => set(`customer[${k}]`, v));
    set('notes', old.notes); set('seller_id', old.seller_id); set('due_date', old.due_date);
    if (old.wants_nfe) { nfe.checked = true; nfe.dispatchEvent(new Event('change')); }
    Object.values(old.items || {}).forEach(addItem);
    Object.values(old.payments || {}).forEach(addPayment);
  }
  if (!itemsBox.children.length) addItem();
  if (!paysBox.children.length) addPayment();
  document.getElementById('add-item').onclick = () => addItem();
  document.getElementById('add-payment').onclick = () => addPayment();
  recalc();
})();
