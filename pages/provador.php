<?php
use App\Database;

// Biblioteca de carros: admin pode corrigir medidas e anexar um modelo 3D real (.glb)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_starts_with((string) ($_POST['action'] ?? ''), 'car_')) {
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if (!App\Auth::isAdmin()) {
            throw new DomainException('Somente o administrador pode alterar a biblioteca de carros.');
        }
        $car = Database::one('SELECT * FROM car_models WHERE id = ?', [$id]);
        if (!$car) {
            throw new DomainException('Carro nao encontrado.');
        }
        if ($_POST['action'] === 'car_edit') {
            $tire = strtoupper(trim((string) ($_POST['tire'] ?? '')));
            if (!preg_match('#^\d{3}/\d{2} ?R\d{2}$#', $tire)) {
                throw new DomainException('Pneu original no formato 185/60 R15.');
            }
            $n = fn ($k) => max(500, min(7000, (int) ($_POST[$k] ?? 0)));
            Database::run('UPDATE car_models SET length_mm = ?, width_mm = ?, height_mm = ?, wheelbase_mm = ?, tire = ? WHERE id = ?',
                [$n('length_mm'), $n('width_mm'), $n('height_mm'), $n('wheelbase_mm'), preg_replace('#\s*R#', ' R', $tire), $id]);
            flash('ok', 'Medidas do ' . $car['model'] . ' atualizadas.');
        } elseif ($_POST['action'] === 'car_glb') {
            $f = $_FILES['glb'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
                throw new DomainException('Envie o arquivo .glb (ate 45 MB).');
            }
            if ($f['size'] > 45 * 1024 * 1024 || strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'glb' || file_get_contents($f['tmp_name'], false, null, 0, 4) !== 'glTF') {
                throw new DomainException('O arquivo precisa ser um modelo 3D .glb valido de ate 45 MB.');
            }
            $dir = __DIR__ . '/../public/uploads/cars';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $name = 'car-' . $id . '-' . time() . '.glb';
            if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
                throw new DomainException('Nao consegui salvar o arquivo.');
            }
            if ($car['glb_path'] && is_file(__DIR__ . '/../public/' . $car['glb_path'])) {
                @unlink(__DIR__ . '/../public/' . $car['glb_path']);
            }
            Database::run('UPDATE car_models SET glb_path = ? WHERE id = ?', ['uploads/cars/' . $name, $id]);
            flash('ok', 'Modelo 3D do ' . $car['model'] . ' enviado.');
        } elseif ($_POST['action'] === 'car_glb_remove') {
            if ($car['glb_path'] && is_file(__DIR__ . '/../public/' . $car['glb_path'])) {
                @unlink(__DIR__ . '/../public/' . $car['glb_path']);
            }
            Database::run('UPDATE car_models SET glb_path = NULL WHERE id = ?', [$id]);
            flash('ok', 'Modelo 3D removido: volta a usar o desenho do sistema.');
        }
    } catch (DomainException $ex) {
        flash('err', $ex->getMessage());
    }
    redirect(url('provador', ['carro' => $id]));
}
$cars = Database::all('SELECT * FROM car_models WHERE active = 1 ORDER BY body_type, brand, model');
$carsJs = array_map(fn ($c) => [
    'id' => (int) $c['id'], 'marca' => $c['brand'], 'modelo' => $c['model'], 'tipo' => $c['body_type'],
    'L' => (int) $c['length_mm'], 'W' => (int) $c['width_mm'], 'H' => (int) $c['height_mm'], 'E' => (int) $c['wheelbase_mm'],
    'pneu' => $c['tire'], 'glb' => $c['glb_path'],
], $cars);

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
?>
<section class="hero slim"><div><h1>Provador de rodas</h1><p>Escolha o carro do cliente na biblioteca (ou fotografe o carro dele) e mostre em 360° como fica com as nossas rodas.</p></div></section>

<div class="pv-tabs" role="tablist" aria-label="Modo do provador">
  <button type="button" role="tab" id="pv-tab-3d" class="on" aria-selected="true">Carro 3D (biblioteca)</button>
  <button type="button" role="tab" id="pv-tab-foto" aria-selected="false">Foto do cliente</button>
