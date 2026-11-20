<?php
use App\Database;

// Biblioteca de carros: fotos PNG sem fundo por angulo. Admin cadastra carros, envia fotos e salva a posicao das rodas.
const PV_ANGULOS = ['Lateral direita', '3/4 traseira direita', 'Traseira', '3/4 traseira esquerda', 'Lateral esquerda', '3/4 frente esquerda', 'Frente', '3/4 frente direita'];
// Chamadas do provador feitas pela pagina (respondem JSON ou a imagem recortada)
$pvAct = (string) ($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pvAct === 'cutout') {
    $pvJson = function (array $a, int $code = 200): never {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($a, JSON_UNESCAPED_UNICODE);
        exit;
    };
    try {
        if ($pvAct === 'cutout') {
            // remove o fundo no servidor (mesmo modelo do navegador, mas funciona em qualquer celular)
            $f = $_FILES['foto'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK || !@getimagesize($f['tmp_name'])) {
                throw new DomainException('A foto nao chegou ao servidor.');
            }
            $in = tempnam(sys_get_temp_dir(), 'pv');
            move_uploaded_file($f['tmp_name'], $in);
            $out = $in . '.png';
            $cmd = 'timeout 90 python3 ' . escapeshellarg(__DIR__ . '/../bin/cutout.py') . ' ' . escapeshellarg($in) . ' ' . escapeshellarg($out) . ' 2>&1';
            $lines = [];
            exec($cmd, $lines, $rc);
            @unlink($in);
            if ($rc !== 0 || !is_file($out)) {
                @unlink($out);
                throw new DomainException('Removedor de fundo do servidor indisponivel: ' . mb_substr(implode(' ', $lines), -200));
            }
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: image/png');
            header('X-Box: ' . preg_replace('/[^0-9,]/', '', (string) end($lines)));
            readfile($out);
            @unlink($out);
            exit;
        }
    } catch (DomainException $ex) {
        $pvJson(['ok' => false, 'error' => $ex->getMessage()], 422);
    }
}

