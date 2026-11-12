<?php
use App\Database;

const CRM_FIELDS = ['first_name', 'last_name', 'email', 'phone', 'tax_id', 'car', 'street', 'number', 'district', 'city', 'state', 'zip', 'country', 'notes', 'birth_date'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_starts_with((string) ($_POST['action'] ?? ''), 'mk_')) {
    require __DIR__ . '/_crm_marketing_actions.php';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!App\Auth::isAdmin() && (($action === 'save' && !empty($_POST['id'])) || $action === 'delete')) {
        flash('err', 'Somente o administrador pode editar ou excluir clientes.');
        redirect(url('crm'));
    }
    if ($action === 'save') {
        $d = [];
        foreach (CRM_FIELDS as $f) {
            $d[$f] = upper((string) ($_POST[$f] ?? ''));
        }
        $d['tax_id'] = digits($d['tax_id']);
        $d['phone'] = format_phone($d['phone']);
        $d['state'] = strtoupper(substr($d['state'], 0, 2));
        $d['country'] = $d['country'] ?: 'Brasil';
        $d['birth_date'] = App\Marketing::parseBirth($d['birth_date']) ?? '';
        if ($d['first_name'] === '') {
            flash('err', 'Informe o nome do cliente.');
        } elseif ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            flash('err', 'E-mail invalido.');
        } else {
            $vals = array_map(fn ($v) => $v === '' ? null : $v, $d);
            $vals['first_name'] = $d['first_name'];
            $vals['last_name'] = $d['last_name'];
            $vals['country'] = $d['country'];
            if ($id = (int) ($_POST['id'] ?? 0)) {
                Database::run('UPDATE customers SET ' . implode(', ', array_map(fn ($f) => "$f = ?", CRM_FIELDS)) . ' WHERE id = ?', array_merge(array_values($vals), [$id]));
                flash('ok', 'Cliente atualizado.');
            } else {
                Database::insert('INSERT INTO customers (' . implode(',', CRM_FIELDS) . ') VALUES (' . implode(',', array_fill(0, count(CRM_FIELDS), '?')) . ')', array_values($vals));
                flash('ok', 'Cliente cadastrado.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) $_POST['id'];
        if (Database::value('SELECT COUNT(*) FROM sales WHERE customer_id = ?', [$id]) > 0) {
            flash('err', 'Este cliente tem vendas registradas e nao pode ser excluido.');
        } else {
            Database::run('DELETE FROM customers WHERE id = ?', [$id]);
            flash('ok', 'Cliente excluido.');
        }
    }
    redirect(url('crm'));
}

if (!empty($_GET['history'])) {
    $c = Database::one('SELECT * FROM customers WHERE id = ?', [(int) $_GET['history']]);
    if ($c) {
        $sales = Database::all('SELECT s.*, (SELECT GROUP_CONCAT(description SEPARATOR ", ") FROM sale_items WHERE sale_id = s.id) items FROM sales s WHERE customer_id = ? ORDER BY id DESC', [$c['id']]);
        ?>
<section class="hero slim"><div><h1><?= e(trim($c['first_name'] . ' ' . $c['last_name'])) ?></h1><p><?= e($c['phone'] ?? '') ?> <?= e($c['car'] ? '· ' . $c['car'] : '') ?></p></div>
  <div class="big"><small>Total em compras</small><strong><?= money(array_sum(array_column($sales, 'total'))) ?></strong></div></section>
<div class="card"><h2>Historico de compras</h2><table><thead><tr><th>#</th><th>Data</th><th>Itens</th><th class="num">Total</th></tr></thead><tbody>
  <?php foreach ($sales as $s): ?><tr><td><a href="<?= e(url('vendas', ['id' => $s['id']])) ?>">#<?= (int) $s['id'] ?></a></td><td><?= e(fmt_date($s['created_at'], true)) ?></td><td><?= e($s['items']) ?></td><td class="num"><?= money($s['total']) ?><?php if (App\Auth::isAdmin()): ?> <a class="btn ghost sm" href="<?= e(url('vendas', ['edit' => $s['id']])) ?>">Editar</a><?php endif; ?></td></tr><?php endforeach; ?>
  <?php if (!$sales): ?><tr><td colspan="4" class="muted">Este cliente ainda nao comprou.</td></tr><?php endif; ?></tbody></table>
  <p><a href="<?= e(url('crm')) ?>">Voltar para clientes</a></p></div>
        <?php
        return;
    }
}

$crmTab = (string) ($_GET['tab'] ?? '');
if (in_array($crmTab, ['reativar', 'aniversarios', 'ofertas', 'importar'], true)) {
    require __DIR__ . '/_crm_marketing.php';
    return;
}
$q = trim((string) ($_GET['q'] ?? ''));
$args = [];
$where = '1=1';
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where = "(CONCAT_WS(' ', c.first_name, c.last_name) LIKE ? OR c.phone LIKE ? OR c.tax_id LIKE ? OR c.email LIKE ? OR c.car LIKE ?)";
    $args = [$like, $like, $like, $like, $like];
}
$kpi = Database::one(
    'SELECT COUNT(*) total,
            SUM(EXISTS (SELECT 1 FROM sales s WHERE s.customer_id = c.id AND s.created_at >= NOW() - INTERVAL 90 DAY)) active
     FROM customers c'
);
$best = Database::one(
    "SELECT CONCAT_WS(' ', c.first_name, c.last_name) name, SUM(s.total) ltv FROM sales s JOIN customers c ON c.id = s.customer_id GROUP BY c.id ORDER BY ltv DESC LIMIT 1"
);
$pg = max(1, (int) ($_GET['p'] ?? 1));
$per = 30;
$count = (int) Database::value("SELECT COUNT(*) FROM customers c WHERE $where", $args);
$rows = Database::all(
    "SELECT c.*, COALESCE(SUM(s.total),0) ltv, COUNT(s.id) purchases, MAX(s.created_at) last_purchase
     FROM customers c LEFT JOIN sales s ON s.customer_id = c.id WHERE $where GROUP BY c.id ORDER BY last_purchase DESC, c.id DESC LIMIT $per OFFSET " . (($pg - 1) * $per),
    $args
);
$edit = isset($_GET['edit']) && App\Auth::isAdmin() ? Database::one('SELECT * FROM customers WHERE id = ?', [(int) $_GET['edit']]) : null;
$v = fn (string $k, string $d = '') => e((string) ($edit[$k] ?? $d));
$active = (int) $kpi['active'];
?>
<section class="hero slim"><div><h1>CRM de clientes</h1><p>Carteira, historico de compras e contato rapido por WhatsApp.</p></div></section>
<?php require __DIR__ . '/_crm_tabs.php'; ?>
<div class="kpis">
  <div class="kpi"><span>Cadastros</span><strong><?= (int) $kpi['total'] ?></strong></div>
  <div class="kpi"><span>Ativos (90 dias)</span><strong class="ok"><?= $active ?></strong></div>
  <div class="kpi"><span>Inativos</span><strong class="bad"><?= (int) $kpi['total'] - $active ?></strong><small>sem comprar ha +90 dias</small></div>
  <div class="kpi"><span>Maior cliente</span><strong style="font-size:18px"><?= e($best['name'] ?? '-') ?></strong><small class="ok"><?= $best ? money($best['ltv']) : '' ?></small></div>
