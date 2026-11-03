<?php
use App\Database;

const CATEGORIES = ['pneu' => 'Pneu', 'roda' => 'Roda', 'parafuso' => 'Parafuso', 'peca' => 'Peca', 'servico' => 'Servico', 'outro' => 'Outro'];

function save_image(): ?string
{
    if (empty($_FILES['image']['tmp_name']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($_FILES['image']['size'] > 3 * 1024 * 1024) {
        throw new DomainException('A imagem deve ter no maximo 3 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) {
        throw new DomainException('Use imagem JPG, PNG ou WebP.');
    }
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../public/uploads/' . $name)) {
        throw new DomainException('Nao foi possivel salvar a imagem.');
    }
    return 'uploads/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            if (($_POST['category'] ?? '') === 'roda') {
                // Roda segue o padrao unico: JOGO DE RODA <MARCA> R<ARO> - <ACABAMENTO> (por jogo de 4).
                $usedW = ($_POST['condition_type'] ?? '') === 'usado';
                $w = App\Wheel::normalize((string) ($_POST['wheel_brand'] ?? ''), (string) ($_POST['wheel_rim'] ?? ''), (string) ($_POST['wheel_finish'] ?? ''), $usedW);
                $dup = Database::value('SELECT id FROM products WHERE name = ? AND condition_type = ? AND id <> ? AND active = 1 LIMIT 1', [$w['name'], $usedW ? 'usado' : 'novo', $id]);
                if ($dup) {
                    throw new DomainException('Este jogo ja esta cadastrado (' . $w['name'] . '). Use o Ajuste de estoque para somar jogos.');
                }
                $_POST['name'] = $w['name'];
                $_POST['brand'] = $w['brand'];
                $_POST['model'] = $w['finish'];
                $_POST['rim'] = $w['rim'];
                $_POST['width'] = '';
                $_POST['profile'] = '';
                $_POST['description'] = '';
                $_POST['gtin'] = '';
                $_POST['mpn'] = '';
                $_POST['compatible_cars'] = '';
            }
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new DomainException('Informe o nome do produto.');
            }
            $cat = array_key_exists($_POST['category'] ?? '', CATEGORIES) ? $_POST['category'] : 'pneu';
            $cond = ($_POST['condition_type'] ?? '') === 'usado' ? 'usado' : 'novo';
            $t = fn (string $k, int $max = 200) => ($v = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max)) === '' ? null : $v;
            $vals = [
                $name, $t('description', 2000), $t('gtin', 20), $t('mpn', 60), $t('google_category'), $cat, $cond,
                $t('brand', 80), $t('model', 80), $t('compatible_cars', 1000), $t('width', 10), $t('profile', 10), $t('rim', 10),
                $t('location', 60), parse_money($_POST['cost_price'] ?? 0), parse_money($_POST['price'] ?? 0),
            ];
            $image = save_image();
            if ($id) {
                Database::run(
                    'UPDATE products SET name=?, description=?, gtin=?, mpn=?, google_category=?, category=?, condition_type=?, brand=?, model=?,
                     compatible_cars=?, width=?, profile=?, rim=?, location=?, cost_price=?, price=?' . ($image ? ', image_path=?' : '') . ' WHERE id=?',
                    array_merge($vals, $image ? [$image] : [], [$id])
                );
                flash('ok', 'Produto atualizado.');
            } else {
                $qty = max(0, (int) ($_POST['initial_qty'] ?? 0));
                $pid = Database::insert(
                    'INSERT INTO products (name, description, gtin, mpn, google_category, category, condition_type, brand, model, compatible_cars,
                     width, profile, rim, location, cost_price, price, image_path, stock) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    array_merge($vals, [$image, $qty])
                );
                if ($qty > 0) {
                    Database::insert("INSERT INTO stock_movements (product_id, type, quantity, reason) VALUES (?, 'in', ?, 'Estoque inicial')", [$pid, $qty]);
                }
                flash('ok', 'Produto cadastrado.');
            }
        } elseif ($action === 'adjust') {
            $pid = (int) ($_POST['product_id'] ?? 0);
            $delta = (int) ($_POST['delta'] ?? 0);
            if (!$pid || $delta === 0) {
                throw new DomainException('Escolha o produto e informe a variacao (ex.: 5 ou -2).');
            }
            $pdo = Database::pdo();
            $pdo->beginTransaction();
            $cur = Database::one('SELECT stock FROM products WHERE id = ? FOR UPDATE', [$pid]);
            if (!$cur || $cur['stock'] + $delta < 0) {
                $pdo->rollBack();
                throw new DomainException('O ajuste deixaria o estoque negativo.');
            }
            Database::run('UPDATE products SET stock = stock + ? WHERE id = ?', [$delta, $pid]);
            Database::insert("INSERT INTO stock_movements (product_id, type, quantity, reason) VALUES (?, 'adjust', ?, ?)", [$pid, $delta, mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 200) ?: 'Ajuste manual']);
            $pdo->commit();
            flash('ok', 'Estoque ajustado.');
        } elseif ($action === 'toggle') {
            Database::run('UPDATE products SET active = 1 - active WHERE id = ?', [(int) $_POST['id']]);
            flash('ok', 'Produto atualizado.');
        }
    } catch (DomainException $e) {
        flash('err', $e->getMessage());
    }
    redirect(url('estoque'));
}