$pvAjax = ($_POST['action'] ?? '') === 'car_img_wheels';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_starts_with((string) ($_POST['action'] ?? ''), 'car_')) {
    $id = (int) ($_POST['id'] ?? 0);
    $back = url('provador', ['carro' => $id]);
    try {
        if (!App\Auth::isAdmin()) {
            throw new DomainException('Somente o administrador pode alterar a biblioteca de carros.');
        }
        $action = (string) $_POST['action'];
        if ($action === 'car_new') {
            $brand = upper((string) ($_POST['brand'] ?? ''));
            $model = upper((string) ($_POST['model'] ?? ''));
            $type = in_array($_POST['body_type'] ?? '', ['hatch', 'sedan', 'suv', 'picape'], true) ? $_POST['body_type'] : 'hatch';
            $tire = preg_replace('#\s*R#i', ' R', strtoupper(trim((string) ($_POST['tire'] ?? ''))));
            if ($brand === '' || $model === '' || !preg_match('#^\d{3}/\d{2} R\d{2}$#', $tire)) {
                throw new DomainException('Informe marca, modelo e o pneu original no formato 185/60 R15.');
            }
            if (Database::value('SELECT id FROM car_models WHERE brand = ? AND model = ?', [$brand, $model])) {
                throw new DomainException('Esse carro ja esta na biblioteca.');
            }
            $id = Database::insert('INSERT INTO car_models (brand, model, body_type, length_mm, width_mm, height_mm, wheelbase_mm, tire) VALUES (?,?,?,4200,1750,1500,2600,?)', [$brand, $model, $type, $tire]);
            $back = url('provador', ['carro' => $id]);
            flash('ok', "$brand $model cadastrado. Agora envie as fotos dele.");
        } else {
            $car = Database::one('SELECT * FROM car_models WHERE id = ?', [$id]);
            if (!$car) {
                throw new DomainException('Carro nao encontrado.');
            }
            if ($action === 'car_edit') {
                $tire = preg_replace('#\s*R#i', ' R', strtoupper(trim((string) ($_POST['tire'] ?? ''))));
                if (!preg_match('#^\d{3}/\d{2} R\d{2}$#', $tire)) {
                    throw new DomainException('Pneu original no formato 185/60 R15.');
                }
                Database::run('UPDATE car_models SET brand = ?, model = ?, tire = ? WHERE id = ?', [upper((string) $_POST['brand']) ?: $car['brand'], upper((string) $_POST['model']) ?: $car['model'], $tire, $id]);
                flash('ok', 'Carro atualizado.');
            } elseif ($action === 'car_img_upload') {
                $files = $_FILES['imgs'] ?? null;
                if (!$files || !is_array($files['name'])) {
                    throw new DomainException('Escolha as fotos do carro (PNG sem fundo).');
                }
                $dir = __DIR__ . '/../public/uploads/cars';
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                $next = (int) Database::value('SELECT COALESCE(MAX(sort), -1) + 1 FROM car_images WHERE car_id = ?', [$id]);
                $ok = 0;
                foreach ($files['name'] as $i => $name) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK || $files['size'][$i] > 12 * 1024 * 1024) {
                        continue;
                    }
                    $info = @getimagesize($files['tmp_name'][$i]);
                    $ext = ['image/png' => 'png', 'image/webp' => 'webp', 'image/jpeg' => 'jpg'][$info['mime'] ?? ''] ?? null;
                    if (!$ext) {
                        continue;
                    }
                    $file = 'car-' . $id . '-' . time() . '-' . $i . '.' . $ext;
                    if (move_uploaded_file($files['tmp_name'][$i], $dir . '/' . $file)) {
                        $angle = PV_ANGULOS[($next + $ok) % count(PV_ANGULOS)];
                        Database::insert('INSERT INTO car_images (car_id, angle, sort, path) VALUES (?,?,?,?)', [$id, $angle, $next + $ok, 'uploads/cars/' . $file]);
                        $ok++;
                    }
                }
                if (!$ok) {
                    throw new DomainException('Nenhuma foto enviada. Use PNG, WEBP ou JPG de ate 12 MB.');
                }
                flash('ok', "$ok foto(s) adicionada(s) ao {$car['model']}. Confira o angulo de cada uma.");
            } elseif ($action === 'car_img_angle') {
                $angle = in_array($_POST['angle'] ?? '', PV_ANGULOS, true) ? $_POST['angle'] : 'Lateral direita';
                Database::run('UPDATE car_images SET angle = ?, sort = ? WHERE id = ? AND car_id = ?', [$angle, array_search($angle, PV_ANGULOS, true), (int) $_POST['img'], $id]);
                flash('ok', 'Angulo atualizado.');
            } elseif ($action === 'car_img_delete') {
                $img = Database::one('SELECT * FROM car_images WHERE id = ? AND car_id = ?', [(int) $_POST['img'], $id]);
                if ($img) {
                    if (is_file(__DIR__ . '/../public/' . $img['path'])) {
                        @unlink(__DIR__ . '/../public/' . $img['path']);
                    }
                    Database::run('DELETE FROM car_images WHERE id = ?', [$img['id']]);
                }
                flash('ok', 'Foto removida.');
            } elseif ($action === 'car_img_wheels') {
                $w = json_decode((string) ($_POST['wheels'] ?? ''), true);
                if (!is_array($w) || !isset($w['list']) || !is_array($w['list']) || count($w['list']) > 4) {
                    throw new DomainException('Posicao das rodas invalida.');
                }
                $clean = ['on' => !empty($w['on']), 'list' => array_map(fn ($r) => ['x' => (float) $r['x'], 'y' => (float) $r['y'], 'r' => (float) $r['r'], 'sq' => (float) ($r['sq'] ?? 1)], $w['list'])];
                Database::run('UPDATE car_images SET wheels = ? WHERE id = ? AND car_id = ?', [json_encode($clean), (int) $_POST['img'], $id]);
                header('Content-Type: application/json');
                echo json_encode(['ok' => true]);
                exit;
            }
        }
    } catch (DomainException $ex) {
        if ($pvAjax) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
            exit;
        }
        flash('err', $ex->getMessage());
    }
    redirect($back);
}
$cars = Database::all('SELECT * FROM car_models WHERE active = 1 ORDER BY brand, model');
$imgs = [];
foreach (Database::all('SELECT * FROM car_images ORDER BY car_id, sort, id') as $im) {
    $imgs[(int) $im['car_id']][] = ['id' => (int) $im['id'], 'angulo' => $im['angle'], 'src' => $im['path'], 'rodas' => $im['wheels'] ? json_decode($im['wheels'], true) : null];
}
$carsJs = array_map(fn ($c) => [
    'id' => (int) $c['id'], 'marca' => $c['brand'], 'modelo' => $c['model'], 'tipo' => $c['body_type'], 'pneu' => $c['tire'],
    'fotos' => $imgs[(int) $c['id']] ?? [],
], $cars);
$isAdmin = App\Auth::isAdmin();

