<?php
declare(strict_types=1);

namespace App;

/** Indicadores do dashboard. Mes no formato 'YYYY-MM'. */
final class Reports
{
    /** @return array{start:string,end:string,days:int,elapsed:int} */
    public static function period(string $month, ?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01') ?: new \DateTimeImmutable('first day of this month');
        $end = $start->modify('last day of this month');
        $last = $today < $end ? ($today < $start ? $start : $today) : $end;
        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'days' => (int) $end->format('j'),
            'elapsed' => (int) $last->format('j'),
        ];
    }

    /** Folha proporcional aos dias ja decorridos do mes (pagamento semanal / 7 por dia). */
    public static function payroll(float $weeklyTotal, int $elapsedDays): float
    {
        return round($weeklyTotal / 7 * $elapsedDays, 2);
    }

    public static function summary(string $month, ?\DateTimeImmutable $today = null): array
    {
        $p = self::period($month, $today);
        $r = Database::one(
            'SELECT COUNT(*) n, COALESCE(SUM(total),0) revenue FROM sales WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)',
            [$p['start'], $p['end']]
        );
        $cmv = (float) Database::value(
            'SELECT COALESCE(SUM(i.quantity * i.unit_cost),0) FROM sale_items i JOIN sales s ON s.id = i.sale_id
             WHERE s.created_at >= ? AND s.created_at < DATE_ADD(?, INTERVAL 1 DAY)',
            [$p['start'], $p['end']]
        );
        $weekly = (float) Database::value('SELECT COALESCE(SUM(weekly_pay),0) FROM sellers WHERE active = 1');
        $payroll = (float) Database::value('SELECT COALESCE(SUM(amount),0) FROM payroll_payments WHERE paid_on >= ? AND paid_on <= ?', [$p['start'], $p['end']]);
        $expenses = (float) Database::value(
            "SELECT COALESCE(SUM(amount),0) FROM accounts_payable WHERE kind <> 'ativo' AND paid_at >= ? AND paid_at < DATE_ADD(?, INTERVAL 1 DAY)",
            [$p['start'], $p['end']]
        );
        // boletos de mercadoria (ativos) abatem da receita bruta no lugar do custo cadastrado
        $assets = (float) Database::value(
            "SELECT COALESCE(SUM(amount),0) FROM accounts_payable WHERE kind = 'ativo' AND paid_at >= ? AND paid_at < DATE_ADD(?, INTERVAL 1 DAY)",
            [$p['start'], $p['end']]
        );
        $revenue = (float) $r['revenue'];
        $profit = round($revenue - $assets - $payroll - $expenses, 2);

        return [
            'period' => $p, 'sales' => (int) $r['n'], 'revenue' => $revenue,
            'ticket' => $r['n'] ? round($revenue / (int) $r['n'], 2) : 0.0,
            'cmv' => $cmv, 'assets' => $assets, 'weekly_payroll' => $weekly, 'payroll' => $payroll, 'expenses' => $expenses,
            'profit' => $profit, 'margin' => $revenue > 0 ? round($profit / $revenue * 100, 1) : 0.0,
        ];
    }

    /** Serie diaria para o grafico: faturamento, lucro e faturamento por vendedor. */
    public static function daily(string $month, ?\DateTimeImmutable $today = null): array
    {
        $p = self::period($month, $today);
        $rows = Database::all(
            'SELECT DATE(s.created_at) d, s.seller_id, SUM(s.total) revenue FROM sales s
             WHERE s.created_at >= ? AND s.created_at < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY d, s.seller_id',
            [$p['start'], $p['end']]
        );
        $cost = Database::all(
            'SELECT DATE(s.created_at) d, SUM(i.quantity * i.unit_cost) cmv FROM sale_items i JOIN sales s ON s.id = i.sale_id
             WHERE s.created_at >= ? AND s.created_at < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY d',
            [$p['start'], $p['end']]
        );
        $weekly = (float) Database::value('SELECT COALESCE(SUM(weekly_pay),0) FROM sellers WHERE active = 1');
        $sellers = Database::all('SELECT id, name, color FROM sellers WHERE active = 1 ORDER BY name');

        $labels = [];
        $revenue = $profit = array_fill(0, $p['days'], 0.0);
        $bySeller = [];
        foreach ($sellers as $s) {
            $bySeller[(int) $s['id']] = array_fill(0, $p['days'], 0.0);
        }
        $cmvByDay = [];
        foreach ($cost as $c) {
            $cmvByDay[(int) substr($c['d'], 8, 2)] = (float) $c['cmv'];
        }
        foreach ($rows as $r) {
            $i = (int) substr($r['d'], 8, 2) - 1;
            $revenue[$i] += (float) $r['revenue'];
            if ($r['seller_id'] && isset($bySeller[(int) $r['seller_id']])) {
                $bySeller[(int) $r['seller_id']][$i] += (float) $r['revenue'];
            }
        }
        for ($d = 1; $d <= $p['days']; $d++) {
            $labels[] = str_pad((string) $d, 2, '0', STR_PAD_LEFT);
            $profit[$d - 1] = round($revenue[$d - 1] - ($cmvByDay[$d] ?? 0) - ($d <= $p['elapsed'] ? $weekly / 7 : 0), 2);
        }

        return [
            'labels' => $labels, 'revenue' => $revenue, 'profit' => $profit,
            'sellers' => array_map(fn ($s) => ['name' => $s['name'], 'color' => $s['color'], 'data' => $bySeller[(int) $s['id']]], $sellers),
        ];
    }
}