$q = trim((string) ($_GET['q'] ?? ''));
$cat = (string) ($_GET['cat'] ?? '');
$cond = (string) ($_GET['cond'] ?? '');
$GROUPS = ['pneu_novo' => ['Pneus novos', 'pneu', 'novo'], 'pneu_usado' => ['Pneus usados', 'pneu', 'usado'], 'roda_novo' => ['Rodas novas', 'roda', 'novo'], 'roda_usado' => ['Rodas usadas', 'roda', 'usado'], 'parafuso_novo' => ['Parafusos', 'parafuso', 'novo']];
$grp = (string) ($_GET['grp'] ?? '');
if (isset($GROUPS[$grp])) {
    [, $cat, $cond] = $GROUPS[$grp];
}
$where = ['1=1'];
$args = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = "(name LIKE ? OR brand LIKE ? OR model LIKE ? OR gtin LIKE ? OR mpn LIKE ? OR CONCAT_WS('/', width, profile, rim) LIKE ? OR location LIKE ?)";
    array_push($args, $like, $like, $like, $like, $like, $like, $like);
}
if (isset(CATEGORIES[$cat])) { $where[] = 'category = ?'; $args[] = $cat; }
if (in_array($cond, ['novo', 'usado'], true)) { $where[] = 'condition_type = ?'; $args[] = $cond; }
$w = implode(' AND ', $where);

$perPage = 40;
$total = (int) Database::value("SELECT COUNT(*) FROM products WHERE $w", $args);
$pg = max(1, (int) ($_GET['p'] ?? 1));
$products = Database::all("SELECT * FROM products WHERE $w ORDER BY active DESC, name LIMIT $perPage OFFSET " . (($pg - 1) * $perPage), $args);
$stats = Database::one('SELECT COUNT(*) n, COALESCE(SUM(stock),0) units, COALESCE(SUM(stock*cost_price),0) cost_value, COALESCE(SUM(stock*price),0) sale_value, SUM(stock <= 0 AND active=1) out_of_stock FROM products');
$tabCnt = [];
foreach (Database::all("SELECT category k, condition_type c, COUNT(*) n, COALESCE(SUM(stock),0) q FROM products WHERE active = 1 AND category IN ('pneu','roda','parafuso') GROUP BY category, condition_type") as $r) {
    $tabCnt[$r['k'] . '_' . $r['c']] = $r;
}
$edit = isset($_GET['edit']) ? Database::one('SELECT * FROM products WHERE id = ?', [(int) $_GET['edit']]) : null;
$allProducts = Database::all('SELECT id, name, stock FROM products WHERE active = 1 ORDER BY name LIMIT 500');
$v = fn (string $k, string $d = '') => e((string) ($edit[$k] ?? $d));
?>
<section class="hero slim"><div><h1>Estoque</h1><p>Cadastre produtos, acompanhe quantidades e corrija diferencas com ajuste manual. Toda movimentacao fica registrada.</p></div></section>