</div>
<div class="cols">
  <form class="card caps" method="post" id="form"><?= App\Csrf::field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <h2><?= $edit ? 'Editar cliente' : 'Novo cliente' ?></h2>
    <div class="stack" style="margin-top:12px">
      <div class="row c2"><input name="first_name" placeholder="Nome" required value="<?= $v('first_name') ?>"><input name="last_name" placeholder="Sobrenome" value="<?= $v('last_name') ?>"></div>
      <input name="email" type="email" placeholder="E-mail" value="<?= $v('email') ?>">
      <input name="phone" inputmode="tel" placeholder="Telefone (xx xxxxx-xxxx)" value="<?= $v('phone') ?>">
      <input name="tax_id" inputmode="numeric" placeholder="CPF/CNPJ" value="<?= $v('tax_id') ?>">
      <input name="car" placeholder="Carro" value="<?= $v('car') ?>">
      <label class="birth"><span>Data de nascimento</span><input name="birth_date" type="date" max="<?= date('Y-m-d') ?>" value="<?= $v('birth_date') ?>"></label>
      <div class="row c2"><input name="street" placeholder="Logradouro" value="<?= $v('street') ?>"><input name="number" placeholder="Numero" value="<?= $v('number') ?>"></div>
      <div class="row c2"><input name="district" placeholder="Bairro" value="<?= $v('district') ?>"><input name="city" placeholder="Cidade" value="<?= $v('city') ?>"></div>
      <div class="row c2"><input name="state" maxlength="2" placeholder="UF" value="<?= $v('state') ?>"><input name="zip" placeholder="CEP" value="<?= $v('zip') ?>"></div>
      <input name="country" placeholder="Pais" value="<?= $v('country', 'Brasil') ?>">
      <textarea name="notes" placeholder="Observacoes"><?= $v('notes') ?></textarea>
      <button class="btn green block" type="submit" style="font-size:15px;padding:12px"><?= $edit ? 'Salvar alteracoes' : 'Cadastrar cliente' ?></button>
      <?php if ($edit): $cs = Database::all('SELECT id, total, created_at FROM sales WHERE customer_id = ? ORDER BY id DESC LIMIT 20', [$edit['id']]); if ($cs): ?>
        <div class="small muted" style="margin-top:6px">Vendas deste cliente (admin pode editar):</div>
        <?php foreach ($cs as $x): ?><a class="btn ghost sm" style="display:flex;justify-content:space-between" href="<?= e(url('vendas', ['edit' => $x['id']])) ?>"><span>#<?= (int) $x['id'] ?> · <?= e(fmt_date($x['created_at'])) ?></span><b><?= money($x['total']) ?></b></a><?php endforeach; ?>
      <?php endif; endif; ?>
      <?php if ($edit): ?><a class="btn ghost block" href="<?= e(url('crm')) ?>" style="font-size:14px;padding:11px">Cancelar edicao</a><?php endif; ?>
    </div>
  </form>
  <div class="card"><h2>Clientes</h2>
    <form class="filters" method="get" style="margin-top:12px;grid-template-columns:1fr auto"><input type="hidden" name="page" value="crm"><input name="q" placeholder="Buscar por nome, telefone, CPF/CNPJ, e-mail ou carro" value="<?= e($q) ?>"><button class="btn" type="submit">Buscar</button></form>
    <div class="scroll"><table><thead><tr><th>Cliente</th><th>Contato</th><th class="num">LTV</th><th class="num">Compras</th><th>Ultima compra</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $r): $wa = whatsapp_link($r['phone']); $days = $r['last_purchase'] ? (int) ((time() - strtotime($r['last_purchase'])) / 86400) : null; ?>
      <tr><td><b><?= e(trim($r['first_name'] . ' ' . $r['last_name'])) ?></b><br><span class="tag green">Cliente</span><?php if ($r['car']): ?><span class="tag"><?= e($r['car']) ?></span><?php endif; ?></td>
        <td><?php if ($wa): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= e($r['phone']) ?> · WhatsApp</a><?php else: ?>-<?php endif; ?><br><span class="small muted"><?= e($r['email'] ?: 'sem e-mail') ?></span></td>
        <td class="num"><b><?= money($r['ltv']) ?></b></td><td class="num"><?= (int) $r['purchases'] ?></td>
        <td><?= e(fmt_date($r['last_purchase'])) ?><?php if ($days !== null): ?><br><span class="small muted">ha <?= $days ?>d</span><?php endif; ?></td>
        <td class="actions"><a class="btn ghost sm" href="<?= e(url('crm', ['history' => $r['id']])) ?>">Historico</a><?php if (App\Auth::isAdmin()): ?><a class="btn ghost sm" href="<?= e(url('crm', ['edit' => $r['id']])) ?>#form">Editar</a>
          <form method="post" onsubmit="return confirm('Excluir este cliente?')"><?= App\Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn danger sm" type="submit">Excluir</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="muted">Nenhum cliente encontrado.</td></tr><?php endif; ?></tbody></table></div>
    <?php $pages = (int) ceil($count / $per); if ($pages > 1): ?><div class="pager"><?php if ($pg > 1): ?><a class="btn ghost sm" href="<?= e(url('crm', ['q' => $q, 'p' => $pg - 1])) ?>">Anterior</a><?php endif; ?><span class="small">Pagina <?= $pg ?> de <?= $pages ?></span><?php if ($pg < $pages): ?><a class="btn ghost sm" href="<?= e(url('crm', ['q' => $q, 'p' => $pg + 1])) ?>">Proxima</a><?php endif; ?></div><?php endif; ?>
  </div>
</div>
