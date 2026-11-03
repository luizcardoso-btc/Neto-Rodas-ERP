<?php
use App\Database;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('err', 'Informe o nome do vendedor.');
        } else {
            Database::insert(
                'INSERT INTO sellers (name, weekly_pay, access_token, color) VALUES (?,?,?,?)',
                [$name, parse_money($_POST['weekly_pay'] ?? 0), bin2hex(random_bytes(20)), preg_match('/^#[0-9a-f]{6}$/i', $_POST['color'] ?? '') ? $_POST['color'] : '#ffd21a']
            );
            flash('ok', "Vendedor $name cadastrado.");
        }
    } elseif ($action === 'update') {
        Database::run(
            'UPDATE sellers SET name = ?, weekly_pay = ?, active = ? WHERE id = ?',
            [trim((string) $_POST['name']), parse_money($_POST['weekly_pay'] ?? 0), empty($_POST['active']) ? 0 : 1, (int) $_POST['id']]
        );
        flash('ok', 'Vendedor atualizado.');
    } elseif ($action === 'new_token') {
        Database::run('UPDATE sellers SET access_token = ? WHERE id = ?', [bin2hex(random_bytes(20)), (int) $_POST['id']]);
        flash('ok', 'Novo link gerado. O link antigo deixou de funcionar.');
    }
    redirect(url('vendedores'));
}

$sellers = Database::all('SELECT * FROM sellers ORDER BY active DESC, name');

