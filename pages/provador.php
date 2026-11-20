<?php
use App\Database;

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
<section class="hero slim"><div><h1>Provador de rodas</h1><p>Fotografe o carro do cliente, o fundo sai sozinho e o carro aparece na plataforma do showroom com a roda escolhida.</p></div></section>

<div class="pv" id="pv">
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

<script>window.PV_RODAS = <?= json_encode($rodasJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<script src="vendor/ort/ort.wasm.min.js"></script>
<script src="<?= e(asset('provador.js')) ?>"></script>
