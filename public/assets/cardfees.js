/* Campos de cartao (maquina, bandeira, parcelas) e previa da taxa. Usado no PDV e na edicao de venda. */
window.CardUI = (function () {
  const esc = (v) => String(v ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const brl = (c) => (c / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const BRANDS = { vm: 'Visa / Master', elo: 'Elo', amex: 'Amex' };
  function html(i, d, cfg) {
    const m = cfg.machines.map((x) => `<option value="${x.id}" ${String(d.machine_id) === String(x.id) ? 'selected' : ''}>${esc(x.name)}</option>`).join('');
    const b = Object.keys(BRANDS).map((k) => `<option value="${k}" ${(d.card_brand || 'vm') === k ? 'selected' : ''}>${BRANDS[k]}</option>`).join('');
    let p = `<option value="">PARCELAS</option><option value="d" ${d.card_inst === 'd' ? 'selected' : ''}>Debito</option>`;
    for (let n = 1; n <= 12; n++) p += `<option value="${n}" ${String(d.card_inst) === String(n) ? 'selected' : ''}>${n}x</option>`;
    return `<div class="card-box" hidden style="margin-top:10px"><div class="grid">
      <select name="payments[${i}][machine_id]" class="cm" aria-label="Maquina"><option value="">MAQUINA</option>${m}</select>
      <select name="payments[${i}][card_brand]" class="cb" aria-label="Bandeira">${b}</select>
      <select name="payments[${i}][card_inst]" class="ci" aria-label="Parcelas">${p}</select></div>
      <small class="fee-msg muted"></small></div>`;
  }
  /* liga os campos: show() exibe/oculta conforme a forma; preview mostra taxa e liquido */
  function wire(line, cfg, amountCents) {
    const box = line.querySelector('.card-box'), msg = line.querySelector('.fee-msg');
    const get = (s) => line.querySelector(s).value;
    const update = (isCard) => {
      box.hidden = !isCard;
      line.querySelectorAll('.card-box select').forEach((s) => { s.required = isCard; });
      if (!isCard) { msg.textContent = ''; return; }
      const mid = get('.cm'), inst = get('.ci');
      if (!mid || !inst) { msg.textContent = 'Escolha a maquina e as parcelas: o cliente paga o valor cheio e a taxa e calculada.'; return; }
      const r = ((cfg.rates[mid] || {})[get('.cb')] || {})[inst];
      if (r === undefined) { msg.textContent = 'Sem taxa cadastrada para esta combinacao (Financeiro > Custos e taxas). Taxa considerada: 0%.'; return; }
      const fee = Math.round(amountCents() * r / 100);
      msg.textContent = `Taxa ${String(r).replace('.', ',')}% = ${brl(fee)} · a loja recebe ${brl(amountCents() - fee)}`;
    };
    return update;
  }
  return { html, wire };
})();