// Rodas do estoque com foto, para o cliente escolher pela referencia da loja
$rodas = Database::all(
    "SELECT id, name, brand, rim, price, stock, condition_type, image_path FROM products
     WHERE active = 1 AND category = 'roda' AND COALESCE(image_path, '') <> ''
     ORDER BY (stock > 0) DESC, rim, name LIMIT 300"
);
$rodasJs = array_map(fn ($r) => [
    'id' => (int) $r['id'], 'nome' => $r['name'], 'marca' => (string) $r['brand'], 'aro' => (int) digits((string) $r['rim']) ?: null,
    'preco' => (float) $r['price'], 'estoque' => (int) $r['stock'], 'cond' => $r['condition_type'], 'img' => $r['image_path'],
], $rodas);
// rodas do catalogo: fotos ja limpas que ficam no proprio sistema (public/rodas), aparecem primeiro na busca
foreach (array_reverse(Database::all('SELECT * FROM rim_catalog WHERE active = 1 ORDER BY rim, name')) as $c) {
    array_unshift($rodasJs, [
        'id' => 'c' . $c['id'], 'nome' => $c['name'], 'marca' => (string) $c['brand'], 'aro' => (int) $c['rim'],
        'preco' => null, 'estoque' => null, 'cond' => 'novo', 'img' => $c['path'], 'catalogo' => true,
    ]);
}
?>
<section class="hero slim"><div><h1>Provador de rodas</h1><p>Escolha o carro, a roda e a cor, e apresente para o cliente na plataforma.</p></div></section>

