<?php
declare(strict_types=1);

namespace App;

use DomainException;
use PDOException;

/**
 * Regras de venda: itens, pagamentos multiplos, troca, estoque e contas a receber.
 * Tudo acontece numa unica transacao; vendas repetidas (mesmo request_token) sao ignoradas.
 */
final class SaleService
{
    public const METHODS = ['dinheiro', 'pix', 'cartao', 'prazo', 'troca'];
    public const ITEM_CATEGORIES = ['pneu' => 'Pneus', 'roda' => 'Roda', 'parafuso' => 'Parafusos', 'servico' => 'Servicos'];
    public const TRADE_CATEGORIES = ['pneu' => 'Pneu usado', 'roda' => 'Roda usada'];
    public const ADDRESS_FIELDS = ['street' => 'logradouro', 'number' => 'numero', 'district' => 'bairro', 'city' => 'cidade', 'state' => 'UF', 'zip' => 'CEP'];

    /**
     * @param array{
     *   request_token:string, seller_id?:?int, wants_nfe?:bool, notes?:?string, due_date?:?string,
     *   customer?:?array<string,mixed>,
     *   items:list<array{product_id?:?int,name?:string,quantity:int,unit_price:float}>,
     *   payments:list<array{method:string,amount:float,note?:?string,trade_qty?:int}>
     * } $data
     * @return array{id:int,duplicate:bool}
     */
    public static function create(array $data, string $source = 'pdv', bool $allowNegativeStock = false): array
    {
        $token = trim((string) ($data['request_token'] ?? ''));
        if (!preg_match('/^[0-9a-f-]{36}$/i', $token)) {
            throw new DomainException('Requisicao invalida. Recarregue a pagina e tente de novo.');
        }
        if ($existing = Database::value('SELECT id FROM sales WHERE request_token = ?', [$token])) {
            return ['id' => (int) $existing, 'duplicate' => true];
        }

        $items = self::normalizeItems($data['items'] ?? []);
        $totalCents = array_sum(array_map(fn ($i) => (int) round($i['unit_price'] * 100) * $i['quantity'], $items));
        $payments = self::normalizePayments($data['payments'] ?? []);
        $paidCents = array_sum(array_map(fn ($p) => (int) round($p['amount'] * 100), $payments));
        if ($paidCents !== $totalCents) {
            throw new DomainException(sprintf(
                'Os pagamentos (%s) precisam fechar com o total da venda (%s).',
                money($paidCents / 100),
                money($totalCents / 100)
            ));
        }

        if (array_filter($payments, fn ($p) => $p['to_creditor']) && empty($data['seller_id'])) {
            throw new DomainException('Para registrar envio ao credor, selecione o vendedor que recebeu o pagamento.');
        }
        $customerIn = $data['customer'] ?? null;
        if (!empty($data['wants_nfe'])) {
            self::assertNfeCustomer($customerIn);
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $customerId = self::resolveCustomer($customerIn);
            $sellerId = !empty($data['seller_id']) ? (int) $data['seller_id'] : null;
            $prazoCents = array_sum(array_map(
                fn ($p) => $p['method'] === 'prazo' ? (int) round($p['amount'] * 100) : 0,
                $payments
            ));

            $saleId = Database::insert(
                'INSERT INTO sales (request_token, customer_id, seller_id, total, payment_status, source, wants_nfe, notes, sale_type)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [
                    $token, $customerId, $sellerId, $totalCents / 100,
                    $prazoCents > 0 ? 'pending' : 'paid', $source,
                    empty($data['wants_nfe']) ? 0 : 1,
                    self::clip($data['notes'] ?? null, 255),
                    ($data['sale_type'] ?? '') === 'atacado' ? 'atacado' : 'varejo',
                ]
            );

            foreach ($items as $item) {
                self::addItem($saleId, $item, $allowNegativeStock);
            }

            foreach ($payments as $p) {
                $payId = Database::insert(
                    'INSERT INTO sale_payments (sale_id, method, amount, note, trade_qty, trade_category, to_creditor, machine_id, card_brand, card_kind, card_installments, fee_rate, fee_amount) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$saleId, $p['method'], $p['amount'], $p['note'], $p['method'] === 'troca' ? $p['trade_qty'] : 1, $p['method'] === 'troca' ? $p['trade_category'] : null, $p['to_creditor'] ? 1 : 0,
                        $p['machine_id'], $p['card_brand'], $p['card_kind'], $p['card_installments'], $p['fee_rate'], $p['fee_amount']]
                );
                if ($p['method'] === 'troca') {
                    $tradePid = self::receiveTradeIn($saleId, $p);
                    Database::run('UPDATE sale_payments SET product_id = ? WHERE id = ?', [$tradePid, $payId]);
                }
            }

