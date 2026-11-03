<?php
use App\Database;

const CATEGORIES = ['pneu' => 'Pneu', 'roda' => 'Roda', 'peca' => 'Peca', 'servico' => 'Servico', 'outro' => 'Outro'];

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
  <div class="sd"><i class="dot green"></i><div><span>Em estoque (novos)</span><strong><?= $cnt['novo']['pneu'] + $cnt['novo']['roda'] ?></strong><small><?= $cnt['novo']['pneu'] ?> pneu(s) · <?= $cnt['novo']['roda'] ?> roda(s)</small></div></div>
  <div class="sd"><i class="dot red"></i><div><span>Usados (trocas)</span><strong><?= $cnt['usado']['pneu'] + $cnt['usado']['roda'] ?></strong><small><?= $cnt['usado']['pneu'] ?> pneu(s) · <?= $cnt['usado']['roda'] ?> roda(s)</small></div></div>
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
        <input name="name" placeholder="Nome do produto" required value="<?= $v('name') ?>">
        <textarea name="description" placeholder="Descricao"><?= $v('description') ?></textarea>
        <div class="row c2"><input name="gtin" placeholder="GTIN / EAN" value="<?= $v('gtin') ?>"><input name="mpn" placeholder="MPN / cod. fabricante" value="<?= $v('mpn') ?>"></div>
        <input name="google_category" placeholder="Categoria Google (ex: Veiculos e pecas > Pecas)" value="<?= $v('google_category') ?>">
        <div class="row c2">
          <select name="category" aria-label="Categoria"><?php foreach (CATEGORIES as $k => $l): ?><option value="<?= $k ?>" <?= ($edit['category'] ?? 'pneu') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
          <select name="condition_type" aria-label="Condicao"><option value="novo">Novo</option><option value="usado" <?= ($edit['condition_type'] ?? '') === 'usado' ? 'selected' : '' ?>>Usado</option></select>
        </div>
        <div class="row c2"><input name="brand" placeholder="Marca" value="<?= $v('brand') ?>"><input name="model" placeholder="Modelo" value="<?= $v('model') ?>"></div>
        <input name="compatible_cars" placeholder="Carros compativeis (separe por virgula)" value="<?= $v('compatible_cars') ?>">
        <div><label class="f" for="image">Imagem do produto</label><input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"></div>
        <div class="row c3"><input name="width" placeholder="Largura" value="<?= $v('width') ?>"><input name="profile" placeholder="Perfil" value="<?= $v('profile') ?>"><input name="rim" placeholder="Aro" value="<?= $v('rim') ?>"></div>
        <input name="location" placeholder="Local (ex: Prateleira A1)" value="<?= $v('location') ?>">
        <div class="row c2">
          <input name="cost_price" inputmode="decimal" placeholder="Preco custo" value="<?= $edit ? e(number_format((float) $edit['cost_price'], 2, ',', '')) : '' ?>">
          <input name="price" inputmode="decimal" placeholder="Preco de venda" value="<?= $edit ? e(number_format((float) $edit['price'], 2, ',', '')) : '' ?>">
        </div>
        <?php if (!$edit): ?><input name="initial_qty" type="number" min="0" placeholder="Quantidade inicial"><?php endif; ?>
        <button class="btn block" type="submit"><?= $edit ? 'Salvar alteracoes' : 'Salvar produto' ?></button>
        <?php if ($edit): ?><a class="btn ghost block" href="<?= e(url('estoque')) ?>" style="font-size:14px;padding:11px">Cancelar edicao</a><?php endif; ?>
      </div>
    </form>

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
    <form class="filters" method="get" style="margin-top:12px">
      <input type="hidden" name="page" value="estoque">
      <input name="q" placeholder="Buscar nome, marca, medida, GTIN, local" value="<?= e($q) ?>" style="grid-column:span 2">
      <select name="cat" aria-label="Categoria"><option value="">Todas as categorias</option><?php foreach (CATEGORIES as $k => $l): ?><option value="<?= $k ?>" <?= $cat === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
      <select name="cond" aria-label="Condicao"><option value="">Novo e usado</option><option value="novo" <?= $cond === 'novo' ? 'selected' : '' ?>>Novo</option><option value="usado" <?= $cond === 'usado' ? 'selected' : '' ?>>Usado</option></select>
      <button class="btn" type="submit">Filtrar</button>
    </form>
    <div class="scroll"><table>
      <thead><tr><th>Produto</th><th>Medida</th><th>Local</th><th class="num">Custo</th><th class="num">Preco</th><th class="num">Qtd</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($products as $p): ?>
        <tr style="<?= $p['active'] ? '' : 'opacity:.5' ?>">
          <td><b><?= e($p['name']) ?></b><br>
            <?php if ($p['brand']): ?><span class="tag"><?= e($p['brand']) ?></span><?php endif; ?>
            <span class="tag <?= $p['condition_type'] === 'usado' ? 'warn' : 'green' ?>"><?= e($p['condition_type']) ?></span>
            <?php if ($p['gtin']): ?><span class="small muted"><?= e($p['gtin']) ?></span><?php endif; ?></td>
          <td><?= e(trim(($p['width'] ?? '') . ($p['profile'] ? '/' . $p['profile'] : '') . ($p['rim'] ? ' R' . $p['rim'] : ''))) ?: '-' ?></td>
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
        <?php if ($pg > 1): ?><a class="btn ghost sm" href="<?= e(url('estoque', ['q' => $q, 'cat' => $cat, 'cond' => $cond, 'p' => $pg - 1])) ?>">Anterior</a><?php endif; ?>
        <span class="small">Pagina <?= $pg ?> de <?= $pages ?></span>
        <?php if ($pg < $pages): ?><a class="btn ghost sm" href="<?= e(url('estoque', ['q' => $q, 'cat' => $cat, 'cond' => $cond, 'p' => $pg + 1])) ?>">Proxima</a><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