<div class="pv" id="pv">
  <div class="pv-stage" id="pv-stage">
    <canvas id="pv-c" aria-label="Apresentação do carro com a roda escolhida. Arraste para girar."></canvas>
    <div class="pv-busy" id="pv-busy" hidden><div class="pv-spin"></div><p id="pv-busy-t">Carregando...</p></div>
    <div class="pv-empty" id="pv-empty">
      <h2>Escolha o carro</h2>
      <p>Selecione o modelo ao lado. Depois escolha a roda e a cor.</p>
    </div>
    <div class="pv-tools" id="pv-tools" hidden>
      <button class="chip on" type="button" id="pv-play">Apresentação</button>
      <button class="chip" type="button" id="pv-antes">Antes</button>
      <?php if ($isAdmin): ?><button class="chip" type="button" id="pv-ajustar">Ajustar rodas</button>
      <button class="chip" type="button" id="pv-salvar-rodas" hidden>Salvar posição das rodas</button><?php endif; ?>
      <button class="chip" type="button" id="pv-save">Gerar imagem</button>
    </div>
    <div class="pv-info" id="pv-info" hidden></div>
  </div>

  <aside class="pv-side">
    <div class="card">
      <h2>Carro</h2>
      <input id="pv-cq" type="search" placeholder="Buscar: HB20, Gol, Compass, Hilux..." aria-label="Buscar carro" style="margin-top:10px">
      <div class="pv-cars" id="pv-cars"></div>
      <?php if ($isAdmin): ?>
      <details class="p3-admin" id="pv-admin"><summary class="small">Biblioteca: fotos e cadastro de carros</summary>
        <div id="pv-admin-car" hidden>
          <form method="post" enctype="multipart/form-data" class="stack" style="margin-top:10px"><?= App\Csrf::field() ?><input type="hidden" name="action" value="car_img_upload"><input type="hidden" name="id" class="pv-car-id">
            <label class="birth"><span>Adicionar fotos PNG sem fundo de <b class="pv-car-nome"></b> (pode escolher várias, na ordem do giro)</span><input type="file" name="imgs[]" accept="image/png,image/webp,image/jpeg" multiple required></label>
            <button class="btn sm" type="submit">Enviar fotos</button>
          </form>
          <div class="pv-admin-imgs" id="pv-admin-imgs"></div>
          <form method="post" class="stack" style="margin-top:10px" id="pv-car-edit"><?= App\Csrf::field() ?><input type="hidden" name="action" value="car_edit"><input type="hidden" name="id" class="pv-car-id">
            <div class="row c2"><input name="brand" placeholder="Marca" aria-label="Marca"><input name="model" placeholder="Modelo" aria-label="Modelo"></div>
            <label class="birth"><span>Pneu original (define os pneus de cada aro)</span><input name="tire" placeholder="185/60 R15"></label>
            <button class="btn ghost sm" type="submit">Salvar dados do carro</button>
          </form>
        </div>
        <form method="post" class="stack" style="margin-top:14px;border-top:1px dashed var(--line,#e5e7eb);padding-top:10px"><?= App\Csrf::field() ?><input type="hidden" name="action" value="car_new">
          <b class="small">Cadastrar carro novo</b>
          <div class="row c2"><input name="brand" placeholder="Marca (ex.: Hyundai)" required aria-label="Marca"><input name="model" placeholder="Modelo (ex.: HB20)" required aria-label="Modelo"></div>
          <div class="row c2"><select name="body_type" aria-label="Tipo"><option value="hatch">Hatch</option><option value="sedan">Sedã</option><option value="suv">SUV</option><option value="picape">Picape</option></select><input name="tire" placeholder="Pneu original 185/60 R15" required aria-label="Pneu original"></div>
          <button class="btn sm" type="submit">Cadastrar</button>
        </form>
      </details>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Roda</h2>
      <input id="pv-q" type="search" placeholder="Buscar roda (nome, marca, aro)" aria-label="Buscar roda" style="margin-top:10px">
      <div class="pv-rodas" id="pv-rodas"></div>
    </div>

    <div class="card">
      <h2>Aro</h2>
      <div class="pv-opts" id="pv-aros"></div>
      <p class="small muted" id="pv-aro-t" style="margin:8px 0 0"></p>
    </div>

    <div class="card">
      <h2>Cor do carro</h2>
      <div class="pv-cores" id="pv-cores"></div>
    </div>
  </aside>
</div>

<div class="pv-modal" id="pv-out" hidden role="dialog" aria-modal="true" aria-label="Imagem para enviar">
  <div class="pv-box"><img id="pv-out-img" alt="Carro do cliente com a roda simulada">
    <p class="small muted">No celular, toque e segure a imagem para salvar ou compartilhar no WhatsApp. No computador, clique com o botão direito e escolha "Salvar imagem".</p>
    <button class="btn" type="button" id="pv-out-x">Fechar</button></div>
</div>

<script>window.PV_CARS = <?= json_encode($carsJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>; window.PV_CAR_SEL = <?= (int) ($_GET['carro'] ?? 0) ?>; window.PV_ANGULOS = <?= json_encode(PV_ANGULOS, JSON_UNESCAPED_UNICODE) ?>; window.PV_CSRF = <?= json_encode(App\Csrf::token()) ?>; window.PV_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;</script>
<script>window.PV_RODAS = <?= json_encode($rodasJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script src="vendor/ort/ort.wasm.min.js"></script>
<script src="<?= e(asset('provador.js')) ?>"></script>
