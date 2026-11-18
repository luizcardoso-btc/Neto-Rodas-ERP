<?php
/** Lista de contas a receber com Editar / Recebi / Apagar. Espera $arRows e $arBack. */
$arBack = $arBack ?? url('financeiro', ['tab' => 'receber']);
$hidden = fn (string $action, int $id) => App\Csrf::field() . '<input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="back" value="' . e($arBack) . '">';
?>
<div class="ar-list">
<?php foreach ($arRows as $r): $late = !$r['paid_at'] && $r['due_date'] && $r['due_date'] < date('Y-m-d'); $id = (int) $r['id']; ?>
  <div class="ar-row <?= $r['paid_at'] ? 'paid' : ($late ? 'late' : '') ?>">
    <div class="ar-main">
      <b><?= money($r['amount']) ?></b>
      <span><?= e($r['customer'] ?: $r['description']) ?><?php if ($r['sale_id']): ?> · <a href="<?= e(url('vendas', ['id' => $r['sale_id']])) ?>">venda #<?= (int) $r['sale_id'] ?></a><?php endif; ?></span>
      <span class="small muted"><?= $r['customer'] ? e($r['description']) : '' ?></span>
      <span class="small"><?php if ($r['paid_at']): ?><span class="tag green">recebido <?= e(fmt_date($r['paid_at'])) ?></span><?php elseif ($r['due_date']): ?><span class="<?= $late ? 'tag red' : 'muted' ?>"><?= $late ? 'atrasado · ' : 'previsto ' ?><?= e(fmt_date($r['due_date'])) ?></span><?php else: ?><span class="tag warn">sem data</span><?php endif; ?>
        <?php if (!$r['paid_at'] && !empty($r['phone'])): ?> · <a href="<?= e(whatsapp_link($r['phone'])) ?>" target="_blank" rel="noopener">cobrar no WhatsApp</a><?php endif; ?></span>
    </div>
    <div class="ar-acts">
      <?php if (!$r['paid_at']): ?>
        <details class="ar-d"><summary class="btn green sm">Recebi</summary>
          <form method="post" class="stack ar-pop"><?= $hidden('ar_receive', $id) ?>
            <p class="small" style="margin:0">Deixe em branco para receber o valor todo (<?= money($r['amount']) ?>).</p>
            <input name="partial" inputmode="decimal" placeholder="Recebi só uma parte (R$)">
            <label class="birth"><span>Nova data para o restante</span><input type="date" name="new_due" value="<?= e((string) $r['due_date']) ?>"></label>
            <button class="btn green" type="submit">Confirmar recebimento</button>
          </form></details>
        <details class="ar-d"><summary class="btn ghost sm">Editar</summary>
          <form method="post" class="stack ar-pop"><?= $hidden('ar_edit', $id) ?>
            <label class="birth"><span>Data prevista para receber</span><input type="date" name="due_date" value="<?= e((string) $r['due_date']) ?>"></label>
            <input name="description" value="<?= e($r['description']) ?>" aria-label="Descricao">
            <?php if (!$r['sale_id']): ?><input name="amount" inputmode="decimal" value="<?= e(number_format((float) $r['amount'], 2, ',', '')) ?>" aria-label="Valor"><?php else: ?>
              <p class="small muted" style="margin:0">Para mudar o valor ou a forma de pagamento, use <a href="<?= e(url('vendas', ['edit' => $r['sale_id']])) ?>">Editar venda</a>.</p><?php endif; ?>
            <button class="btn" type="submit">Salvar</button>
          </form></details>
      <?php elseif (App\Auth::isAdmin()): ?>
        <form method="post"><?= $hidden('ar_reopen', $id) ?><button class="linklike" type="submit">desfazer recebimento</button></form>
      <?php endif; ?>
      <?php if (App\Auth::isAdmin()): ?>
        <form method="post" onsubmit="return confirm('Apagar esta conta a receber?<?= $r['sale_id'] ? ' A venda continua registrada; para trocar a forma de pagamento use Editar venda.' : '' ?>')"><?= $hidden('ar_delete', $id) ?><button class="btn danger sm" type="submit">Apagar</button></form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$arRows): ?><p class="muted">Nenhuma conta a receber em aberto.</p><?php endif; ?>
</div>
