/* Digitou o CEP -> preenche logradouro, bairro, cidade e UF (ViaCEP). Funciona em qualquer formulario com campo de CEP. */
(function () {
  const sel = 'input[name="zip"], input[name$="[zip]"]';
  const up = (v) => String(v || '').toUpperCase();
  function pick(form, n) { return form.querySelector(`[name="${n}"], [name="customer[${n}]"]`); }
  async function lookup(zip) {
    const form = zip.form; if (!form) return;
    const d = zip.value.replace(/\D/g, '');
    if (d.length !== 8) return;
    if (zip.dataset.last === d) return;
    zip.dataset.last = d;
    let hint = zip.parentNode.querySelector('.cep-hint');
    if (!hint) { hint = document.createElement('small'); hint.className = 'cep-hint muted'; hint.style.cssText = 'display:block;margin-top:4px'; zip.insertAdjacentElement('afterend', hint); }
    hint.textContent = 'Buscando endereco...';
    try {
      const r = await fetch(`https://viacep.com.br/ws/${d}/json/`);
      const j = await r.json();
      if (j.erro) { hint.textContent = 'CEP nao encontrado. Preencha o endereco manualmente.'; return; }
      const set = (n, v) => { const i = pick(form, n); if (i && v) i.value = up(v); };
      set('street', j.logradouro); set('district', j.bairro); set('city', j.localidade); set('state', j.uf);
      hint.textContent = 'Endereco preenchido: ' + up([j.logradouro, j.bairro, j.localidade + '/' + j.uf].filter(Boolean).join(' - '));
      const num = pick(form, 'number'); if (num && !num.value) num.focus();
    } catch (e) { hint.textContent = 'Sem conexao com o servico de CEP. Preencha o endereco manualmente.'; }
  }
  document.addEventListener('input', (ev) => { if (ev.target.matches(sel)) lookup(ev.target); });
  document.addEventListener('change', (ev) => { if (ev.target.matches(sel)) lookup(ev.target); });
})();
