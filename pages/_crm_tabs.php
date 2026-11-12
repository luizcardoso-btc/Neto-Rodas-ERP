<?php $crmTab = (string) ($_GET['tab'] ?? ''); ?>
<nav class="fin-tabs crm-tabs" style="margin:0 0 16px">
  <a class="pill <?= $crmTab === '' ? 'on' : '' ?>" href="<?= e(url('crm')) ?>">Clientes</a>
  <a class="pill <?= $crmTab === 'reativar' ? 'on' : '' ?>" href="<?= e(url('crm', ['tab' => 'reativar'])) ?>">Reativar clientes</a>
  <a class="pill <?= $crmTab === 'ofertas' ? 'on' : '' ?>" href="<?= e(url('crm', ['tab' => 'ofertas'])) ?>">Ofertas</a>
  <?php if (App\Auth::isAdmin()): ?><a class="pill <?= $crmTab === 'importar' ? 'on' : '' ?>" href="<?= e(url('crm', ['tab' => 'importar'])) ?>">Importar clientes</a><?php endif; ?>
</nav>