<?php
$cnt = ['novo' => ['pneu' => 0, 'roda' => 0], 'usado' => ['pneu' => 0, 'roda' => 0]];
foreach (Database::all("SELECT condition_type c, category k, COALESCE(SUM(stock),0) q FROM products WHERE active = 1 AND stock > 0 AND category IN ('pneu','roda') GROUP BY condition_type, category") as $r) {
    $cnt[$r['c']][$r['k']] = (int) $r['q'];
}
?>
<?php if (App\Auth::isAdmin()): ?><p class="small" style="text-align:right;margin:-6px 0 10px"><a class="muted" href="<?= e(url('zerar')) ?>">Zerar dados de teste</a></p><?php endif; ?>
<div class="stock-dots">
  <div class="sd"><i class="dot green"></i><div><span>Em estoque (novos)</span><strong><?= $cnt['novo']['pneu'] + $cnt['novo']['roda'] ?></strong><small><?= $cnt['novo']['pneu'] ?> pneu(s) · <?= $cnt['novo']['roda'] ?> jogo(s) de roda</small></div></div>
  <div class="sd"><i class="dot red"></i><div><span>Usados (trocas)</span><strong><?= $cnt['usado']['pneu'] + $cnt['usado']['roda'] ?></strong><small><?= $cnt['usado']['pneu'] ?> pneu(s) · <?= $cnt['usado']['roda'] ?> jogo(s) de roda</small></div></div>
</div>
<div class="kpis">
  <div class="kpi"><span>Produtos</span><strong><?= (int) $stats['n'] ?></strong><small><?= (int) $stats['units'] ?> unidades</small></div>
  <div class="kpi"><span>Valor a custo</span><strong><?= money($stats['cost_value']) ?></strong></div>
  <div class="kpi"><span>Valor a preco de venda</span><strong><?= money($stats['sale_value']) ?></strong></div>
  <div class="kpi"><span>Sem estoque</span><strong class="<?= $stats['out_of_stock'] ? 'bad' : '' ?>"><?= (int) $stats['out_of_stock'] ?></strong></div>
</div>

