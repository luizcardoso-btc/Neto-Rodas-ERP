<?php
/**
 * Formulario de venda compartilhado entre PDV e PDV Mobile.
 * Espera: $mode ('pdv'|'mobile'), $sellers (lista), $fixedSeller (?array), $self (url base da pagina).
 */
use App\SaleService;

$old = $_SESSION['_old_sale'] ?? null;
unset($_SESSION['_old_sale']);
// Orcamento aprovado levado ao PDV: pre-preenche cliente, itens e pagamento (so no PDV de balcao).
$fromQuote = null;
if (!$old && ($source ?? '') === 'pdv' && !empty($_GET['quote'])) {
    $fromQuote = App\QuoteToSale::prefill((int) $_GET['quote']);
    $old = $fromQuote;
}
$quoteId = (int) ($old['quote_id'] ?? 0);
$token = $old['request_token'] ?? uuid4();
$lookupBase = $self . (str_contains($self, '?') ? '&' : '?') . 'lookup=';
?>
<form method="post" id="sale-form" class="caps" data-lookup="<?= e($lookupBase) ?>" data-finishes="<?= e(json_encode(App\Wheel::FINISHES)) ?>" data-brands="<?= e(json_encode(App\Wheel::brands())) ?>" data-rims="<?= e(json_encode([App\Wheel::RIM_MIN, App\Wheel::RIM_MAX])) ?>" data-card="<?= e(json_encode(['machines' => App\CardFees::machines(), 'rates' => App\CardFees::rateMap() ?: new stdClass()])) ?>" data-creditor="<?= e(App\Settings::all()['creditor_name']) ?>" data-old="<?= e(json_encode($old, JSON_UNESCAPED_UNICODE) ?: 'null') ?>" novalidate>
  <?= App\Csrf::field() ?>
  <input type="hidden" name="action" value="sale">
  <input type="hidden" name="request_token" value="<?= e($token) ?>">
  <?php if ($quoteId): ?><input type="hidden" name="quote_id" value="<?= $quoteId ?>">
  <div class="flash ok" style="margin:0 0 14px">Venda do <b>orcamento #<?= e(str_pad((string) $quoteId, 4, '0', STR_PAD_LEFT)) ?></b>. Confira cliente, itens e pagamento; <?= $fromQuote && !empty($fromQuote['_unmatched']) ? '<b>escolha na lista o pneu/roda dos itens marcados</b>, ' : '' ?>edite o que precisar e finalize.</div><?php endif; ?>

  <div class="card">
    <h2>Cliente</h2>
    <p class="sub">Busque um cliente existente ou preencha os dados para cadastrar na hora.</p>
    <div class="suggest" style="margin-bottom:10px">
      <input id="customer-search" placeholder="Buscar cliente por nome, telefone ou CPF/CNPJ" autocomplete="off">
      <ul id="customer-results" hidden></ul>
    </div>
    <input type="hidden" name="customer[customer_id]" id="customer_id">
    <p class="small muted" style="margin:0 0 10px">Campos com * sao obrigatorios. Tudo e gravado em CAIXA ALTA.</p>
    <div class="row c2"><input name="customer[first_name]" placeholder="NOME *" autocomplete="off" required><input name="customer[last_name]" placeholder="SOBRENOME" autocomplete="off"></div>
    <div class="row"><input name="customer[email]" type="email" placeholder="E-MAIL (opcional)"></div>
    <div class="row c2"><input name="customer[phone]" inputmode="tel" placeholder="TELEFONE (DDD) *" required minlength="10"><input name="customer[tax_id]" inputmode="numeric" placeholder="CPF/CNPJ *" required minlength="11"></div>
    <div class="row c2"><input name="customer[zip]" inputmode="numeric" placeholder="CEP (opcional)" maxlength="9" autocomplete="postal-code"><input name="customer[car]" placeholder="CARRO *" required></div>
    <div class="row"><input name="notes" placeholder="OBSERVACOES"></div>

    <label class="flash ok" style="display:flex;gap:10px;align-items:center;cursor:pointer;margin:6px 0 10px">
      <input type="checkbox" name="wants_nfe" value="1" id="wants-nfe" style="width:auto"> NF-e
    </label>
    <fieldset style="border:1px solid var(--line);border-radius:16px;padding:14px;margin:0">
      <legend style="font-weight:700;padding:0 6px">Endereco do cliente</legend>
      <p class="small muted" style="margin:0 0 10px">Preenchido sozinho ao digitar o CEP; confira e informe o numero. Logradouro, numero, bairro, cidade e UF sao obrigatorios quando NF-e estiver marcada.</p>
      <div class="row c2"><input name="customer[street]" placeholder="Logradouro"><input name="customer[number]" placeholder="Numero"></div>
      <div class="row c2"><input name="customer[district]" placeholder="Bairro"><input name="customer[city]" placeholder="Cidade"></div>
      <div class="row"><input name="customer[state]" maxlength="2" placeholder="UF"></div>
    </fieldset>
  </div>

  <div class="card">
    <?php if ($fixedSeller): ?>
      <input type="hidden" name="seller_id" value="<?= (int) $fixedSeller['id'] ?>">
      <p style="margin:0">Vendedor: <b><?= e($fixedSeller['name']) ?></b></p>
    <?php else: ?>
      <select name="seller_id" aria-label="Vendedor"><option value="">Selecione o vendedor</option>
        <?php foreach ($sellers as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select>
    <?php endif; ?>
  </div>

  <div class="card">
    <input type="hidden" name="sale_type" id="sale_type" value="varejo">
    <div class="tabs-sale" role="tablist" aria-label="Tipo da venda"><button type="button" role="tab" class="on" data-t="varejo">Varejo</button><button type="button" role="tab" data-t="atacado">Atacado</button></div>
    <p class="small muted" id="atacado-hint" hidden style="margin:0 0 10px">Venda de atacado: ao escolher o produto, o preco de atacado cadastrado no estoque e usado (se nao houver, vale o preco normal). Voce pode ajustar o valor.</p>
    <div class="actions" style="justify-content:space-between;align-items:center">
      <div><h2>Itens da venda</h2><p class="sub" style="margin:0">Escolha a categoria (Pneus, Roda ou Servicos). Pneus: busque pela medida (ex.: 175/65 R14). Roda: nome, marca ou aro.</p></div>
      <button class="btn ghost sm" type="button" id="add-item">+ Item</button>
    </div>
    <div id="items" style="margin-top:12px"></div>
    <div class="totalbar"><span class="muted">Total da venda</span><b id="total">R$ 0,00</b></div>
  </div>

  <div class="card">
    <div class="actions" style="justify-content:space-between;align-items:center">
      <div><h2>Pagamento</h2><p class="sub" style="margin:0">Pode dividir em varios meios (ex.: pix + dinheiro). <span class="dot"></span>Troca = pneu ou roda usada: entra na categoria e no estoque de usados.</p></div>
      <button class="btn ghost sm" type="button" id="add-payment">+ Pagamento</button>
    </div>
    <div id="payments" style="margin-top:12px"></div>
    <div class="sumbar">
      <div>Total<b id="s-total">R$ 0,00</b></div>
      <div>Pago<b id="s-paid">R$ 0,00</b></div>
      <div id="s-box">Falta<b id="s-diff">R$ 0,00</b></div>
    </div>
    <input type="hidden" id="due_date" name="due_date" value="">
    <button type="button" class="btn ghost sm" id="rest-receber" hidden style="margin-top:10px">Lancar o restante como A RECEBER</button>
  </div>

  <button class="btn green block" type="submit" id="submit">Finalizar venda</button>
</form>
<script src="<?= e(asset('cardfees.js')) ?>"></script>
<script src="<?= e(asset('sale.js')) ?>"></script>
