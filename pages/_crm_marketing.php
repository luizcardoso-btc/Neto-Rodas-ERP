<?php
use App\Database;
use App\Marketing;

$S = App\Settings::all();
$self = $_SERVER['REQUEST_URI'] ?? '';
$back = 'index.php?' . (parse_url($self, PHP_URL_QUERY) ?: http_build_query(['page' => 'crm', 'tab' => $crmTab]));
$name = fn (array $c) => trim($c['first_name'] . ' ' . $c['last_name']);
$contactBtn = function (array $c, int $offerId = 0, string $label = 'Chamar no WhatsApp') use ($back): string {
    return '<form method="post" target="_blank" class="mk-send">' . App\Csrf::field()
        . '<input type="hidden" name="action" value="mk_contact"><input type="hidden" name="id" value="' . (int) $c['id'] . '">'
        . ($offerId ? '<input type="hidden" name="offer_id" value="' . $offerId . '">' : '')
        . '<input type="hidden" name="back" value="' . e($back) . '"><button class="btn green sm" type="submit" onclick="setTimeout(function(){location.reload()},1500)">' . e($label) . '</button></form>';
};
$optoutBtn = fn (array $c) => '<form method="post" onsubmit="return confirm(\'Marcar que este cliente NAO quer receber mensagens?\')">' . App\Csrf::field()
    . '<input type="hidden" name="action" value="mk_optout"><input type="hidden" name="id" value="' . (int) $c['id'] . '"><input type="hidden" name="back" value="' . e($back) . '"><button class="btn ghost sm" type="submit" title="Nao quer receber mensagens">Não quer</button></form>';
?>
<section class="hero slim"><div><h1>CRM de clientes</h1><p>Traga de volta quem parou de comprar e divulgue ofertas pelo WhatsApp.</p></div></section>
<?php require __DIR__ . '/_crm_tabs.php'; ?>

<?php if ($S['wa_group_link'] === '' && $crmTab !== 'importar'): ?>
  <div class="flash err" style="margin-bottom:14px">Cadastre o <b>link do grupo de ofertas</b> do WhatsApp<?= App\Auth::isAdmin() ? ' no quadro "Mensagem e link do grupo", abaixo' : ' (peça ao administrador)' ?>. Sem ele, a mensagem vai sem o convite.</div>
<?php endif; ?>

<?php if ($crmTab === 'reativar'):
    $days = in_array((int) ($_GET['dias'] ?? 90), [30, 60, 90, 180, 365], true) ? (int) ($_GET['dias'] ?? 90) : 90;
    $never = ($_GET['nunca'] ?? '') === '1';
    $all = ($_GET['todos'] ?? '') === '1';
    $src = (string) ($_GET['origem'] ?? '');
    $sources = array_column(Database::all("SELECT DISTINCT source FROM customers WHERE source IS NOT NULL AND source <> '' ORDER BY source"), 'source');
    $list = Marketing::reactivateList($days, $never, !$all, $src);
    $totCalled = (int) Database::value('SELECT COUNT(*) FROM customers WHERE contacted_at >= NOW() - INTERVAL 30 DAY');
    $totOpt = (int) Database::value('SELECT COUNT(*) FROM customers WHERE opt_out = 1');
?>
<div class="kpis">
  <div class="kpi"><span>Para chamar agora</span><strong class="bad"><?= count($list) ?><?= count($list) >= 200 ? '+' : '' ?></strong><small><?= $never ? 'nunca compraram' : 'sem comprar há +' . $days . ' dias' ?></small></div>
  <div class="kpi"><span>Chamados (30 dias)</span><strong class="ok"><?= $totCalled ?></strong><small>somem da lista por 30 dias</small></div>
  <div class="kpi"><span>Não querem mensagens</span><strong><?= $totOpt ?></strong></div>
