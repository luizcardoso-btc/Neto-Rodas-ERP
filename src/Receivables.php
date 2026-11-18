<?php
declare(strict_types=1);

namespace App;

use DomainException;

/** Contas a receber (vendas a prazo e lancamentos avulsos): editar, receber (total ou parte), reabrir e apagar. */
final class Receivables
{
    /** @return array<string,mixed> */
    private static function get(int $id): array
    {
        $r = Database::one('SELECT * FROM accounts_receivable WHERE id = ?', [$id]);
        if (!$r) {
            throw new DomainException('Conta a receber nao encontrada.');
        }
        return $r;
    }

    /** Edita data prevista e descricao; o valor so muda em lancamento avulso (o da venda muda em "Editar venda"). */
    public static function update(int $id, array $d): void
    {
        $r = self::get($id);
        $due = trim((string) ($d['due_date'] ?? ''));
        if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            throw new DomainException('Data invalida.');
        }
        $desc = upper((string) ($d['description'] ?? '')) ?: $r['description'];
        $amount = (float) $r['amount'];
        if (!$r['sale_id'] && trim((string) ($d['amount'] ?? '')) !== '') {
            $amount = parse_money($d['amount']);
            if ($amount <= 0) {
                throw new DomainException('Informe um valor maior que zero.');
            }
        }
        Database::run('UPDATE accounts_receivable SET due_date = ?, description = ?, amount = ? WHERE id = ?', [$due ?: null, mb_substr($desc, 0, 200), $amount, $id]);
    }

    /** Recebe tudo ou so uma parte (a parte recebida vira um lancamento pago; o restante continua em aberto). */
    public static function receive(int $id, mixed $partial = null, ?string $newDue = null): void
    {
        $r = self::get($id);
        if ($r['paid_at']) {
            return;
        }
        $part = trim((string) $partial) === '' ? 0.0 : parse_money($partial);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($part > 0 && $part < (float) $r['amount'] - 0.004) {
                Database::insert(
                    'INSERT INTO accounts_receivable (sale_id, customer_id, description, amount, due_date, paid_at) VALUES (?,?,?,?,?,NOW())',
                    [$r['sale_id'], $r['customer_id'], mb_substr($r['description'] . ' (PARTE RECEBIDA)', 0, 200), round($part, 2), $r['due_date']]
                );
                $nd = $newDue && preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDue) ? $newDue : $r['due_date'];
                Database::run('UPDATE accounts_receivable SET amount = ?, due_date = ? WHERE id = ?', [round((float) $r['amount'] - $part, 2), $nd, $id]);
            } else {
                Database::run('UPDATE accounts_receivable SET paid_at = NOW() WHERE id = ?', [$id]);
            }
            self::syncSale($r['sale_id']);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Desfaz o recebimento (marcado como pago por engano). */
    public static function reopen(int $id): void
    {
        $r = self::get($id);
        Database::run('UPDATE accounts_receivable SET paid_at = NULL WHERE id = ?', [$id]);
        self::syncSale($r['sale_id']);
    }

    /** Apaga a cobranca. A venda continua registrada (para trocar a forma de pagamento, use "Editar venda"). */
    public static function delete(int $id): void
    {
        $r = self::get($id);
        Database::run('DELETE FROM accounts_receivable WHERE id = ?', [$id]);
        self::syncSale($r['sale_id']);
    }

    /** Venda fica "pendente" enquanto tiver alguma conta a receber em aberto. */
    private static function syncSale(mixed $saleId): void
    {
        if (!$saleId) {
            return;
        }
        $open = (int) Database::value('SELECT COUNT(*) FROM accounts_receivable WHERE sale_id = ? AND paid_at IS NULL', [$saleId]);
        Database::run('UPDATE sales SET payment_status = ? WHERE id = ?', [$open ? 'pending' : 'paid', $saleId]);
    }

    /** @return list<array<string,mixed>> */
    public static function list(bool $openOnly = true, int $limit = 300): array
    {
        return Database::all(
            "SELECT a.*, CONCAT_WS(' ', c.first_name, c.last_name) customer, c.phone
             FROM accounts_receivable a LEFT JOIN customers c ON c.id = a.customer_id
             " . ($openOnly ? 'WHERE a.paid_at IS NULL' : '') . "
             ORDER BY (a.paid_at IS NOT NULL), a.due_date IS NULL, a.due_date, a.id DESC LIMIT " . max(1, $limit)
        );
    }
}
