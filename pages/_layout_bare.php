<?php /** @var string $content @var string $title */ ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - ERP Pneus</title>
<link rel="icon" type="image/png" href="<?= e(asset('favicon.png')) ?>">
<meta name="theme-color" content="#070707">
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
</head>
<body>
<?php foreach (take_flashes() as $f): ?>
  <div class="flash <?= $f['type'] === 'ok' ? 'ok' : 'err' ?>" style="margin:12px" role="status"><?= e($f['message']) ?></div>
<?php endforeach; ?>
<?= $content ?>
</body>
</html>