</div>
<div class="card">
  <h2>Reativar clientes</h2>
  <p class="small muted" style="margin:4px 0 12px">Clique em <b>Chamar no WhatsApp</b>: a conversa abre com a mensagem pronta (nome, carro e link do grupo). Revise e envie. O cliente fica marcado como chamado e sai da lista por 30 dias. Mande aos poucos, poucas dezenas por dia, para o número não ser bloqueado.</p>
  <form class="filters" method="get" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:12px">
    <input type="hidden" name="page" value="crm"><input type="hidden" name="tab" value="reativar">
    <div><label class="f" for="dias">Sem comprar há</label><select id="dias" name="dias"><?php foreach ([30, 60, 90, 180, 365] as $d): ?><option value="<?= $d ?>" <?= $d === $days ? 'selected' : '' ?>>+<?= $d ?> dias</option><?php endforeach; ?></select></div>
    <div><label class="f" for="origem">Origem</label><select id="origem" name="origem"><option value="">Todas</option><?php foreach ($sources as $so): ?><option <?= $so === $src ? 'selected' : '' ?>><?= e($so) ?></option><?php endforeach; ?></select></div>
    <label class="chk"><input type="checkbox" name="nunca" value="1" <?= $never ? 'checked' : '' ?>> Só quem nunca comprou no sistema novo</label>
    <label class="chk"><input type="checkbox" name="todos" value="1" <?= $all ? 'checked' : '' ?>> Mostrar também quem já foi chamado</label>
    <button class="btn" type="submit">Filtrar</button>
  </form>
  <div class="scroll"><table><thead><tr><th>Cliente</th><th>Última compra</th><th class="num">Total comprado</th><th></th></tr></thead><tbody>
  <?php foreach ($list as $c): $dd = $c['last_purchase'] ? (int) ((time() - strtotime($c['last_purchase'])) / 86400) : null; ?>
    <tr><td><b><?= e($name($c)) ?></b> <span class="small muted"><?= e($c['phone']) ?></span><br>
        <?php if ($c['car']): ?><span class="tag"><?= e($c['car']) ?></span><?php endif; ?><?php if ($c['source']): ?><span class="tag warn"><?= e($c['source']) ?></span><?php endif; ?><?php if ($c['contacted_at']): ?><span class="small muted">chamado em <?= e(fmt_date($c['contacted_at'])) ?></span><?php endif; ?></td>
      <td><?= $dd === null ? '<span class="muted">nunca comprou</span>' : e(fmt_date($c['last_purchase'])) . '<br><span class="small muted">há ' . $dd . ' dias</span>' ?><?php if ($c['last_items']): ?><br><span class="small"><?= e(mb_strimwidth($c['last_items'], 0, 70, '...')) ?></span><?php endif; ?></td>
      <td class="num"><?= money($c['ltv']) ?><br><span class="small muted"><?= (int) $c['purchases'] ?> compra(s)</span></td>
      <td class="actions"><?= $contactBtn($c) ?><?= $optoutBtn($c) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$list): ?><tr><td colspan="4" class="muted">Nenhum cliente para chamar com esse filtro. 🎉</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<?php if ($crmTab === 'ofertas'):
    $offers = Database::all('SELECT o.*, (SELECT COUNT(*) FROM offer_contacts oc WHERE oc.offer_id = o.id) sent FROM offers o ORDER BY o.active DESC, o.id DESC LIMIT 50');
    $cur = isset($_GET['offer']) ? Database::one('SELECT * FROM offers WHERE id = ?', [(int) $_GET['offer']]) : null;
?>
<div class="cols">
  <form class="card" method="post"><?= App\Csrf::field() ?><input type="hidden" name="action" value="mk_offer_save"><input type="hidden" name="back" value="<?= e($back) ?>">
    <h2>Nova oferta</h2>
    <div class="stack" style="margin-top:12px">
      <input name="title" required maxlength="120" placeholder="Título (ex.: PNEU 175/70 R14 COM DESCONTO)">
      <textarea name="message" required rows="6" placeholder="Mensagem">Olá {nome}! Oferta da Neto Pneus e Rodas: pneu {medida} com preço especial esta semana. Responda aqui para garantir o seu. Grupo de ofertas: {link}</textarea>
      <p class="small muted" style="margin:0">Use <b>{nome}</b>, <b>{carro}</b>, <b>{medida}</b> e <b>{link}</b> (link do grupo): são trocados pelos dados de cada cliente.</p>
      <div class="row c2"><input name="measure" placeholder="Medida ou aro (ex.: 175/70 R14 ou 14)"><input name="car_filter" placeholder="Carro (ex.: GOL, ONIX)"></div>
      <p class="small muted" style="margin:0">Público: quem já comprou essa medida/aro <b>ou</b> tem esse carro. Deixe os dois em branco para mandar a todos.</p>
      <button class="btn green block" type="submit">Criar oferta e ver o público</button>
    </div>
  </form>
  <div class="card"><h2>Ofertas</h2>
    <div class="scroll"><table><thead><tr><th>Oferta</th><th>Público</th><th class="num">Enviadas</th><th></th></tr></thead><tbody>
    <?php foreach ($offers as $o): ?>
      <tr style="<?= $o['active'] ? '' : 'opacity:.55' ?>"><td><b><?= e($o['title']) ?></b><br><span class="small muted"><?= e(fmt_date($o['created_at'])) ?></span></td>
        <td class="small"><?= e(trim(($o['measure'] ? 'medida ' . $o['measure'] : '') . ($o['measure'] && $o['car_filter'] ? ' ou ' : '') . ($o['car_filter'] ? 'carro ' . $o['car_filter'] : '')) ?: 'todos') ?></td>
        <td class="num"><?= (int) $o['sent'] ?></td>
        <td class="actions"><a class="btn sm" href="<?= e(url('crm', ['tab' => 'ofertas', 'offer' => $o['id']])) ?>">Enviar</a>
          <form method="post"><?= App\Csrf::field() ?><input type="hidden" name="action" value="mk_offer_toggle"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"><button class="btn ghost sm" type="submit"><?= $o['active'] ? 'Encerrar' : 'Reabrir' ?></button></form>
          <?php if (App\Auth::isAdmin()): ?><form method="post" onsubmit="return confirm('Excluir esta oferta?')"><?= App\Csrf::field() ?><input type="hidden" name="action" value="mk_offer_delete"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>"><button class="btn danger sm" type="submit">Excluir</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$offers): ?><tr><td colspan="4" class="muted">Nenhuma oferta ainda. Crie a primeira ao lado.</td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</div>
