<?php
use App\Database;

// Biblioteca de carros: fotos PNG sem fundo por angulo. Admin cadastra carros, envia fotos e salva a posicao das rodas.
const PV_ANGULOS = ['Lateral direita', '3/4 traseira direita', 'Traseira', '3/4 traseira esquerda', 'Lateral esquerda', '3/4 frente esquerda', 'Frente', '3/4 frente direita'];
// Foto do carro do cliente: fica no banco (nao se perde quando o sistema atualiza)
if (isset($_GET['cimg'])) {
    $im = App\CustomerCars::image((int) $_GET['cimg']);
    while (ob_get_level()) {
        ob_end_clean();
    }
    if (!$im) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $im['mime']);
    header('Cache-Control: private, max-age=31536000, immutable');
    echo $im['data'];
    exit;
}

// Chamadas do provador feitas pela pagina (respondem JSON ou a imagem recortada)
$pvAct = (string) ($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($pvAct, ['cutout', 'cust_save', 'cust_update', 'cust_wheels', 'cust_delete'], true)) {
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
        if ($pvAct === 'cust_save') {
            $images = [];
            $files = $_FILES['imgs'] ?? ['name' => []];
            foreach ((array) $files['name'] as $i => $n) {
                if (($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK) {
                    continue;
                }
                $info = @getimagesize($files['tmp_name'][$i]);
                $images[] = [
                    'data' => (string) file_get_contents($files['tmp_name'][$i]), 'mime' => $info['mime'] ?? '',
                    'angle' => (string) ($_POST['angles'][$i] ?? 'Lateral direita'),
                    'wheels' => json_decode((string) ($_POST['wheels'][$i] ?? ''), true) ?: null,
                ];
            }
            $id = App\CustomerCars::create($_POST, $images, (int) (App\Auth::user()['id'] ?? 0) ?: null);
            $car = array_values(array_filter(App\CustomerCars::all(), fn ($c) => $c['cid'] === $id))[0] ?? null;
            $pvJson(['ok' => true, 'car' => $car]);
        }
        $cid = (int) ($_POST['id'] ?? 0);
        if ($pvAct === 'cust_update') {
            App\CustomerCars::update($cid, $_POST);
            $car = array_values(array_filter(App\CustomerCars::all(), fn ($c) => $c['cid'] === $cid))[0] ?? null;
            $pvJson(['ok' => true, 'car' => $car]);
        }
        if ($pvAct === 'cust_wheels') {
            App\CustomerCars::setWheels($cid, (int) ($_POST['img'] ?? 0), json_decode((string) ($_POST['wheels'] ?? ''), true) ?: []);
            $pvJson(['ok' => true]);
        }
        if ($pvAct === 'cust_delete') {
            if (!App\Auth::isAdmin()) {
                throw new DomainException('Somente o administrador pode apagar carros de clientes.');
            }
            App\CustomerCars::delete($cid);
            $pvJson(['ok' => true]);
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
$clientCarsJs = App\CustomerCars::all();

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
<section class="hero slim"><div><h1>Provador de rodas</h1><p>Escolha o carro do cliente na biblioteca (ou fotografe o carro dele) e mostre na plataforma 360° como fica com as nossas rodas.</p></div></section>

<div class="pv" id="pv">
  <div class="pv-stage" id="pv-stage">
    <canvas id="pv-c" aria-label="Carro na plataforma com a roda simulada. Arraste para girar."></canvas>
    <video id="pv-video" playsinline muted hidden></video>
    <div class="pv-guide" id="pv-guide" hidden><div class="pv-frame"></div><p id="pv-guide-t">Enquadre o carro de lado, com as duas rodas aparecendo</p></div>
    <div class="pv-busy" id="pv-busy" hidden><div class="pv-spin"></div><p id="pv-busy-t">Carregando...</p></div>
    <div class="pv-empty" id="pv-empty">
      <h2>Escolha o carro do cliente</h2>
      <p>Selecione o modelo na biblioteca ao lado, ou fotografe o carro do cliente.</p>
      <div class="pv-row">
        <button class="btn green" type="button" data-cam="1">Fotografar carro do cliente</button>
      </div>
    </div>
    <div class="pv-shoot" id="pv-shoot" hidden>
      <button class="btn ghost" type="button" id="pv-cancel">Fechar câmera</button>
      <button class="pv-shutter" type="button" id="pv-snap" aria-label="Fotografar"></button>
      <button class="btn ghost" type="button" id="pv-done" hidden>Concluir</button>
    </div>
    <div class="pv-tools" id="pv-tools" hidden>
      <button class="chip on" type="button" id="pv-girar">Girar 360°</button>
      <button class="chip on" type="button" id="pv-depois">Com a roda nova</button>
      <button class="chip" type="button" id="pv-antes">Original</button>
      <button class="chip" type="button" id="pv-ajustar">Ajustar rodas</button>
      <button class="chip" type="button" id="pv-salvar-rodas" hidden>Salvar posição das rodas</button>
      <button class="chip" type="button" id="pv-save">Gerar imagem</button>
    </div>
    <div class="pv-info" id="pv-info" hidden></div>
  </div>

  <aside class="pv-side">
    <div class="card">
      <h2>Carro</h2>
      <div class="pv-seg" id="pv-cseg" role="tablist"><button type="button" data-v="modelos" class="on">Modelos</button><button type="button" data-v="clientes">Carros de clientes <b id="pv-ccount"></b></button></div>
      <input id="pv-cq" type="search" placeholder="Buscar: HB20, Gol, Compass, Hilux..." aria-label="Buscar carro" style="margin-top:8px">
      <div class="pv-cars" id="pv-cars"></div>
      <p class="small muted" style="margin:8px 0 0">Ou <button type="button" class="linklike" data-cam="1">fotografar o carro do cliente</button> · <button type="button" class="linklike" data-cam="8">giro com 8 fotos</button> · <label for="pv-file" class="linklike" style="cursor:pointer">escolher fotos</label></p>
      <input type="file" id="pv-file" accept="image/*" capture="environment" multiple class="pv-file">
      <div class="pv-frames" id="pv-frames"></div>
      <div class="pv-clientinfo" id="pv-clientinfo" hidden></div>
      <form class="pv-savecar stack" id="pv-savecar" hidden autocomplete="off">
        <b>Salvar carro do cliente</b>
        <p class="small muted" style="margin:0">Fica no banco de carros, com a foto já sem fundo, para a próxima venda.</p>
        <div class="row c2"><input name="owner_name" placeholder="Nome do cliente" aria-label="Nome do cliente"><input name="phone" placeholder="WhatsApp" inputmode="tel" aria-label="WhatsApp"></div>
        <div class="row c2"><input name="brand" placeholder="Marca" required aria-label="Marca" list="pv-marcas"><input name="model" placeholder="Modelo" required aria-label="Modelo" list="pv-modelos"></div>
        <div class="row c2"><input name="color" placeholder="Cor" aria-label="Cor"><input name="plate" placeholder="Placa" aria-label="Placa" style="text-transform:uppercase"></div>
        <input name="tire" placeholder="Pneu atual (ex.: 185/60 R15)" aria-label="Pneu atual">
        <button class="btn green sm" type="submit">Salvar carro</button>
      </form>
      <datalist id="pv-marcas"></datalist><datalist id="pv-modelos"></datalist>
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
      <h2>Cor do carro</h2>
      <div class="pv-cores" id="pv-cores"></div>
      <p class="small muted" style="margin:8px 0 0">Simulação de pintura: mostra o carro do cliente em outra cor. Funciona melhor em carros claros.</p>
    </div>

    <div class="card">
      <h2>Aro</h2>
      <div class="pv-opts" id="pv-aros"></div>
      <p class="small muted" id="pv-aro-t" style="margin:8px 0 0"></p>
    </div>

    <div class="card">
      <h2>Roda escolhida</h2>
      <input id="pv-q" type="search" placeholder="Buscar roda do estoque (nome, marca, aro)" aria-label="Buscar roda do estoque" style="margin-top:10px">
      <div class="pv-rodas" id="pv-rodas"></div>
      <p class="small muted" style="margin:8px 0 0">Aparecem as rodas do estoque que têm foto. Sem foto? <label for="pv-rimfile" style="cursor:pointer;text-decoration:underline">use uma foto da roda</label> ou um desenho abaixo.</p>
      <input type="file" id="pv-rimfile" accept="image/*" capture="environment" class="pv-file">
      <div class="pv-opts c2" id="pv-sys" style="margin-top:10px"></div>
    </div>

    <div class="card">
      <h2>Comparar</h2>
      <div class="pv-ab"><button type="button" id="pv-a" class="on"><b>A</b><span id="pv-a-t">-</span></button><button type="button" id="pv-b"><b>B</b><span id="pv-b-t">-</span></button></div>
      <button class="chip" type="button" id="pv-alt" style="margin-top:8px;width:100%">Alternar A e B</button>
    </div>
  </aside>
</div>

<div class="pv-modal" id="pv-out" hidden role="dialog" aria-modal="true" aria-label="Imagem para enviar">
  <div class="pv-box"><img id="pv-out-img" alt="Carro do cliente com a roda simulada">
    <p class="small muted">No celular, toque e segure a imagem para salvar ou compartilhar no WhatsApp. No computador, clique com o botão direito e escolha "Salvar imagem".</p>
    <button class="btn" type="button" id="pv-out-x">Fechar</button></div>
</div>

<script>window.PV_CLIENT_CARS = <?= json_encode($clientCarsJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>; window.PV_CARS = <?= json_encode($carsJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>; window.PV_CAR_SEL = <?= (int) ($_GET['carro'] ?? 0) ?>; window.PV_ANGULOS = <?= json_encode(PV_ANGULOS, JSON_UNESCAPED_UNICODE) ?>; window.PV_CSRF = <?= json_encode(App\Csrf::token()) ?>; window.PV_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;</script>
<script>window.PV_RODAS = <?= json_encode($rodasJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script src="vendor/ort/ort.wasm.min.js"></script>
<script src="<?= e(asset('provador.js')) ?>"></script>