</div>

<div class="pv" id="p3">
  <div class="pv-stage" id="p3-stage">
    <canvas id="p3-c" aria-label="Carro em 3D. Arraste para girar 360 graus."></canvas>
    <div class="pv-info p3-spec" id="p3-spec"></div>
    <div class="pv-tools" id="p3-views">
      <button class="chip on" type="button" data-v="auto">Girar 360°</button>
      <button class="chip" type="button" data-v="tres">3/4 frente</button>
      <button class="chip" type="button" data-v="lado">Lateral</button>
      <button class="chip" type="button" data-v="tras">Traseira</button>
      <button class="chip" type="button" data-v="roda">Roda de perto</button>
    </div>
    <div class="pv-busy" id="p3-busy" hidden><div class="pv-spin"></div><p id="p3-busy-t">Carregando...</p></div>
  </div>
  <aside class="pv-side">
    <div class="card">
      <h2>Carro do cliente</h2>
      <input id="p3-q" type="search" placeholder="Buscar: HB20, Hilux, BYD, SUV..." aria-label="Buscar carro" style="margin-top:10px">
      <select id="p3-car" aria-label="Carro" style="margin-top:8px"></select>
      <div class="p3-cores" id="p3-cores" role="group" aria-label="Cor"></div>
      <p class="small muted" id="p3-car-t" style="margin:8px 0 0"></p>
      <?php if (App\Auth::isAdmin()): ?>
      <details class="p3-admin"><summary class="small">Corrigir medidas / modelo 3D real</summary>
        <form method="post" class="stack" id="p3-edit" style="margin-top:8px"><?= App\Csrf::field() ?><input type="hidden" name="action" value="car_edit"><input type="hidden" name="id" value="">
          <div class="row c2"><label class="birth"><span>Comprimento (mm)</span><input name="length_mm" type="number"></label><label class="birth"><span>Largura (mm)</span><input name="width_mm" type="number"></label></div>
          <div class="row c2"><label class="birth"><span>Altura (mm)</span><input name="height_mm" type="number"></label><label class="birth"><span>Entre-eixos (mm)</span><input name="wheelbase_mm" type="number"></label></div>
          <label class="birth"><span>Pneu original</span><input name="tire" placeholder="185/60 R15"></label>
          <button class="btn sm" type="submit">Salvar medidas</button>
        </form>
        <form method="post" enctype="multipart/form-data" class="stack" id="p3-glb" style="margin-top:12px"><?= App\Csrf::field() ?><input type="hidden" name="action" value="car_glb"><input type="hidden" name="id" value="">
          <label class="birth"><span>Modelo 3D real do carro (.glb, até 45 MB)</span><input type="file" name="glb" accept=".glb,model/gltf-binary" required></label>
          <button class="btn sm" type="submit">Enviar modelo 3D</button>
        </form>
        <form method="post" id="p3-glb-rm" style="margin-top:6px" hidden><?= App\Csrf::field() ?><input type="hidden" name="action" value="car_glb_remove"><input type="hidden" name="id" value=""><button class="linklike danger-t" type="submit">remover modelo 3D</button></form>
        <p class="small muted" style="margin:8px 0 0">Modelos 3D gratuitos (.glb) podem ser baixados em sites como o Sketchfab (filtro "downloadable", licença CC). O sistema acerta o tamanho pelas medidas acima e troca as rodas dele pelas nossas.</p>
      </details>
      <?php endif; ?>
    </div>
    <div class="card">
      <h2>Aro</h2>
      <div class="pv-opts" id="p3-aros"></div>
      <p class="small muted" id="p3-aro-t" style="margin:8px 0 0"></p>
    </div>
    <div class="card">
      <h2>Roda escolhida</h2>
      <input id="p3-rq" type="search" placeholder="Buscar roda do estoque" aria-label="Buscar roda do estoque" style="margin-top:10px">
      <div class="pv-rodas" id="p3-rodas"></div>
      <div class="pv-opts c2" id="p3-sys" style="margin-top:10px"></div>
      <div class="pv-opts c2" id="p3-acab" style="margin-top:6px"></div>
    </div>
    <div class="card">
      <h2>Comparar</h2>
      <div class="pv-ab"><button type="button" id="p3-a" class="on"><b>A</b><span id="p3-a-t">-</span></button><button type="button" id="p3-b"><b>B</b><span id="p3-b-t">-</span></button></div>
      <button class="chip" type="button" id="p3-alt" style="margin-top:8px;width:100%">Alternar A e B</button>
      <div class="lbl" style="display:flex;justify-content:space-between;margin-top:12px"><span class="small">Rebaixamento</span><span class="small" id="p3-rb-t">original</span></div>
      <input type="range" id="p3-rb" min="0" max="80" step="5" value="0" aria-label="Rebaixamento em milimetros" style="width:100%">
    </div>
  </aside>