            self::syncCreditorPayments($saleId, $sellerId, date('Y-m-d'));

            self::insertReceivables($saleId, $customerId, $payments, $data['due_date'] ?? null, null);

            $pdo->commit();
            return ['id' => $saleId, 'duplicate' => false];
        } catch (PDOException $e) {
            $pdo->rollBack();
            // Duas requisicoes simultaneas com o mesmo token: a segunda cai aqui.
            if ($e->getCode() === '23000' && ($id = Database::value('SELECT id FROM sales WHERE request_token = ?', [$token]))) {
                return ['id' => (int) $id, 'duplicate' => true];
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Edicao completa de uma venda ja realizada (somente admin, validado na tela).
     * Reajusta estoque (itens e pneus usados da troca), contas a receber e grava auditoria.
     *
     * @param array{
     *   created_at?:?string, customer_id?:?int, seller_id?:?int, notes?:?string, wants_nfe?:bool,
     *   payment_status?:?string, due_date?:?string,
     *   items:list<array{id?:?int,product_id?:?int,description?:string,quantity:int,unit_price:float,unit_cost?:?float}>,
     *   payments:list<array{id?:?int,method:string,amount:float,note?:?string,trade_qty?:int}>
     * } $data
     */
    public static function update(int $saleId, array $data, ?array $user = null): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $sale = Database::one('SELECT * FROM sales WHERE id = ? FOR UPDATE', [$saleId]);
            if (!$sale) {
                throw new DomainException('Venda nao encontrada.');
            }
            $oldItems = Database::all('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id', [$saleId]);
            $oldPays = Database::all('SELECT * FROM sale_payments WHERE sale_id = ? ORDER BY id', [$saleId]);
            $snapshot = json_encode(['sale' => $sale, 'items' => $oldItems, 'payments' => $oldPays], JSON_UNESCAPED_UNICODE);

            // ---- itens ----
            $items = [];
            foreach ($data['items'] ?? [] as $r) {
                $desc = trim((string) ($r['description'] ?? ''));
                $pid = !empty($r['product_id']) ? (int) $r['product_id'] : null;
                if ($pid === null && $desc === '') {
                    continue;
                }
                $qty = (int) ($r['quantity'] ?? 0);
                $price = round((float) ($r['unit_price'] ?? 0), 2);
                if ($qty < 1) {
                    throw new DomainException('Informe a quantidade de cada item (minimo 1).');
                }
                if ($price < 0) {
                    throw new DomainException('Preco unitario nao pode ser negativo.');
                }
                $items[] = ['id' => (int) ($r['id'] ?? 0), 'product_id' => $pid, 'description' => $desc, 'quantity' => $qty,
                    'unit_price' => $price, 'unit_cost' => isset($r['unit_cost']) && $r['unit_cost'] !== null ? round((float) $r['unit_cost'], 2) : null];
            }
            if (!$items) {
                throw new DomainException('A venda precisa ter ao menos um item ou servico.');
            }
            $payments = self::normalizePayments($data['payments'] ?? []);
            $totalCents = array_sum(array_map(fn ($i) => (int) round($i['unit_price'] * 100) * $i['quantity'], $items));
            $paidCents = array_sum(array_map(fn ($p) => (int) round($p['amount'] * 100), $payments));
            if ($paidCents !== $totalCents) {
                throw new DomainException(sprintf(
                    'Os pagamentos (%s) precisam fechar com o total da venda (%s).',
                    money($paidCents / 100),
                    money($totalCents / 100)
                ));
            }

            $oldById = [];
            foreach ($oldItems as $o) {
                $oldById[(int) $o['id']] = $o;
            }
            $keep = [];
            foreach ($items as $it) {
                $old = $it['id'] ? ($oldById[$it['id']] ?? null) : null;
                if ($old) {
                    $keep[] = (int) $old['id'];
                    $desc = $it['description'] !== '' ? $it['description'] : $old['description'];
                    // mesmo produto: acerta a diferenca de estoque; produto trocado: devolve o antigo e baixa o novo.
                    if ((int) $old['product_id'] === (int) $it['product_id']) {
                        if ($it['product_id']) {
                            self::moveStock((int) $it['product_id'], (int) $old['quantity'] - $it['quantity'], "Edicao da venda #$saleId", $saleId);
                        }
                        $cost = $it['unit_cost'] ?? (float) $old['unit_cost'];
                    } else {
                        if ($old['product_id']) {
                            self::moveStock((int) $old['product_id'], (int) $old['quantity'], "Edicao da venda #$saleId", $saleId);
                        }
                        $cost = $it['unit_cost'];
                        if ($it['product_id']) {
                            $pr = self::product($it['product_id']);
                            $cost ??= (float) $pr['cost_price'];
                            $desc = $it['description'] !== '' ? $it['description'] : $pr['name'];
                            self::moveStock($it['product_id'], -$it['quantity'], "Edicao da venda #$saleId", $saleId);
                        }
                        $cost ??= 0.0;
                    }
                    Database::run(
                        'UPDATE sale_items SET product_id = ?, category = ?, description = ?, quantity = ?, unit_price = ?, unit_cost = ? WHERE id = ?',
                        [$it['product_id'], self::itemCategory($it['product_id']), self::clip($desc, 200), $it['quantity'], $it['unit_price'], $cost, $old['id']]
                    );
                } else {
                    $cost = $it['unit_cost'] ?? 0.0;
                    $desc = $it['description'];
                    if ($it['product_id']) {
                        $pr = self::product($it['product_id']);
                        $cost = $it['unit_cost'] ?? (float) $pr['cost_price'];
                        $desc = $desc !== '' ? $desc : $pr['name'];
                        self::moveStock($it['product_id'], -$it['quantity'], "Edicao da venda #$saleId", $saleId);
                    }
                    $newId = Database::insert(
                        'INSERT INTO sale_items (sale_id, product_id, category, description, quantity, unit_price, unit_cost) VALUES (?,?,?,?,?,?,?)',
                        [$saleId, $it['product_id'], self::itemCategory($it['product_id']), self::clip($desc, 200), $it['quantity'], $it['unit_price'], $cost]
                    );
                    $keep[] = $newId;
                }
            }
            foreach ($oldItems as $o) {
                if (!in_array((int) $o['id'], $keep, true)) {
                    if ($o['product_id']) {
                        self::moveStock((int) $o['product_id'], (int) $o['quantity'], "Edicao da venda #$saleId (item removido)", $saleId);
                    }
                    Database::run('DELETE FROM sale_items WHERE id = ?', [$o['id']]);
                }
            }

            // ---- pagamentos ----
            $oldPayById = [];
            foreach ($oldPays as $o) {
                $oldPayById[(int) $o['id']] = $o;
            }
            $usedPay = [];
            // normalizePayments descarta linhas vazias; reencontra o id original pela mesma ordem de entrada.
            $normalized = [];
            foreach (array_values($data['payments'] ?? []) as $raw) {
                if (round((float) ($raw['amount'] ?? 0), 2) <= 0) {
                    continue;
                }
                $normalized[] = (int) ($raw['id'] ?? 0);
            }
            foreach ($payments as $idx => $p) {
                $oldPay = $oldPayById[$normalized[$idx] ?? 0] ?? null;
                $isTrade = $p['method'] === 'troca';
                $qty = $isTrade ? $p['trade_qty'] : 1;
                $tradePid = null;
                if ($oldPay) {
                    $usedPay[] = (int) $oldPay['id'];
                    $tradePid = $oldPay['product_id'] ? (int) $oldPay['product_id'] : null;
                    $wasTrade = $oldPay['method'] === 'troca' && $tradePid;
                    if ($wasTrade && $isTrade) {
                        self::updateTradeProduct($tradePid, (int) $oldPay['trade_qty'], $p, $saleId);
                    } elseif ($wasTrade && !$isTrade) {
                        self::moveStock($tradePid, -(int) $oldPay['trade_qty'], "Troca desfeita na venda #$saleId", $saleId, true);
                        $tradePid = null;
                    } elseif ($isTrade) {
                        $tradePid = self::receiveTradeIn($saleId, $p);
                    }
                    Database::run(
                        'UPDATE sale_payments SET method = ?, amount = ?, note = ?, trade_qty = ?, product_id = ?, trade_category = ?, to_creditor = ?, machine_id = ?, card_brand = ?, card_kind = ?, card_installments = ?, fee_rate = ?, fee_amount = ? WHERE id = ?',
                        [$p['method'], $p['amount'], $p['note'], $qty, $isTrade ? $tradePid : null, $isTrade ? $p['trade_category'] : null, $p['to_creditor'] ? 1 : 0,
                            $p['machine_id'], $p['card_brand'], $p['card_kind'], $p['card_installments'], $p['fee_rate'], $p['fee_amount'], $oldPay['id']]
                    );
                } else {
                    $payId = Database::insert(
                        'INSERT INTO sale_payments (sale_id, method, amount, note, trade_qty, trade_category, to_creditor, machine_id, card_brand, card_kind, card_installments, fee_rate, fee_amount) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                        [$saleId, $p['method'], $p['amount'], $p['note'], $qty, $isTrade ? $p['trade_category'] : null, $p['to_creditor'] ? 1 : 0,
                            $p['machine_id'], $p['card_brand'], $p['card_kind'], $p['card_installments'], $p['fee_rate'], $p['fee_amount']]
                    );
                    if ($isTrade) {
                        Database::run('UPDATE sale_payments SET product_id = ? WHERE id = ?', [self::receiveTradeIn($saleId, $p), $payId]);
                    }
                }
            }
            foreach ($oldPays as $o) {
                if (!in_array((int) $o['id'], $usedPay, true)) {
                    if ($o['method'] === 'troca' && $o['product_id']) {
                        self::moveStock((int) $o['product_id'], -(int) $o['trade_qty'], "Troca removida da venda #$saleId", $saleId, true);
                    }
                    Database::run('DELETE FROM sale_payments WHERE id = ?', [$o['id']]);
                }
            }

            // ---- cabecalho ----
            $customerId = !empty($data['customer_id']) ? (int) $data['customer_id'] : null;
            if ($customerId && !Database::value('SELECT id FROM customers WHERE id = ?', [$customerId])) {
                throw new DomainException('Cliente nao encontrado.');
            }
            $sellerId = !empty($data['seller_id']) ? (int) $data['seller_id'] : null;
            $createdAt = self::validDateTime($data['created_at'] ?? null) ?? $sale['created_at'];
            $prazoCents = array_sum(array_map(fn ($p) => $p['method'] === 'prazo' ? (int) round($p['amount'] * 100) : 0, $payments));
            $status = $prazoCents > 0 && ($data['payment_status'] ?? 'pending') !== 'paid' ? 'pending' : 'paid';
            if (!empty($data['wants_nfe'])) {
                $c = $customerId ? Database::one('SELECT * FROM customers WHERE id = ?', [$customerId]) : null;
                self::assertNfeCustomer($c);
            }
            Database::run(
                'UPDATE sales SET customer_id = ?, seller_id = ?, total = ?, payment_status = ?, wants_nfe = ?, notes = ?, created_at = ?, sale_type = ? WHERE id = ?',
                [$customerId, $sellerId, $totalCents / 100, $status, empty($data['wants_nfe']) ? 0 : 1, self::clip($data['notes'] ?? null, 255), $createdAt, ($data['sale_type'] ?? '') === 'atacado' ? 'atacado' : 'varejo', $saleId]
            );

            self::syncCreditorPayments($saleId, $sellerId, substr($createdAt, 0, 10));

            // ---- contas a receber ----
            $oldPaidAt = Database::value('SELECT MAX(paid_at) FROM accounts_receivable WHERE sale_id = ?', [$saleId]);
            Database::run('DELETE FROM accounts_receivable WHERE sale_id = ?', [$saleId]);
            $paidAt = $status === 'paid' ? ($oldPaidAt ?: date('Y-m-d H:i:s')) : null;
            self::insertReceivables($saleId, $customerId, $payments, $data['due_date'] ?? null, $paidAt);

            Database::insert(
                'INSERT INTO sale_audit (sale_id, user_id, username, summary, snapshot) VALUES (?,?,?,?,?)',
                [$saleId, $user['id'] ?? null, $user['username'] ?? null,
                    sprintf('Venda editada: total %s -> %s', money((float) $sale['total']), money($totalCents / 100)), $snapshot]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Pagamentos marcados "enviado ao credor" viram UM lancamento no extrato do credor (origem: venda).
     * O faturamento da venda nao muda; refazer a partir dos pagamentos evita duplicidade em edicoes.
     */
    private static function syncCreditorPayments(int $saleId, ?int $sellerId, string $paidOn): void
    {
        Database::run("DELETE FROM creditor_payments WHERE sale_id = ? AND source = 'venda'", [$saleId]);
        $rows = Database::all('SELECT id, amount FROM sale_payments WHERE sale_id = ? AND to_creditor = 1', [$saleId]);
        if (!$rows) {
            return;
        }
        if (!$sellerId) {
            throw new DomainException('Para registrar envio ao credor, selecione o vendedor que recebeu o pagamento.');
        }
        foreach ($rows as $r) {
            Database::insert(
                "INSERT INTO creditor_payments (seller_id, sale_id, sale_payment_id, amount, paid_on, note, source) VALUES (?,?,?,?,?,?, 'venda')",
                [$sellerId, $saleId, $r['id'], $r['amount'], $paidOn, "VENDA #$saleId"]
            );
        }
    }

    private static function itemCategory(?int $productId): string
    {
        if (!$productId) {
            return 'servico';
        }
        return (string) (Database::value('SELECT category FROM products WHERE id = ?', [$productId]) ?: 'pneu');
    }

    /**
     * Cadastro obrigatorio no PDV: nome, CPF/CNPJ, telefone e carro (CEP opcional; se preenchido, 8 numeros).
     * @param array<string,mixed>|null $c
     */
    public static function assertPdvCustomer(?array $c): void
    {
        $c ??= [];
        $missing = [];
        if (trim((string) ($c['first_name'] ?? '')) === '') {
            $missing[] = 'NOME';
        }
        $email = trim((string) ($c['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $missing[] = 'E-MAIL (invalido; pode deixar em branco)';
        }
        if (!in_array(strlen(digits($c['tax_id'] ?? '')), [11, 14], true)) {
            $missing[] = 'CPF/CNPJ (11 ou 14 numeros)';
        }
        if (!in_array(strlen(digits($c['phone'] ?? '')), [10, 11], true)) {
            $missing[] = 'TELEFONE (com DDD)';
        }
        $zipDigits = digits($c['zip'] ?? '');
        if ($zipDigits !== '' && strlen($zipDigits) !== 8) {
            $missing[] = 'CEP (8 numeros; pode deixar em branco)';
        }
        if (trim((string) ($c['car'] ?? '')) === '') {
            $missing[] = 'CARRO';
        }
        if ($missing) {
            throw new DomainException('Preencha corretamente: ' . implode(', ', $missing) . '.');
        }
    }

    /** @return array{id:int,name:string,cost_price:float|string,stock:int|string} */
    private static function product(int $id): array
    {
        $p = Database::one('SELECT id, name, cost_price, stock FROM products WHERE id = ? FOR UPDATE', [$id]);
        if (!$p) {
            throw new DomainException('Produto nao encontrado.');
        }
        return $p;
    }

    /** Soma $delta ao estoque (negativo = baixa) e registra o movimento. $strict: nao deixa o estoque ficar negativo. */
    private static function moveStock(int $productId, int $delta, string $reason, int $saleId, bool $strict = false): void
    {
        if ($delta === 0) {
            return;
        }
        $p = self::product($productId);
        if ($strict && (int) $p['stock'] + $delta < 0) {
            throw new DomainException(sprintf('O pneu usado "%s" desta troca ja saiu do estoque; nao da para desfazer/reduzir a troca.', $p['name']));
        }
        Database::run('UPDATE products SET stock = stock + ? WHERE id = ?', [$delta, $productId]);
        Database::insert(
            'INSERT INTO stock_movements (product_id, type, quantity, reason, sale_id) VALUES (?,?,?,?,?)',
            [$productId, 'adjust', $delta, $reason, $saleId]
        );
    }

    /** Troca editada: acerta quantidade, valor, categoria e identificacao do usado que entrou no estoque. */
    private static function updateTradeProduct(int $productId, int $oldQty, array $p, int $saleId): void
    {
        $qty = $p['trade_qty'];
        $unit = round($p['amount'] / $qty, 2);
        self::moveStock($productId, $qty - $oldQty, "Edicao da troca na venda #$saleId", $saleId, true);
        [$name, $w, $pf, $rim] = self::tradeIdentity($p['note'], $p['trade_category'] ?? 'pneu');
        $cat = ($p['trade_category'] ?? 'pneu') === 'roda' ? 'roda' : 'pneu';
        Database::run(
            'UPDATE products SET name = ?, description = ?, width = ?, profile = ?, rim = ?, cost_price = ?, price = ?, category = ? WHERE id = ?',
            [$name, $p['note'], $w, $pf, $rim, $unit, $unit, $cat, $productId]
        );
    }

    private static function validDateTime(?string $v): ?string
    {
        $v = trim((string) $v);
        if (!preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(?::\d{2})?$/', $v, $m) || !self::validDate($m[1])) {
            return null;
        }
        return $m[1] . ' ' . $m[2] . ':00';
    }

    /** @return list<array{product_id:?int,name:string,quantity:int,unit_price:float,category:string}> */
    public static function normalizeItems(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) ($r['name'] ?? ''));
            $pid = !empty($r['product_id']) ? (int) $r['product_id'] : null;
            $qty = (int) ($r['quantity'] ?? 0);
            $price = round((float) ($r['unit_price'] ?? 0), 2);
            if ($pid === null && $name === '') {
                continue; // linha vazia
            }
            if ($qty < 1) {
                throw new DomainException('Informe a quantidade de cada item (minimo 1).');
            }
            if ($price < 0) {
                throw new DomainException('Preco unitario nao pode ser negativo.');
            }
            $cat = (string) ($r['category'] ?? '');
            if ($pid === null) {
                $cat = 'servico'; // sem produto do estoque = servico/taxa (nao mexe no estoque)
            } elseif (!isset(self::ITEM_CATEGORIES[$cat])) {
                $cat = 'pneu';
            }
            $out[] = ['product_id' => $pid, 'name' => $name, 'quantity' => $qty, 'unit_price' => $price, 'category' => $cat];
        }
        if (!$out) {
            throw new DomainException('Adicione ao menos um item na venda.');
        }
        return $out;
    }

    /** Uma conta a receber por pagamento "a receber": cada um com a sua data prevista (sem data: a do formulario, ou +30 dias). */
    private static function insertReceivables(int $saleId, ?int $customerId, array $payments, mixed $fallbackDue, ?string $paidAt): void
    {
        $fallback = self::validDate($fallbackDue); // sem data: fica em aberto (sem vencimento)
        $n = 0;
        foreach ($payments as $p) {
            if ($p['method'] !== 'prazo') {
                continue;
            }
            $n++;
            Database::insert(
                'INSERT INTO accounts_receivable (sale_id, customer_id, description, amount, due_date, paid_at) VALUES (?,?,?,?,?,?)',
                [$saleId, $customerId, "Venda #$saleId (a receber" . ($n > 1 ? " $n" : '') . ')', $p['amount'], self::validDate($p['due'] ?? null) ?? $fallback, $paidAt]
            );
        }
    }

    /** @return list<array{method:string,amount:float,note:?string,trade_qty:int,trade_category:string,to_creditor:bool}> */
    public static function normalizePayments(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $method = (string) ($r['method'] ?? '');
            $amount = round((float) ($r['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            if (!in_array($method, self::METHODS, true)) {
                throw new DomainException('Forma de pagamento invalida.');
            }
            $note = self::clip($r['note'] ?? null, 200);
            if ($method === 'troca' && ($note === null || $note === '')) {
                throw new DomainException('Na troca, descreva o item recebido.');
            }
            $tc = (string) ($r['trade_category'] ?? 'pneu');
            if (!isset(self::TRADE_CATEGORIES[$tc])) {
                $tc = 'pneu';
            }
            $card = ['machine_id' => null, 'card_brand' => null, 'card_kind' => null, 'card_installments' => null, 'fee_rate' => null, 'fee_amount' => 0.0];
            if ($method === 'cartao' && !empty($r['machine_id'])) {
                $card = CardFees::resolve($r, $amount); // maquina escolhida: grava a taxa vigente
            }
            $out[] = ['method' => $method, 'amount' => $amount, 'note' => $note, 'trade_qty' => max(1, (int) ($r['trade_qty'] ?? 1)), 'trade_category' => $tc,
                'to_creditor' => !empty($r['to_creditor']) && in_array($method, ['dinheiro', 'pix', 'cartao'], true),
                'due' => $method === 'prazo' ? self::validDate($r['due'] ?? null) : null] + $card;
        }
        if (!$out) {
            throw new DomainException('Informe como o cliente pagou.');
        }
        return $out;
    }

    private static function assertNfeCustomer(?array $c): void
    {
        $missing = [];
        if (!$c || digits($c['tax_id'] ?? '') === '') {
            $missing[] = 'CPF/CNPJ';
        }
        foreach (self::ADDRESS_FIELDS as $key => $label) {
            if (!$c || trim((string) ($c[$key] ?? '')) === '') {
                $missing[] = $label;
            }
        }
        if ($missing) {
            throw new DomainException('Para emitir NF-e, preencha: ' . implode(', ', $missing) . '.');
        }
    }

    private static function resolveCustomer(?array $c): ?int
    {
        if (!$c) {
            return null;
        }
        $fields = ['first_name', 'last_name', 'email', 'phone', 'tax_id', 'car', 'street', 'number', 'district', 'city', 'state', 'zip', 'notes'];
        $clean = [];
        foreach ($fields as $f) {
            $clean[$f] = trim((string) ($c[$f] ?? ''));
        }
        $clean['tax_id'] = digits($clean['tax_id']);
        $clean['phone'] = format_phone($clean['phone']);
        $clean['state'] = strtoupper(substr($clean['state'], 0, 2));

        $id = !empty($c['customer_id']) ? (int) $c['customer_id'] : null;
        if (!$id && $clean['tax_id'] !== '') {
            $id = Database::value('SELECT id FROM customers WHERE tax_id = ? LIMIT 1', [$clean['tax_id']]) ?: null;
        }
        if (!$id && digits($clean['phone']) !== '') {
            $id = Database::value('SELECT id FROM customers WHERE phone = ? LIMIT 1', [$clean['phone']]) ?: null;
        }

        if ($id) {
            // Atualiza apenas o que foi preenchido, sem apagar dados existentes.
            $sets = [];
            $vals = [];
            foreach ($clean as $f => $v) {
                if ($v !== '' && $f !== 'first_name' && $f !== 'last_name') {
                    $sets[] = "$f = ?";
                    $vals[] = $v;
                }
            }
            if ($sets) {
                $vals[] = $id;
                Database::run('UPDATE customers SET ' . implode(', ', $sets) . ' WHERE id = ?', $vals);
            }
            return (int) $id;
        }

        if ($clean['first_name'] === '') {
            return null; // venda sem cliente identificado
        }
        $cols = array_keys($clean);
        return Database::insert(
            'INSERT INTO customers (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')',
            array_map(fn ($k, $v) => $v === '' && $k !== 'last_name' ? null : $v, array_keys($clean), array_values($clean))
        );
    }

    private static function addItem(int $saleId, array $item, bool $allowNegative): void
    {
        // Servico/taxa: linha sem produto, nao mexe no estoque.
        if (!$item['product_id']) {
            Database::insert(
                'INSERT INTO sale_items (sale_id, product_id, category, description, quantity, unit_price, unit_cost) VALUES (?,?,?,?,?,?,?)',
                [$saleId, null, 'servico', self::clip($item['name'], 200), $item['quantity'], $item['unit_price'], 0]
            );
            return;
        }
        $p = Database::one('SELECT id, name, cost_price, stock, category FROM products WHERE id = ? FOR UPDATE', [$item['product_id']]);
        if (!$p) {
            throw new DomainException('Produto nao encontrado.');
        }
        if (!$allowNegative && (int) $p['stock'] < $item['quantity']) {
            throw new DomainException(sprintf(
                'Estoque insuficiente de "%s": disponivel %d, pedido %d.',
                $p['name'],
                (int) $p['stock'],
                $item['quantity']
            ));
        }

        Database::insert(
            'INSERT INTO sale_items (sale_id, product_id, category, description, quantity, unit_price, unit_cost) VALUES (?,?,?,?,?,?,?)',
            [$saleId, $p['id'], $p['category'] ?: 'pneu', self::clip($p['name'], 200), $item['quantity'], $item['unit_price'], $p['cost_price']]
        );
        Database::run('UPDATE products SET stock = stock - ? WHERE id = ?', [$item['quantity'], $p['id']]);
        Database::insert(
            'INSERT INTO stock_movements (product_id, type, quantity, reason, sale_id) VALUES (?,?,?,?,?)',
            [$p['id'], 'out', -$item['quantity'], "Venda #$saleId", $saleId]
        );
    }

    /** Item recebido em troca entra no estoque como usado (custo = valor creditado). Com a medida na descricao (ex.: 175/65 R14), fica localizavel por medida. */
    private static function receiveTradeIn(int $saleId, array $p): int
    {
        $qty = $p['trade_qty'];
        $unit = round($p['amount'] / $qty, 2);
        [$name, $w, $pf, $rim] = self::tradeIdentity($p['note'], $p['trade_category'] ?? 'pneu');
        $cat = ($p['trade_category'] ?? 'pneu') === 'roda' ? 'roda' : 'pneu';
        $pid = Database::insert(
            "INSERT INTO products (name, description, condition_type, width, profile, rim, cost_price, price, stock, category)
             VALUES (?, ?, 'usado', ?, ?, ?, ?, ?, ?, ?)",
            [$name, $p['note'], $w, $pf, $rim, $unit, $unit, $qty, $cat]
        );
        Database::insert(
            'INSERT INTO stock_movements (product_id, type, quantity, reason, sale_id) VALUES (?,?,?,?,?)',
            [$pid, 'in', $qty, "Troca na venda #$saleId", $saleId]
        );
        return $pid;
    }

    /** @return array{0:?string,1:?string,2:?string,3:?string} nome, largura, perfil, aro do usado recebido */
    private static function tradeIdentity(?string $note, string $category): array
    {
        $note = (string) $note;
        if ($category === 'roda') {
            if (preg_match('/^(.+?)\s+R(\d{2})\s+-\s+(.+)$/u', trim($note), $m)) { // padrao: MARCA R18 - ACABAMENTO
                return [self::clip('JOGO DE RODA USADA ' . trim($note), 200), null, null, $m[2]];
            }
            return [self::clip('RODA USADA - ' . $note, 200), null, null, preg_match('/\bARO\s*(\d{2})\b|\bR\s?(\d{2})\b/i', $note, $m) ? ($m[1] ?: $m[2]) : null];
        }
        if (preg_match('/(\d{3})\s*[\/x]\s*(\d{2})\s*(?:z?r)?\s*(\d{2})/i', $note, $m)) {
            return ["Pneu $m[1]/$m[2] R$m[3] (usado)", $m[1], $m[2], $m[3]];
        }
        return [self::clip($note, 200), null, null, null];
    }

    private static function clip(?string $v, int $max): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private static function validDate(?string $v): ?string
    {
        if (!$v || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $v));
        return checkdate($m, $d, $y) ? $v : null;
    }
}