// ---- Ranking gamificado do mes: 1 XP a cada R$ 10 vendidos + 20 XP por venda.
$month = preg_match('/^\d{4}-\d{2}$/', $_GET['m'] ?? '') ? $_GET['m'] : date('Y-m');
$months = [];
for ($k = 0; $k < 12; $k++) {
    $months[] = date('Y-m', strtotime("first day of -$k month"));
}
$levels = [[0, 'Iniciante', '#9ca3af'], [150, 'Bronze', '#b4783c'], [400, 'Prata', '#94a3b8'], [800, 'Ouro', '#e0b400'], [1500, 'Diamante', '#38bdf8']];
$rank = Database::all(
    "SELECT sel.id, sel.name, COUNT(s.id) n, COALESCE(SUM(s.total),0) total FROM sellers sel
     LEFT JOIN sales s ON s.seller_id = sel.id AND DATE_FORMAT(s.created_at,'%Y-%m') = ?
     WHERE sel.active = 1 GROUP BY sel.id, sel.name ORDER BY total DESC, n DESC, sel.name",
    [$month]
);
foreach ($rank as &$r) {
    $r['xp'] = (int) floor($r['total'] / 10) + 20 * (int) $r['n'];
    $r['ticket'] = $r['n'] ? $r['total'] / $r['n'] : 0;
    $lv = 0;
    foreach ($levels as $i => $l) {
        if ($r['xp'] >= $l[0]) {
            $lv = $i;
        }
    }
    $r['lv'] = $lv;
    $next = $levels[$lv + 1] ?? null;
    $r['pct'] = $next ? min(100, ($r['xp'] - $levels[$lv][0]) / ($next[0] - $levels[$lv][0]) * 100) : 100;
    $r['to_next'] = $next ? $next[0] - $r['xp'] : 0;
}
unset($r);
$top = array_filter($rank, fn ($r) => $r['n'] > 0);
$badge = [];
if ($top) {
    $byN = $top; usort($byN, fn ($a, $b) => $b['n'] <=> $a['n']); $badge[$byN[0]['id']][] = '⚡ Mais vendas';
    $byT = $top; usort($byT, fn ($a, $b) => $b['ticket'] <=> $a['ticket']); $badge[$byT[0]['id']][] = '💎 Maior ticket';
    $badge[reset($top)['id']][] = '👑 Líder do mês';
}
$medal = ['🥇', '🥈', '🥉'];
$base = (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
?>
<section class="hero slim"><div><h1>Vendedores</h1><p>Cada vendedor tem um link pessoal para registrar vendas pelo celular, sem precisar de senha. A folha semanal entra no lucro do dashboard.</p></div></section>
<section class="card rank-board">
  <div class="actions" style="justify-content:space-between;align-items:center;margin-bottom:6px">
    <div><h2>🏆 Ranking de vendedores</h2><p class="muted small" style="margin:2px 0 0">1 XP a cada R$ 10 vendidos + 20 XP por venda. Suba de nível vendendo mais!</p></div>
    <form method="get"><input type="hidden" name="page" value="vendedores"><select name="m" onchange="this.form.submit()" aria-label="Mês" style="width:auto;border-radius:999px"><?php foreach ($months as $m): ?><option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= date('m/Y', strtotime($m . '-01')) ?></option><?php endforeach; ?></select></form>
  </div>
  <?php if (!$rank): ?><p class="muted">Cadastre vendedores para ver o ranking.</p><?php else: ?>
  <?php $pod = array_slice($rank, 0, 3); $order = count($pod) === 3 ? [1, 0, 2] : array_keys($pod); ?>
  <div class="podium">
    <?php foreach ($order as $i): $r = $pod[$i]; $lv = $levels[$r['lv']]; ?>
      <div class="pod p<?= $i + 1 ?>">
        <div class="pod-medal"><?= $r['n'] ? $medal[$i] : '•' ?></div>
        <div class="pod-name"><?= e($r['name']) ?></div>
        <div class="pod-total"><?= money($r['total']) ?></div>
        <span class="lvl" style="background:<?= e($lv[2]) ?>"><?= e($lv[1]) ?></span>
        <div class="pod-base"><?= $i + 1 ?>º</div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php foreach ($rank as $i => $r): $lv = $levels[$r['lv']]; $nx = $levels[$r['lv'] + 1] ?? null; ?>
    <div class="rk-row">
      <div class="rk-pos"><?= $i < 3 && $r['n'] ? $medal[$i] : ($i + 1) . 'º' ?></div>
      <div class="rk-main">
        <div class="rk-top"><b><?= e($r['name']) ?></b> <span class="lvl" style="background:<?= e($lv[2]) ?>"><?= e($lv[1]) ?></span>
          <?php foreach ($badge[$r['id']] ?? [] as $b): ?><span class="bdg"><?= $b ?></span><?php endforeach; ?></div>
        <div class="xpbar"><i style="width:<?= round($r['pct']) ?>%;background:<?= e($lv[2]) ?>"></i></div>
        <small class="muted"><?= (int) $r['xp'] ?> XP<?= $nx ? ' · faltam ' . (int) $r['to_next'] . ' XP para ' . e($nx[1]) : ' · nível máximo!' ?></small>
      </div>
      <div class="rk-nums"><b><?= money($r['total']) ?></b><small><?= (int) $r['n'] ?> venda(s) · ticket <?= money($r['ticket']) ?></small></div>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>
</section>
<div class="cols">
  <form class="card" method="post">
    <?= App\Csrf::field() ?><input type="hidden" name="action" value="create">
    <h2>Novo vendedor</h2>
    <div class="stack" style="margin-top:12px">
      <input name="name" placeholder="Nome" required>
      <input name="weekly_pay" placeholder="Pagamento semanal (R$)" inputmode="decimal">
      <div><label class="f" for="color">Cor no grafico</label><input id="color" name="color" type="color" value="#ffd21a" style="height:44px;padding:4px"></div>
      <button class="btn green" type="submit">Cadastrar</button>
    </div>
  </form>
  <div class="card">
    <h2>Equipe</h2>
    <?php if (!$sellers): ?><p class="muted">Nenhum vendedor ainda. Cadastre o primeiro ao lado.</p><?php endif; ?>
    <?php foreach ($sellers as $s): $link = $base . '/index.php?page=pdv_mobile&t=' . $s['access_token']; ?>
      <form method="post" class="item-line">
        <?= App\Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
        <div class="grid" style="grid-template-columns:2fr 1fr auto">
          <input name="name" value="<?= e($s['name']) ?>" aria-label="Nome">
          <input name="weekly_pay" value="<?= e(number_format((float) $s['weekly_pay'], 2, ',', '.')) ?>" aria-label="Pagamento semanal">
          <label class="small" style="align-self:center"><input type="checkbox" name="active" value="1" style="width:auto" <?= $s['active'] ? 'checked' : '' ?>> ativo</label>
        </div>
        <div class="line-foot">
          <input readonly value="<?= e($link) ?>" onclick="this.select()" aria-label="Link do PDV mobile" style="max-width:70%;font-size:12px">
          <span class="actions">
            <button class="btn ghost sm" name="action" value="new_token" type="submit" onclick="return confirm('Gerar novo link? O atual para de funcionar.')">Novo link</button>
            <button class="btn sm" name="action" value="update" type="submit">Salvar</button>
          </span>
        </div>
      </form>
    <?php endforeach; ?>
  </div>
</div>