</div>

<div class="pv" id="pv" hidden>
  <div class="pv-stage" id="pv-stage">
    <canvas id="pv-c" aria-label="Carro do cliente na plataforma com a roda simulada"></canvas>
    <video id="pv-video" playsinline muted hidden></video>
    <div class="pv-guide" id="pv-guide" hidden><div class="pv-frame"></div><p id="pv-guide-t">Enquadre o carro de lado, com as duas rodas aparecendo</p></div>
    <div class="pv-busy" id="pv-busy" hidden><div class="pv-spin"></div><p id="pv-busy-t">Removendo o fundo...</p></div>
    <div class="pv-empty" id="pv-empty">
      <h2>Carro do cliente na plataforma</h2>
      <p>Abra a câmera e fotografe o carro de lado. Para o giro de 360°, tire uma foto de cada ângulo andando em volta do carro.</p>
      <div class="pv-row">
        <button class="btn green" type="button" data-cam="1">Foto única</button>
        <button class="btn" type="button" data-cam="8">Giro 360° (8 fotos)</button>
      </div>
      <label class="small" for="pv-file" style="cursor:pointer;text-decoration:underline">ou escolher fotos do aparelho</label>
    </div>
    <div class="pv-shoot" id="pv-shoot" hidden>
      <button class="btn ghost" type="button" id="pv-cancel">Fechar câmera</button>
      <button class="pv-shutter" type="button" id="pv-snap" aria-label="Fotografar"></button>
      <button class="btn ghost" type="button" id="pv-done" hidden>Concluir</button>
    </div>
    <div class="pv-tools" id="pv-tools" hidden>
      <button class="chip on" type="button" id="pv-depois">Depois</button>
      <button class="chip" type="button" id="pv-antes">Antes</button>
      <button class="chip" type="button" id="pv-girar" hidden>Girar 360°</button>
      <button class="chip" type="button" id="pv-ajustar">Ajustar rodas</button>
      <button class="chip" type="button" id="pv-save">Gerar imagem</button>
    </div>
    <div class="pv-info" id="pv-info" hidden></div>
  </div>

  <aside class="pv-side">
    <div class="card">
      <h2>Foto do carro</h2>
      <div class="pv-row" style="margin-top:10px">
        <button class="btn green sm" type="button" data-cam="1">Câmera: foto única</button>
        <button class="btn sm" type="button" data-cam="8">Câmera: giro 360°</button>
      </div>
      <input type="file" id="pv-file" accept="image/*" capture="environment" multiple class="pv-file">
      <p class="small muted" id="pv-model-t" style="margin:10px 0 0">A remoção do fundo roda no próprio aparelho (sem custo e sem enviar a foto para fora). Na primeira vez baixa um arquivo de 4,5 MB.</p>
      <div class="pv-frames" id="pv-frames"></div>
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

<script>window.PV_CARS = <?= json_encode($carsJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>; window.PV_CAR_SEL = <?= (int) ($_GET['carro'] ?? 0) ?>;</script>
<script>window.PV_RODAS = <?= json_encode($rodasJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script src="vendor/ort/ort.wasm.min.js"></script>
<script src="<?= e(asset('provador.js')) ?>"></script>
<script src="vendor/three/three.min.js"></script>
<script src="vendor/three/OrbitControls.js"></script>
<script src="vendor/three/RoomEnvironment.js"></script>
<script src="vendor/three/GLTFLoader.js"></script>
<script src="<?= e(asset('provador3d.js')) ?>"></script>
