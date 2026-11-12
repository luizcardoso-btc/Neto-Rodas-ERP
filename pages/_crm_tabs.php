<?php $crmTab = (string) ($_GET['tab'] ?? ''); ?>
<nav class="fin-tabs crm-tabs" style="margin:0 0 16px">
  <a class="pill <?= $crmTab === '' ? 'on' : '' ?>" href="<?= e(url('crm')) ?>">Clientes</a>
  <a class="pill <?= $crmTab === 'reativar' ? 'on' : '' ?>" href="<?= e(url('crm', ['tab' => 'reativar'])) ?>">Reativar clientes</a>
  <?php $bToday = (int) App\Database::value("SELECT COUNT(*) FROM customers WHERE birth_date IS NOT NULL AND DATE_FORMAT(birth_date, '%m-%d') = DATE_FORMAT(CURDATE(), '%m-%d') AND COALESCE(birthday_sent_year, 0) <> YEAR(CURDATE())"); ?>
  <a class="pill <?= $crmTab === 'aniversarios' ? 'on' : '' ?>" href="<?= e(url('crm', ['tab' => 'aniversarios'])) ?>">Aniversariantes<?php if ($bToday): ?> <span class="badge-n"><?= $bToday ?></span><?php endif; ?></a>
  <a class="pill <?= $crmTab === 'ofertas' ? 'on' : '' ?>" href="<?= e(url('crm', ['tab' => 'ofertas'])) ?>">Ofertas</a>
  <?php if (App\Auth::isAdmin()): ?><a class="pill <?= $crmTab === 'importar' ? 'on' : '' ?>" href="<?= e(url('crm', ['tab' => 'importar'])) ?>">Importar clientes</a><?php endif; ?>
</nav>
