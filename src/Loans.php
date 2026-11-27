<?php
declare(strict_types=1);

namespace App;

use DomainException;

/**
 * Emprestimos de pneus/rodas com parceiros.
 *  - "out" (emprestamos): o pneu sai do nosso estoque. Fecha quando o parceiro devolve (volta ao estoque)
 *    ou paga (vira uma VENDA de atacado para o parceiro: entra na receita e no custo).
 *  - "in" (pegamos emprestado): pode entrar no nosso estoque para vender no PDV. Fecha quando devolvemos
 *    (sai do estoque) ou pagamos (conta paga de mercadoria no Financeiro).
 */
final class Loans
{
    /** @param array<string,mixed> $d */
    public static function create(array $d, ?string $user = null): int
    {
        $dir = ($d['direction'] ?? '') === 'in' ? 'in' : (($d['direction'] ?? '') === 'out' ? 'out' : '');
        if ($dir === '') {
            throw new DomainException('Escolha se a loja PEGOU emprestado ou EMPRESTOU.');
        }
        $partner = upper((string) ($d['partner'] ?? ''));
        if ($partner === '') {
            throw new DomainException('Informe a empresa parceira.');
        }
        $qty = (int) ($d['quantity'] ?? 0);
        if ($qty < 1) {
            throw new DomainException('Informe a quantidade (minimo 1).');
        }
        $pid = !empty($d['product_id']) ? (int) $d['product_id'] : null;
        $product = $pid ? Database::one('SELECT * FROM products WHERE id = ?', [$pid]) : null;
        if ($pid && !$product) {
            throw new DomainException('Produto nao encontrado no estoque.');
        }
        $desc = upper((string) ($d['description'] ?? '')) ?: upper((string) ($product['name'] ?? ''));
        if ($desc === '') {
            throw new DomainException('Escolha o produto do estoque ou descreva o pneu/roda (medida, marca).');
        }
        $value = trim((string) ($d['unit_value'] ?? '')) === '' ? null : parse_money($d['unit_value']);
        $date = self::date($d['loan_date'] ?? null) ?? date('Y-m-d');
        $due = self::date($d['due_date'] ?? null);
        // Emprestamos: sai do estoque se o produto foi escolhido. Pegamos: entra so se pedido.
        $move = $product && ($dir === 'out' || !empty($d['add_stock']));
        if ($move && $dir === 'out' && (int) $product['stock'] < $qty) {
            throw new DomainException(sprintf('Estoque insuficiente de "%s": tem %d, emprestando %d.', $product['name'], $product['stock'], $qty));
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $id = Database::insert(
                'INSERT INTO loans (direction, partner, partner_phone, product_id, description, quantity, unit_value, stock_moved, loan_date, due_date, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$dir, mb_substr($partner, 0, 120), format_phone((string) ($d['partner_phone'] ?? '')) ?: null, $pid, mb_substr($desc, 0, 200), $qty, $value, $move ? 1 : 0, $date, $due,
                    mb_substr(upper((string) ($d['notes'] ?? '')), 0, 255) ?: null, $user]
            );
            if ($move) {
                self::stock($pid, $dir === 'out' ? -$qty : $qty, ($dir === 'out' ? 'Emprestado para ' : 'Pegamos emprestado de ') . $partner . " (emprestimo #$id)");
            }
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Baixa (total ou parcial): devolucao do pneu ou pagamento em dinheiro.
     * @param array<string,mixed> $d
     */
    public static function settle(int $loanId, array $d, ?array $user = null): void
    {
        $loan = self::find($loanId);
        if (!$loan) {
            throw new DomainException('Emprestimo nao encontrado.');
        }
        if ($loan['status'] === 'fechado') {
            throw new DomainException('Este emprestimo ja foi fechado.');
        }
        $kind = ($d['kind'] ?? '') === 'pagamento' ? 'pagamento' : 'devolucao';
        $qty = (int) ($d['quantity'] ?? 0);
        if ($qty < 1 || $qty > $loan['pending']) {
            throw new DomainException(sprintf('Quantidade invalida: faltam %d para fechar este emprestimo.', $loan['pending']));
        }
        $date = self::date($d['settle_date'] ?? null) ?? date('Y-m-d');
        $amount = null;
        $method = null;
        if ($kind === 'pagamento') {
            $amount = parse_money($d['amount'] ?? 0);
            if ($amount <= 0) {
                throw new DomainException('Informe o valor pago.');
            }
            $method = in_array($d['method'] ?? '', ['dinheiro', 'pix', 'prazo'], true) ? $d['method'] : 'pix';
        }
        $partner = $loan['partner'];
        $saleId = null;
        $payableId = null;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($kind === 'devolucao') {
                // Devolveu outro pneu (outra marca/medida)? O estoque mexe no pneu que voltou (ou saiu) de fato.
                [$retPid, $retDesc] = self::returnedProduct($d, $loan, $qty);
                $pid = $retPid ?: ($loan['stock_moved'] ? (int) $loan['product_id'] : 0);
                if ($pid) {
                    self::stock($pid, $loan['direction'] === 'out' ? $qty : -$qty,
                        ($loan['direction'] === 'out' ? 'Devolvido por ' : 'Devolvido para ') . $partner . " (emprestimo #$loanId)" . ($retDesc ? ' - outro pneu: ' . $retDesc : ''));
                }
            } elseif ($loan['direction'] === 'out') {
                // Parceiro pagou: vira venda de atacado (receita + custo). O pneu ja tinha saido do estoque no emprestimo:
                // a venda da a baixa de novo e aqui devolvemos a mesma quantidade, para nao contar duas vezes.
                $pdo->rollBack(); // a venda abre a propria transacao
                $unit = round($amount / $qty, 2);
                $sale = SaleService::create([
                    'request_token' => self::uuid(),
                    'sale_type' => 'atacado',
                    'notes' => "EMPRESTIMO #$loanId - $partner",
                    'customer' => ['first_name' => $partner, 'phone' => (string) $loan['partner_phone'], 'notes' => 'EMPRESA PARCEIRA (EMPRESTIMOS)'],
                    'items' => [['product_id' => $loan['stock_moved'] ? $loan['product_id'] : null, 'name' => $loan['description'], 'quantity' => $qty,
                        'unit_price' => $unit, 'category' => 'pneu']],
                    'payments' => [['method' => $method, 'amount' => $unit * $qty, 'due' => self::date($d['due'] ?? null)]],
                ], 'emprestimo', true);
                $saleId = $sale['id'];
                $pdo->beginTransaction();
                if ($loan['stock_moved'] && $loan['product_id']) {
                    self::stock((int) $loan['product_id'], $qty, "Emprestimo #$loanId pago (venda #$saleId)");
                }
            } else {
                // Nos pagamos o parceiro: conta de mercadoria paga no Financeiro.
                $payableId = Database::insert(
                    "INSERT INTO accounts_payable (description, category, amount, due_date, paid_at, kind) VALUES (?, 'PNEUS', ?, ?, ?, 'ativo')",
                    ["EMPRESTIMO #$loanId - PAGO A $partner ($qty x {$loan['description']})", $amount, $date, $date . ' 12:00:00']
                );
            }
            Database::insert(
                'INSERT INTO loan_settlements (loan_id, kind, quantity, amount, method, sale_id, payable_id, returned_product_id, returned_desc, settle_date, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$loanId, $kind, $qty, $amount, $method, $saleId, $payableId, $retPid ?? null, $retDesc ?? null, $date, mb_substr(upper((string) ($d['notes'] ?? '')), 0, 255) ?: null]
            );
            if ($qty === $loan['pending']) {
                Database::run("UPDATE loans SET status = 'fechado' WHERE id = ?", [$loanId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Edita os dados do emprestimo. Mudou a quantidade: o estoque acompanha (se o emprestimo mexeu no estoque). */
    public static function update(int $loanId, array $d): void
    {
        $loan = self::find($loanId);
        if (!$loan) {
            throw new DomainException('Emprestimo nao encontrado.');
        }
        $partner = upper((string) ($d['partner'] ?? '')) ?: $loan['partner'];
        $qty = (int) ($d['quantity'] ?? $loan['quantity']);
        if ($qty < max(1, $loan['settled'])) {
            throw new DomainException(sprintf('A quantidade nao pode ser menor que %d (ja devolvido/pago).', max(1, $loan['settled'])));
        }
        $diff = $qty - $loan['quantity'];
        if ($diff > 0 && $loan['stock_moved'] && $loan['direction'] === 'out' && $loan['product_id']
            && (int) Database::value('SELECT stock FROM products WHERE id = ?', [$loan['product_id']]) < $diff) {
            throw new DomainException('Estoque insuficiente para aumentar a quantidade emprestada.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($diff !== 0 && $loan['stock_moved'] && $loan['product_id']) {
                self::stock((int) $loan['product_id'], $loan['direction'] === 'out' ? -$diff : $diff, "Emprestimo #$loanId editado (quantidade {$loan['quantity']} -> $qty)");
            }
            $value = trim((string) ($d['unit_value'] ?? '')) === '' ? null : parse_money($d['unit_value']);
            Database::run(
                'UPDATE loans SET partner = ?, partner_phone = ?, description = ?, quantity = ?, unit_value = ?, loan_date = ?, due_date = ?, notes = ?, status = ? WHERE id = ?',
                [mb_substr($partner, 0, 120), format_phone((string) ($d['partner_phone'] ?? '')) ?: null, mb_substr(upper((string) ($d['description'] ?? '')) ?: $loan['description'], 0, 200),
                    $qty, $value, self::date($d['loan_date'] ?? null) ?? $loan['loan_date'], self::date($d['due_date'] ?? null),
                    mb_substr(upper((string) ($d['notes'] ?? '')), 0, 255) ?: null, $qty - $loan['settled'] > 0 ? 'aberto' : 'fechado', $loanId]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Desfaz uma baixa lancada errado: devolucao volta o estoque; pagamento apaga a venda/conta que ele gerou. */
    public static function undoSettlement(int $settlementId): void
    {
        $s = Database::one('SELECT s.*, l.direction, l.stock_moved, l.product_id FROM loan_settlements s JOIN loans l ON l.id = s.loan_id WHERE s.id = ?', [$settlementId]);
        if ($s && $s['returned_product_id']) { // devolucao com outro pneu: desfaz no pneu que voltou
            $s['product_id'] = $s['returned_product_id'];
            $s['stock_moved'] = 1;
        }
        if (!$s) {
            return;
        }
        if ($s['kind'] === 'pagamento' && $s['sale_id'] && Database::value('SELECT id FROM sales WHERE id = ?', [$s['sale_id']])) {
            SaleService::delete((int) $s['sale_id']); // reabre o emprestimo (onSaleDeleted)
            return;
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($s['kind'] === 'devolucao' && $s['stock_moved'] && $s['product_id']) {
                self::stock((int) $s['product_id'], $s['direction'] === 'out' ? -(int) $s['quantity'] : (int) $s['quantity'], "Baixa desfeita (emprestimo #{$s['loan_id']})");
            }
            if ($s['payable_id']) {
                Database::run('DELETE FROM accounts_payable WHERE id = ?', [$s['payable_id']]);
            }
            Database::run('DELETE FROM loan_settlements WHERE id = ?', [$settlementId]);
            Database::run("UPDATE loans SET status = 'aberto' WHERE id = ?", [$s['loan_id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Apaga o emprestimo por completo: desfaz as baixas (e as vendas/contas que geraram) e o estoque. */
    public static function delete(int $loanId): void
    {
        foreach (Database::all('SELECT id FROM loan_settlements WHERE loan_id = ? ORDER BY id DESC', [$loanId]) as $s) {
            self::undoSettlement((int) $s['id']);
        }
        $loan = self::find($loanId);
        if (!$loan) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($loan['stock_moved'] && $loan['product_id']) {
                self::stock((int) $loan['product_id'], $loan['direction'] === 'out' ? (int) $loan['quantity'] : -(int) $loan['quantity'], "Emprestimo #$loanId apagado");
            }
            Database::run('DELETE FROM loans WHERE id = ?', [$loanId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Venda gerada por um emprestimo pago foi apagada: o emprestimo volta a ficar em aberto (o pneu continua com o parceiro). */
    public static function onSaleDeleted(int $saleId): void
    {
        foreach (Database::all('SELECT s.*, l.stock_moved, l.product_id FROM loan_settlements s JOIN loans l ON l.id = s.loan_id WHERE s.sale_id = ?', [$saleId]) as $s) {
            if ($s['stock_moved'] && $s['product_id']) {
                self::stock((int) $s['product_id'], -(int) $s['quantity'], "Venda #$saleId apagada: pneu segue com o parceiro (emprestimo #{$s['loan_id']})");
            }
            Database::run('DELETE FROM loan_settlements WHERE id = ?', [$s['id']]);
            Database::run("UPDATE loans SET status = 'aberto' WHERE id = ?", [$s['loan_id']]);
        }
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        $l = Database::one('SELECT l.*, COALESCE((SELECT SUM(quantity) FROM loan_settlements s WHERE s.loan_id = l.id),0) settled FROM loans l WHERE l.id = ?', [$id]);
        if ($l) {
            $l['settled'] = (int) $l['settled'];
            $l['quantity'] = (int) $l['quantity'];
            $l['pending'] = $l['quantity'] - $l['settled'];
        }
        return $l;
    }

    /** @return list<array<string,mixed>> */
    public static function list(string $status = 'aberto', string $dir = ''): array
    {
        $w = ['1=1'];
        $a = [];
        if (in_array($status, ['aberto', 'fechado'], true)) {
            $w[] = 'l.status = ?';
            $a[] = $status;
        }
        if (in_array($dir, ['in', 'out'], true)) {
            $w[] = 'l.direction = ?';
            $a[] = $dir;
        }
        $rows = Database::all(
            'SELECT l.*, COALESCE((SELECT SUM(quantity) FROM loan_settlements s WHERE s.loan_id = l.id),0) settled, p.stock product_stock, p.condition_type
             FROM loans l LEFT JOIN products p ON p.id = l.product_id WHERE ' . implode(' AND ', $w) . ' ORDER BY l.status, l.due_date IS NULL, l.due_date, l.id DESC LIMIT 300',
            $a
        );
        foreach ($rows as &$r) {
            $r['pending'] = (int) $r['quantity'] - (int) $r['settled'];
        }
        return $rows;
    }

    /** Totais em aberto: quantos pneus devemos e quantos nos devem. @return array{owe:int,owed:int,late:int} */
    public static function totals(): array
    {
        $r = Database::one(
            "SELECT
               COALESCE(SUM(CASE WHEN direction='in' THEN quantity - COALESCE((SELECT SUM(quantity) FROM loan_settlements s WHERE s.loan_id = l.id),0) END),0) owe,
               COALESCE(SUM(CASE WHEN direction='out' THEN quantity - COALESCE((SELECT SUM(quantity) FROM loan_settlements s WHERE s.loan_id = l.id),0) END),0) owed,
               COALESCE(SUM(due_date < CURDATE()),0) late
             FROM loans l WHERE status = 'aberto'"
        );
        return ['owe' => (int) $r['owe'], 'owed' => (int) $r['owed'], 'late' => (int) $r['late']];
    }

    /**
     * Pneu que voltou (ou que devolvemos) quando e diferente do emprestado: escolhido do estoque
     * ou descrito (medida + marca), que entra no estoque com a referencia e a marca.
     * @return array{0:?int,1:?string}
     */
    private static function returnedProduct(array $d, array $loan, int $qty): array
    {
        if (empty($d['other'])) {
            return [null, null];
        }
        $out = $loan['direction'] === 'out';
        if (!empty($d['ret_product_id'])) {
            $p = Database::one('SELECT id, name, brand, stock, condition_type FROM products WHERE id = ? AND active = 1', [(int) $d['ret_product_id']]);
            if (!$p) {
                throw new DomainException('Produto devolvido nao encontrado no estoque.');
            }
            if (!$out && (int) $p['stock'] < $qty) {
                throw new DomainException(sprintf('Estoque insuficiente de "%s": tem %d, devolvendo %d.', $p['name'], $p['stock'], $qty));
            }
            return [(int) $p['id'], product_label($p, false)];
        }
        $size = strtoupper(trim((string) ($d['ret_size'] ?? '')));
        $brand = upper(trim((string) ($d['ret_brand'] ?? '')));
        if (!preg_match('#^(\d{3})\s*/\s*(\d{2})\s*R?\s*(\d{2})$#', $size, $m) || $brand === '') {
            throw new DomainException('Pneu devolvido diferente: escolha do estoque ou informe a medida (ex.: 205/55 R16) e a marca.');
        }
        if (!$out) {
            throw new DomainException('Para devolver outro pneu ao parceiro, escolha o pneu que saiu do nosso estoque.');
        }
        $cond = ($d['ret_cond'] ?? '') === 'usado' ? 'usado' : 'novo';
        $name = "$m[1]/$m[2] R$m[3]";
        $desc = $name . ' ' . $brand . ($cond === 'usado' ? ' (usado)' : '');
        if (!$loan['stock_moved']) {
            return [null, $desc]; // o emprestado nao tinha saido do estoque: so registra o que voltou
        }
        $pid = Database::value(
            "SELECT id FROM products WHERE active = 1 AND category = 'pneu' AND width = ? AND profile = ? AND rim = ? AND UPPER(TRIM(brand)) = ? AND condition_type = ? ORDER BY id LIMIT 1",
            [$m[1], $m[2], $m[3], $brand, $cond]
        );
        if (!$pid) {
            $ref = $loan['product_id'] ? Database::one('SELECT cost_price, price FROM products WHERE id = ?', [$loan['product_id']]) : null;
            $pid = Database::insert(
                "INSERT INTO products (name, category, condition_type, brand, width, profile, rim, cost_price, price, stock) VALUES (?, 'pneu', ?, ?, ?, ?, ?, ?, ?, 0)",
                [$name, $cond, $brand, $m[1], $m[2], $m[3], $ref['cost_price'] ?? 0, $ref['price'] ?? 0]
            );
        }
        return [(int) $pid, $desc];
    }

    private static function stock(int $pid, int $delta, string $reason): void
    {
        Database::run('UPDATE products SET stock = stock + ? WHERE id = ?', [$delta, $pid]);
        Database::insert('INSERT INTO stock_movements (product_id, type, quantity, reason) VALUES (?,?,?,?)', [$pid, 'adjust', $delta, mb_substr($reason, 0, 200)]);
    }

    private static function date(mixed $v): ?string
    {
        $v = trim((string) $v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : null;
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
