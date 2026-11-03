<?php
declare(strict_types=1);

namespace App;

use DomainException;

/** Maquinas de cartao (ex.: amarela e azul) e a taxa de cada uma por bandeira e parcela. A taxa e gravada em cada pagamento. */
final class CardFees
{
    /** Grupos de bandeira da tabela de taxas. */
    public const BRANDS = ['vm' => 'Visa / Master', 'elo' => 'Elo', 'amex' => 'Amex'];

    /** @return list<array{id:int,name:string}> */
    public static function machines(bool $onlyActive = true): array
    {
        return Database::all('SELECT id, name FROM card_machines' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY id');
    }

    /** Taxas para o JavaScript: [maquina][bandeira]['d' ou parcelas] = percentual. @return array<int,array<string,array<string,float>>> */
    public static function rateMap(): array
    {
        $map = [];
        foreach (Database::all('SELECT machine_id, brand, kind, installments, rate FROM card_rates') as $r) {
            $map[(int) $r['machine_id']][$r['brand']][$r['kind'] === 'debito' ? 'd' : (string) (int) $r['installments']] = (float) $r['rate'];
        }
        return $map;
    }

    /**
     * Valida a escolha da maquina e calcula a taxa de um pagamento em cartao.
     * @param array<string,mixed> $p machine_id, card_brand, card_inst ('d' = debito ou 1..12)
     * @return array{machine_id:int,card_brand:string,card_kind:string,card_installments:int,fee_rate:?float,fee_amount:float}
     */
    public static function resolve(array $p, float $amount): array
    {
        $machineId = (int) ($p['machine_id'] ?? 0);
        if (!$machineId || !Database::value('SELECT id FROM card_machines WHERE id = ?', [$machineId])) {
            throw new DomainException('No pagamento em cartao, escolha a maquina (amarela ou azul).');
        }
        $brand = isset(self::BRANDS[$p['card_brand'] ?? '']) ? (string) $p['card_brand'] : 'vm';
        $inst = (string) ($p['card_inst'] ?? '');
        if ($inst === 'd') {
            $kind = 'debito';
            $n = 1;
        } else {
            $n = (int) $inst;
            if ($n < 1 || $n > 12) {
                throw new DomainException('No cartao, escolha debito ou o numero de parcelas (1x a 12x).');
            }
            $kind = 'credito';
        }
        $rate = Database::value('SELECT rate FROM card_rates WHERE machine_id = ? AND brand = ? AND kind = ? AND installments = ?', [$machineId, $brand, $kind, $n]);
        $rate = $rate === null || $rate === false ? null : (float) $rate;
        return [
            'machine_id' => $machineId, 'card_brand' => $brand, 'card_kind' => $kind, 'card_installments' => $n,
            'fee_rate' => $rate, 'fee_amount' => $rate === null ? 0.0 : round($amount * $rate / 100, 2),
        ];
    }

    /** Taxas gravadas no periodo, por maquina. @return list<array{id:int,name:string,n:int,gross:float,fees:float}> */
    public static function byMachine(string $from, string $to): array
    {
        return Database::all(
            "SELECT m.id, m.name, COUNT(p.id) n, COALESCE(SUM(p.amount),0) gross, COALESCE(SUM(p.fee_amount),0) fees
             FROM card_machines m
             LEFT JOIN (SELECT sp.* FROM sale_payments sp JOIN sales s ON s.id = sp.sale_id WHERE sp.method = 'cartao' AND s.created_at >= ? AND s.created_at < DATE_ADD(?, INTERVAL 1 DAY)) p ON p.machine_id = m.id
             GROUP BY m.id, m.name ORDER BY m.id",
            [$from, $to]
        );
    }

    /** Admin: grava a tabela de taxas (campo rate[maquina][bandeira][d|1..12]). */
    public static function saveRates(array $post): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ((array) ($post['rate'] ?? []) as $mid => $brands) {
                foreach ((array) $brands as $brand => $cols) {
                    if (!isset(self::BRANDS[$brand])) {
                        continue;
                    }
                    foreach ((array) $cols as $col => $val) {
                        $val = trim((string) $val);
                        $kind = $col === 'd' ? 'debito' : 'credito';
                        $n = $col === 'd' ? 1 : (int) $col;
                        if ($n < 1 || $n > 12) {
                            continue;
                        }
                        if ($val === '') {
                            Database::run('DELETE FROM card_rates WHERE machine_id = ? AND brand = ? AND kind = ? AND installments = ?', [(int) $mid, $brand, $kind, $n]);
                            continue;
                        }
                        $rate = max(0, min(100, parse_money($val)));
                        Database::run(
                            'INSERT INTO card_rates (machine_id, brand, kind, installments, rate) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE rate = VALUES(rate)',
                            [(int) $mid, $brand, $kind, $n, $rate]
                        );
                    }
                }
            }
            foreach ((array) ($post['machine_name'] ?? []) as $mid => $name) {
                $name = upper(trim((string) $name));
                if ($name !== '') {
                    Database::run('UPDATE card_machines SET name = ? WHERE id = ?', [mb_substr($name, 0, 60), (int) $mid]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
