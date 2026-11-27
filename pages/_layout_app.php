<?php
/** @var string $content @var string $title @var string $page */
$nav = array_filter(PAGES, fn ($k) => $k !== 'zerar' && ($k !== 'vendedores' || App\Auth::isAdmin()), ARRAY_FILTER_USE_KEY);
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - ERP Pneus</title>
<link rel="icon" type="image/png" href="<?= e(asset('favicon.png')) ?>">
<meta name="theme-color" content="#070707">
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<?php $moneyPage = in_array($page, ['dashboard', 'financeiro', 'vendas', 'estoque', 'emprestimos', 'crm', 'vendedores'], true); ?>
<script>try { if (localStorage.getItem('navOff') === '1') document.documentElement.classList.add('nav-off'); <?php if ($moneyPage): ?>if (localStorage.getItem('moneyOff') === '1') document.documentElement.classList.add('money-off');<?php endif; ?> } catch (e) {}</script>
</head>
<body>
<div class="shell">
  <aside class="side" id="side">
    <a class="brand-logo" href="<?= e(url('dashboard')) ?>" aria-label="Neto Rodas e Pneus"><img src="<?= e(asset('logo.webp')) ?>" alt="Neto Rodas e Pneus" width="150" height="91" style="width:150px;height:auto;display:block"></a>
    <div class="co">ERP PNEUS</div>
    <button class="nav-hide" type="button" onclick="navToggle(true)" title="Recolher o menu" aria-label="Recolher o menu">&#8249; Recolher menu</button>
    <button class="btn ghost sm top-toggle" type="button" onclick="document.getElementById('side').classList.toggle('open')">Menu</button>
    <nav>
      <?php foreach ($nav as $key => $label): ?>
        <a href="<?= e(url($key)) ?>" class="<?= $key === $page ? 'on' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="who">
      <b><?= e(App\Auth::user()['username'] ?? '') ?></b>
      <a href="<?= e(url('logout')) ?>">Sair</a>
    </div>
  </aside>
  <button class="nav-show" type="button" onclick="navToggle(false)" title="Mostrar o menu" aria-label="Mostrar o menu">&#9776;</button>
  <?php if ($moneyPage): ?><button class="money-eye" id="money-eye" type="button" title="Ocultar valores" aria-label="Ocultar valores" aria-pressed="false"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path class="eye-open" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle class="eye-open" cx="12" cy="12" r="3"/><path class="eye-off" d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-7 0-11-7-11-7a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg><span>Ocultar valores</span></button><?php endif; ?>
  <main class="main">
    <?php foreach (take_flashes() as $f): ?>
      <div class="flash <?= $f['type'] === 'ok' ? 'ok' : 'err' ?>" role="status"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
<script>function navToggle(off) { document.documentElement.classList.toggle('nav-off', off); try { localStorage.setItem('navOff', off ? '1' : '0'); } catch (e) {} window.dispatchEvent(new Event('resize')); }</script>
<?php if ($moneyPage): ?><script>
// Olho: oculta todos os valores em R$ da tela (fica lembrado neste aparelho)
(function () {
  var root = document.documentElement, btn = document.getElementById('money-eye'), main = document.querySelector('.main');
  var RX = /(-?\s?R\$\s?-?[\d.]+(?:,\d{1,2})?|-?[\d.]+,\d{1,2}\s?%|\b\d{1,3}(?:,\d)?\s?%)/g;
  function wrap(node) {
    var w = document.createTreeWalker(node, NodeFilter.SHOW_TEXT, { acceptNode: function (t) {
      var p = t.parentNode; if (!p || p.closest('script,style,textarea,option,select,m-v,.money-eye')) return NodeFilter.FILTER_REJECT;
      RX.lastIndex = 0; return RX.test(t.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT; } });
    var list = []; while (w.nextNode()) list.push(w.currentNode);
    list.forEach(function (t) {
      var f = document.createDocumentFragment(), s = t.nodeValue, last = 0; RX.lastIndex = 0; var m;
      while ((m = RX.exec(s))) { if (m.index > last) f.appendChild(document.createTextNode(s.slice(last, m.index))); var sp = document.createElement('m-v'); sp.textContent = m[0]; f.appendChild(sp); last = m.index + m[0].length; }
      if (last < s.length) f.appendChild(document.createTextNode(s.slice(last)));
      t.parentNode.replaceChild(f, t);
    });
  }
  function sync() { var off = root.classList.contains('money-off'); btn.setAttribute('aria-pressed', off ? 'true' : 'false'); btn.title = btn.querySelector('span').textContent = off ? 'Mostrar valores' : 'Ocultar valores'; }
  btn.onclick = function () { root.classList.toggle('money-off'); try { localStorage.setItem('moneyOff', root.classList.contains('money-off') ? '1' : '0'); } catch (e) {} sync(); };
  wrap(main); sync();
  new MutationObserver(function (ms) { ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) wrap(n); else if (n.nodeType === 3 && n.parentNode) wrap(n.parentNode); }); }); }).observe(main, { childList: true, subtree: true });
})();
</script><?php endif; ?>
<script src="<?= e(asset('cep.js')) ?>"></script>
</body>
</html>
