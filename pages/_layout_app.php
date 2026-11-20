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
<script>try { if (localStorage.getItem('navOff') === '1') document.documentElement.classList.add('nav-off'); } catch (e) {}</script>
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
  <main class="main">
    <?php foreach (take_flashes() as $f): ?>
      <div class="flash <?= $f['type'] === 'ok' ? 'ok' : 'err' ?>" role="status"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
<script>function navToggle(off) { document.documentElement.classList.toggle('nav-off', off); try { localStorage.setItem('navOff', off ? '1' : '0'); } catch (e) {} window.dispatchEvent(new Event('resize')); }</script>
<script src="<?= e(asset('cep.js')) ?>"></script>
</body>
</html>