<div class="cols">
  <div>
    <form class="card" method="post" enctype="multipart/form-data" id="form">
      <?= App\Csrf::field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <h2><?= $edit ? 'Editar produto' : 'Novo produto' ?></h2>
      <div class="stack" style="margin-top:12px">
        <div class="row c2">
          <select name="category" id="f-cat" aria-label="Categoria"><?php foreach (CATEGORIES as $k => $l): ?><option value="<?= $k ?>" <?= ($edit['category'] ?? ($_GET['newcat'] ?? 'pneu')) === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
          <select name="condition_type" aria-label="Condicao"><option value="novo">Novo</option><option value="usado" <?= ($edit['condition_type'] ?? '') === 'usado' ? 'selected' : '' ?>>Usado</option></select>
        </div>

        <div id="f-wheel" class="stack" hidden>
          <p class="small muted" style="margin:0">Roda e cadastrada e vendida por <b>JOGO (4 unidades)</b>. O nome sai no padrao: <b>JOGO DE RODA KS R18 - DIAMANTADA PRATA</b>.</p>
          <div class="row c2">
            <div><label class="f" for="w-brand">Marca</label><input id="w-brand" name="wheel_brand" list="w-brands" placeholder="EX.: KS" value="<?= e(upper((string) ($edit['brand'] ?? ''))) ?>" autocomplete="off"><datalist id="w-brands"><?php foreach (App\Wheel::brands() as $b): ?><option value="<?= e($b) ?>"><?php endforeach; ?></datalist></div>
            <div><label class="f" for="w-rim">Aro</label><select id="w-rim" name="wheel_rim"><option value="">Aro</option><?php for ($r = App\Wheel::RIM_MIN; $r <= App\Wheel::RIM_MAX; $r++): ?><option value="<?= $r ?>" <?= (string) ($edit['rim'] ?? '') === (string) $r ? 'selected' : '' ?>>R<?= $r ?></option><?php endfor; ?></select></div>
          </div>
          <div><label class="f" for="w-finish">Acabamento</label><select id="w-finish" name="wheel_finish"><option value="">Escolha o acabamento</option><?php foreach (App\Wheel::FINISHES as $fn): ?><option value="<?= e($fn) ?>" <?= upper((string) ($edit['model'] ?? '')) === $fn ? 'selected' : '' ?>><?= e($fn) ?></option><?php endforeach; ?></select></div>
          <div class="card" style="margin:0;padding:10px 14px"><span class="small muted">Nome que sera usado</span><div id="w-preview" style="font-weight:800">-</div></div>
        </div>

        <div id="f-generic" class="stack">
        <input name="name" id="f-name" placeholder="Nome do produto" required value="<?= $v('name') ?>">
        <textarea name="description" placeholder="Descricao"><?= $v('description') ?></textarea>
        <div class="row c2"><input name="gtin" placeholder="GTIN / EAN" value="<?= $v('gtin') ?>"><input name="mpn" placeholder="MPN / cod. fabricante" value="<?= $v('mpn') ?>"></div>
        <input name="google_category" placeholder="Categoria Google (ex: Veiculos e pecas > Pecas)" value="<?= $v('google_category') ?>">
        <div class="row c2"><input name="brand" placeholder="Marca" value="<?= $v('brand') ?>"><input name="model" placeholder="Modelo" value="<?= $v('model') ?>"></div>
        <input name="compatible_cars" placeholder="Carros compativeis (separe por virgula)" value="<?= $v('compatible_cars') ?>">
        <div class="row c3"><input name="width" placeholder="Largura" value="<?= $v('width') ?>"><input name="profile" placeholder="Perfil" value="<?= $v('profile') ?>"><input name="rim" placeholder="Aro" value="<?= $v('rim') ?>"></div>
        </div>
        <div><label class="f" for="image">Imagem do produto</label><input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"></div>
        <input name="location" placeholder="Local (ex: Prateleira A1)" value="<?= $v('location') ?>">
        <div class="row c2">
          <input name="cost_price" id="f-cost" inputmode="decimal" placeholder="Preco custo" value="<?= $edit ? e(number_format((float) $edit['cost_price'], 2, ',', '')) : '' ?>">
          <input name="price" id="f-price" inputmode="decimal" placeholder="Preco de venda" value="<?= $edit ? e(number_format((float) $edit['price'], 2, ',', '')) : '' ?>">
        </div>
        <?php if (!$edit): ?><input name="initial_qty" id="f-qty" type="number" min="0" placeholder="Quantidade inicial"><?php endif; ?>
        <button class="btn block" type="submit"><?= $edit ? 'Salvar alteracoes' : 'Salvar produto' ?></button>
        <?php if ($edit): ?><a class="btn ghost block" href="<?= e(url('estoque')) ?>" style="font-size:14px;padding:11px">Cancelar edicao</a><?php endif; ?>
      </div>
    </form>

    <script>
    (function () {
      const cat = document.getElementById('f-cat'), cond = document.querySelector('[name=condition_type]');
      const wheel = document.getElementById('f-wheel'), gen = document.getElementById('f-generic'), name = document.getElementById('f-name');
      const brand = document.getElementById('w-brand'), rim = document.getElementById('w-rim'), fin = document.getElementById('w-finish');
      const cost = document.getElementById('f-cost'), price = document.getElementById('f-price'), qty = document.getElementById('f-qty'), prev = document.getElementById('w-preview');
      brand.addEventListener('input', () => { brand.value = brand.value.toUpperCase(); });
      function sync() {
        const w = cat.value === 'roda';
        if (cat.value === 'parafuso' && name && !name.value.trim()) name.value = 'JOGO DE PARAFUSOS 16 UNIDADES';
        if (qty) qty.placeholder = (w || cat.value === 'parafuso') ? 'Quantidade de JOGOS' : 'Quantidade inicial';
        wheel.hidden = !w; gen.hidden = w;
        gen.querySelectorAll('input,textarea').forEach((i) => { i.disabled = w; });
        brand.required = rim.required = fin.required = w;
        const jg = w ? ' do JOGO (4 rodas)' : (cat.value === 'parafuso' ? ' do JOGO (16 un.)' : '');
        cost.placeholder = 'Preco de custo' + jg;
        price.placeholder = 'Preco de venda' + jg;
        prev.textContent = !w ? '-' : (brand.value && rim.value && fin.value ? 'JOGO DE RODA' + (cond.value === 'usado' ? ' USADA' : '') + ' ' + brand.value.toUpperCase() + ' R' + rim.value + ' - ' + fin.value : 'Preencha marca, aro e acabamento');
      }
      [cat, cond, brand, rim, fin].forEach((i) => i.addEventListener('input', sync)); cat.addEventListener('change', sync); sync();
    })();
    </script>
    <form class="card" method="post" style="margin-top:20px">
      <?= App\Csrf::field() ?><input type="hidden" name="action" value="adjust">
      <h2>Ajuste de estoque</h2>
      <p class="sub">Use numero positivo para entrada e negativo para saida.</p>
      <div class="stack">
        <select name="product_id" required aria-label="Produto"><option value="">Selecione o produto</option><?php foreach ($allProducts as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> (<?= (int) $p['stock'] ?>)</option><?php endforeach; ?></select>
        <input name="delta" type="number" placeholder="Variacao (ex: 4 ou -1)" required>
        <input name="reason" placeholder="Observacao">
        <button class="btn warn block" type="submit" style="font-size:14px;padding:12px">Aplicar ajuste</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Produtos</h2>
    <div class="fin-tabs" style="margin:12px 0">
      <a class="pill <?= $grp === '' ? 'on' : '' ?>" href="<?= e(url('estoque', ['q' => $q])) ?>">Todos</a>
      <?php foreach ($GROUPS as $gk => [$gl, $gc, $gd]): $tc = $tabCnt[$gc . '_' . $gd] ?? ['n' => 0, 'q' => 0]; ?>
        <a class="pill <?= $grp === $gk ? 'on' : '' ?>" href="<?= e(url('estoque', ['grp' => $gk, 'q' => $q])) ?>"><span class="dot <?= $gd === 'usado' ? 'redd' : 'greend' ?>"></span><?= e($gl) ?> <b><?= (int) $tc['q'] ?></b></a>
      <?php endforeach; ?>
    </div>
    <form class="filters" method="get" style="margin-top:4px;grid-template-columns:1fr auto">
      <input type="hidden" name="page" value="estoque"><input type="hidden" name="grp" value="<?= e($grp) ?>">
      <input name="q" placeholder="Buscar nome, marca, medida, aro, local" value="<?= e($q) ?>">
      <button class="btn" type="submit">Buscar</button>
    </form>
    <div class="scroll"><table>
      <thead><tr><th>Produto</th><th>Medida</th><th>Local</th><th class="num">Custo</th><th class="num">Preco</th><th class="num"><?= in_array($cat, ['roda', 'parafuso'], true) ? 'Jogos' : 'Qtd' ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($products as $p): ?>
        <tr style="<?= $p['active'] ? '' : 'opacity:.5' ?>">
          <td><b><?= e($p['name']) ?></b><br>
            <?php if ($p['brand']): ?><span class="tag"><?= e($p['brand']) ?></span><?php endif; ?>
            <span class="tag <?= $p['condition_type'] === 'usado' ? 'warn' : 'green' ?>"><?= e($p['condition_type']) ?></span><?php if ($p['category'] === 'roda' && !str_starts_with((string) $p['name'], 'JOGO DE RODA ') && (!$p['model'] || !in_array(upper($p['model']), App\Wheel::FINISHES, true))): ?> <span class="tag red">fora do padrao: edite</span><?php endif; ?>
            <?php if ($p['gtin']): ?><span class="small muted"><?= e($p['gtin']) ?></span><?php endif; ?></td>
          <td><?= $p['category'] === 'roda' ? ($p['rim'] ? 'R' . e($p['rim']) : '-') : (e(trim(($p['width'] ?? '') . ($p['profile'] ? '/' . $p['profile'] : '') . ($p['rim'] ? ' R' . $p['rim'] : ''))) ?: '-') ?></td>
          <td><?= e($p['location'] ?? '-') ?></td>
          <td class="num"><?= money($p['cost_price']) ?></td>
          <td class="num"><?= money($p['price']) ?></td>
          <td class="num"><span class="tag <?= $p['stock'] <= 0 ? 'red' : ($p['stock'] < 4 ? 'warn' : 'green') ?>"><?= (int) $p['stock'] ?></span></td>
          <td class="actions">
            <a class="btn ghost sm" href="<?= e(url('estoque', ['edit' => $p['id']])) ?>#form">Editar</a>
            <form method="post" style="display:inline"><?= App\Csrf::field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn danger sm" type="submit"><?= $p['active'] ? 'Desativar' : 'Reativar' ?></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?><tr><td colspan="7" class="muted">Nenhum produto encontrado. Cadastre o primeiro no formulario.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
    <?php $pages = (int) ceil($total / $perPage); if ($pages > 1): ?>
      <div class="pager"><span class="muted small"><?= $total ?> produtos</span>
        <?php if ($pg > 1): ?><a class="btn ghost sm" href="<?= e(url('estoque', ['q' => $q, 'grp' => $grp, 'p' => $pg - 1])) ?>">Anterior</a><?php endif; ?>
        <span class="small">Pagina <?= $pg ?> de <?= $pages ?></span>
        <?php if ($pg < $pages): ?><a class="btn ghost sm" href="<?= e(url('estoque', ['q' => $q, 'grp' => $grp, 'p' => $pg + 1])) ?>">Proxima</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