<?php if ($cur): $targets = Marketing::offerTargets($cur); $done = count(array_filter($targets, fn ($t) => $t['sent_at'])); $preview = Marketing::render($cur['message'], ['first_name' => 'João', 'car' => 'GOL'], $S['wa_group_link'] ?: '[link do grupo]', (string) $cur['measure']); ?>
<div class="card" id="publico" style="margin-top:16px">
  <h2><?= e($cur['title']) ?></h2>
  <p class="small muted" style="margin:4px 0 10px"><?= $done ?> de <?= count($targets) ?> cliente(s) já receberam. Quem já recebeu vai para o fim da lista.</p>
  <div class="mk-preview"><span class="small muted">Exemplo da mensagem:</span><br><?= nl2br(e($preview)) ?></div>
  <div class="scroll"><table><thead><tr><th>Cliente</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($targets as $c): ?>
    <tr style="<?= $c['sent_at'] ? 'opacity:.6' : '' ?>"><td><b><?= e($name($c)) ?></b> <span class="small muted"><?= e($c['phone']) ?></span><?php if ($c['car']): ?> <span class="tag"><?= e($c['car']) ?></span><?php endif; ?></td>
      <td><?= $c['sent_at'] ? '<span class="tag green">enviada ' . e(fmt_date($c['sent_at'])) . '</span>' : '<span class="tag warn">a enviar</span>' ?></td>
      <td class="actions"><?= $cur['active'] ? $contactBtn($c, (int) $cur['id'], $c['sent_at'] ? 'Enviar de novo' : 'Enviar no WhatsApp') : '<span class="small muted">oferta encerrada</span>' ?><?= $optoutBtn($c) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$targets): ?><tr><td colspan="3" class="muted">Nenhum cliente com telefone para esse público.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($crmTab === 'importar' && App\Auth::isAdmin()): ?>
<div class="cols">
  <form class="card" method="post" enctype="multipart/form-data"><?= App\Csrf::field() ?><input type="hidden" name="action" value="mk_import"><input type="hidden" name="back" value="<?= e($back) ?>">
    <h2>Importar clientes (CSV)</h2>
    <p class="small muted" style="margin:6px 0 12px">Aceita a planilha do sistema antigo, contatos exportados do Google Contatos ou WhatsApp e planilhas do Excel salvas como CSV. As colunas são reconhecidas pelo nome: <b>nome, telefone/celular/whatsapp, CPF/CNPJ, carro, e-mail, endereço, bairro, cidade, UF, CEP</b>. Clientes repetidos (mesmo telefone ou CPF/CNPJ) não são duplicados: só os campos vazios são completados.</p>
    <div class="stack">
      <input type="file" name="file" accept=".csv,text/csv,text/plain" required>
      <input name="source" value="SISTEMA ANTIGO" placeholder="Origem (ex.: SISTEMA ANTIGO, WHATSAPP)">
      <button class="btn green block" type="submit">Importar</button>
    </div>
  </form>
  <div class="card"><h2>Origem dos clientes</h2>
    <table><tbody>
    <?php foreach (Database::all("SELECT COALESCE(NULLIF(source,''), 'CADASTRO NO SISTEMA') s, COUNT(*) n FROM customers GROUP BY s ORDER BY n DESC") as $r): ?>
      <tr><td><?= e($r['s']) ?></td><td class="num"><b><?= (int) $r['n'] ?></b></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
</div>
<?php endif; ?>

<?php if (App\Auth::isAdmin() && $crmTab !== 'importar'): ?>
<form class="card" method="post" style="margin-top:16px"><?= App\Csrf::field() ?><input type="hidden" name="action" value="mk_settings"><input type="hidden" name="back" value="<?= e($back) ?>">
  <h2>Mensagem e link do grupo</h2>
  <div class="stack" style="margin-top:12px">
    <input name="wa_group_link" placeholder="Link de convite do grupo (https://chat.whatsapp.com/...)" value="<?= e($S['wa_group_link']) ?>">
    <textarea name="reactivate_message" rows="4"><?= e($S['reactivate_message']) ?></textarea>
    <p class="small muted" style="margin:0">Mensagem de reativação. Use <b>{nome}</b>, <b>{carro_txt}</b> (" com o seu GOL") e <b>{link}</b>.</p>
    <button class="btn" type="submit">Salvar</button>
  </div>
</form>
<?php endif; ?>
