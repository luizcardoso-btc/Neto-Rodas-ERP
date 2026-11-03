<?php
/** Processa o POST de venda (PDV e PDV Mobile). Espera $source ('pdv'|'mobile'), $fixedSeller e $self. */
use App\SaleService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'sale') {
    return;
}

$rows = fn ($k) => array_values(array_filter((array) ($_POST[$k] ?? []), 'is_array'));
$items = array_map(fn ($r) => [
    'product_id' => ($r['category'] ?? '') === 'servico' ? null : ((int) ($r['product_id'] ?? 0) ?: null),
    'name' => upper((string) ($r['name'] ?? '')),
    'category' => (string) ($r['category'] ?? 'pneu'),
    'quantity' => (int) ($r['quantity'] ?? 0),
    'unit_price' => parse_money($r['unit_price'] ?? 0),
], $rows('items'));
$payments = array_map(fn ($r) => [
    'method' => (string) ($r['method'] ?? ''),
    'amount' => parse_money($r['amount'] ?? 0),
    'note' => upper((string) ($r['note'] ?? '')),
    'trade_qty' => (int) ($r['trade_qty'] ?? 1),
    'trade_category' => (string) ($r['trade_category'] ?? 'pneu'),
    'to_creditor' => !empty($r['to_creditor']),
    'machine_id' => (int) ($r['machine_id'] ?? 0),
    'card_brand' => (string) ($r['card_brand'] ?? 'vm'),
    'card_inst' => (string) ($r['card_inst'] ?? ''),
], $rows('payments'));

$data = [
    'request_token' => (string) ($_POST['request_token'] ?? ''),
    'seller_id' => $fixedSeller ? (int) $fixedSeller['id'] : ((int) ($_POST['seller_id'] ?? 0) ?: null),
    'wants_nfe' => !empty($_POST['wants_nfe']),
    'notes' => upper((string) ($_POST['notes'] ?? '')),
    'due_date' => (string) ($_POST['due_date'] ?? ''),
    'customer' => upper_all((array) ($_POST['customer'] ?? [])),
    'items' => $items,
    'payments' => $payments,
];

try {
    foreach ($items as $it) {
        $isService = $it['category'] === 'servico';
        if (!$it['product_id'] && trim($it['name']) === '') {
            continue;
        }
        if (!$isService && !$it['product_id']) {
            throw new DomainException($it['category'] === 'parafuso'
                ? 'Escolha o jogo de parafusos na lista.'
                : ($it['category'] === 'roda'
                ? 'Escolha a roda: busque pelo nome/aro e selecione na lista.'
                : 'Escolha o pneu pela medida: busque (ex.: 175/65 R14) e selecione na lista.'));
        }
        if ($isService) {
            $it['product_id'] = null;
        }
        if ($it['unit_price'] <= 0) {
            throw new DomainException('Informe o preco unitario de cada item (maior que zero).');
        }
    }
    SaleService::assertPdvCustomer($data['customer']);
    foreach ($payments as $pay) {
        if ($pay['method'] === 'cartao' && $pay['amount'] > 0) {
            App\CardFees::resolve($pay, $pay['amount']); // exige maquina, bandeira e parcelas no cartao
        }
    }
    foreach ($payments as $pay) {
        if ($pay['method'] === 'troca' && !isset(SaleService::TRADE_CATEGORIES[$pay['trade_category']])) {
            throw new DomainException('Na troca, escolha se e Roda ou Pneu.');
        }
    }
    $allowNeg = App\Env::get('ALLOW_NEGATIVE_STOCK', 'false') === 'true';
    $res = SaleService::create($data, $source, $allowNeg);
    $quoteId = (int) ($_POST['quote_id'] ?? 0);
    if ($quoteId && !$res['duplicate']) {
        App\Database::run("UPDATE quotes SET status = 'vendido', sale_id = ? WHERE id = ? AND sale_id IS NULL", [$res['id'], $quoteId]);
    }
    flash('ok', $res['duplicate'] ? "Venda #{$res['id']} ja tinha sido registrada." : "Venda #{$res['id']} finalizada.");
} catch (DomainException $e) {
    $_SESSION['_old_sale'] = $_POST;
    flash('err', $e->getMessage());
}
redirect($self);
