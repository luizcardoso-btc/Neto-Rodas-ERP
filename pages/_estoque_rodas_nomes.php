<?php
use App\Database;

// Admin: troca o nome das rodas do estoque pelo nome da roda do provador (preco e estoque ficam iguais)
$catalog = Database::all('SELECT id, name, brand, rim, path FROM rim_catalog WHERE active = 1 ORDER BY name, rim');
$wheels = Database::all("SELECT id, name, brand, model, rim, condition_type, stock, price, rim_catalog_id FROM products WHERE active = 1 AND category = 'roda' ORDER BY (rim_catalog_id IS NULL) DESC, name");
$byId = array_column($catalog, null, 'id');
?>
<div class="card">
  <h2>Nomes das rodas (provador)</h2>
  <p class="sub">Escolha, para cada roda do estoque, qual roda do provador ela é. O nome do produto passa a ser o do provador (ex.: <b>JOGO DE RODA FIAT ARGO 2025 GRAFITE R15</b>) e o provador mostra o preço e o estoque dela. <b>Só o nome muda</b>: preço, custo e quantidade continuam iguais. Onde aparece "sugestão", toque nela para preencher.</p>
  <form method="post">
    <?= App\Csrf::field() ?><input type="hidden" name="action" value="rim_names">
    <div class="stack" style="margin:10px 0">
      <button class="btn ghost sm" type="button" id="rn-all">Usar todas as sugestões</button>
    </div>
    <div class="rn-wrap"><table class="rn">
      <thead><tr><th>Roda no estoque hoje</th><th>Aro</th><th>Estoque</th><th>Roda do provador</th></tr></thead>
      <tbody>
      <?php foreach ($wheels as $w): $sug = $w['rim_catalog_id'] ? null : App\RimNames::suggest($w, $catalog); ?>
        <tr>
          <td><b><?= e($w['name']) ?></b><?= $w['condition_type'] !== 'novo' ? ' <small class="muted">' . e($w['condition_type']) . '</small>' : '' ?></td>
          <td><?= e((string) $w['rim']) ?></td>
          <td><?= (int) $w['stock'] ?></td>
          <td>
            <select name="link[<?= (int) $w['id'] ?>]" aria-label="Roda do provador para <?= e($w['name']) ?>">
              <option value="">— não mudar —</option>
              <?php if ($w['rim_catalog_id']): ?><option value="0">✕ tirar o vínculo</option><?php endif; ?>
              <?php foreach ($catalog as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $w['rim_catalog_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e(preg_replace('/^RODA\s+/', '', $c['name']) . ' · aro ' . $c['rim']) ?></option><?php endforeach; ?>
            </select>
            <?php if ($sug): ?><button type="button" class="linklike rn-sug" data-v="<?= (int) $sug['id'] ?>">sugestão: <?= e(preg_replace('/^RODA\s+/', '', $sug['name']) . ' aro ' . $sug['rim']) ?></button><?php endif; ?>
            <?php if ($w['rim_catalog_id'] && isset($byId[$w['rim_catalog_id']])): ?><small class="muted">já é do provador</small><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$wheels): ?><tr><td colspan="4" class="muted">Nenhuma roda no estoque.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
    <div class="stack" style="margin-top:12px"><button class="btn block" type="submit">Salvar nomes</button>
    <a class="btn ghost block" href="<?= e(url('estoque')) ?>">Voltar ao estoque</a></div>
  </form>
</div>
<style>.rn-wrap{overflow-x:auto}.rn{width:100%;border-collapse:collapse}.rn th,.rn td{padding:8px 6px;border-bottom:1px solid #eee;text-align:left}.rn select{min-width:240px;max-width:100%}.rn .rn-sug{display:block;margin-top:4px;font-size:12px;text-align:left}.rn td{vertical-align:top}</style>
<script>
document.querySelectorAll('.rn-sug').forEach((b) => { b.onclick = () => { b.previousElementSibling.value = b.dataset.v; b.previousElementSibling.style.outline = '2px solid #ffd21a'; }; });
document.getElementById('rn-all').onclick = () => document.querySelectorAll('.rn-sug').forEach((b) => b.click());
</script>
