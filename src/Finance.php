<?php
declare(strict_types=1);

namespace App;

/** Indicadores do Financeiro: resultado do periodo, credor, alertas do "contador virtual" e movimentacoes. */
final class Finance
{
    public const PERIODS = ['hoje' => 'Hoje', '7d' => '7 dias', '30d' => '30 dias', '90d' => '90 dias', 'mes' => 'Este mês', 'tudo' => 'Tudo'];
    public const EXPENSE_CATEGORIES = ['ALUGUEL', 'ENERGIA', 'ÁGUA', 'INTERNET/TELEFONE', 'MARKETING', 'MANUTENÇÃO', 'FORNECEDOR', 'COMISSÃO', 'TRANSPORTE', 'OUTROS'];
    public const ASSET_CATEGORIES = ['PNEUS', 'RODAS', 'PARAFUSOS', 'OUTRAS MERCADORIAS'];
    public const TAX_TYPES = ['DAS - SIMPLES NACIONAL', 'DARF', 'ICMS', 'ISS', 'IRPJ/CSLL', 'INSS/FGTS', 'IPTU/TAXAS', 'OUTRO IMPOSTO'];

    /** @return array{key:string,from:string,to:string,days:int,label:string} */
    public static function range(string $key, ?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');
        $key = isset(self::PERIODS[$key]) ? $key : 'mes';
        $from = match ($key) {
            'hoje' => $today,
            '7d' => $today->modify('-6 days'),
            '30d' => $today->modify('-29 days'),
            '90d' => $today->modify('-89 days'),
            'tudo' => ($min = Database::value('SELECT MIN(DATE(created_at)) FROM sales')) ? new \DateTimeImmutable((string) $min) : $today,
            default => $today->modify('first day of this month'),
        };
        return [
            'key' => $key, 'from' => $from->format('Y-m-d'), 'to' => $today->format('Y-m-d'),
            'days' => max(1, (int) $from->diff($today)->days + 1), 'label' => self::PERIODS[$key],
        ];
    }

    private static function dateRange(string $col): string
    {
        return "$col >= ? AND $col < DATE_ADD(?, INTERVAL 1 DAY)";
    }

    /** @return array<string,mixed> */
    public static function summary(string $from, string $to, int $days): array
    {
        $cfg = Settings::all();
        $a = [$from, $to];
        $r = Database::one('SELECT COUNT(*) n, COALESCE(SUM(total),0) revenue FROM sales WHERE ' . self::dateRange('created_at'), $a);
        $revenue = (float) $r['revenue'];
        $cmv = (float) Database::value('SELECT COALESCE(SUM(i.quantity * i.unit_cost),0) FROM sale_items i JOIN sales s ON s.id = i.sale_id WHERE ' . self::dateRange('s.created_at'), $a);
        // Taxa real das maquinas (gravada em cada pagamento) + aliquota geral so para cartao sem maquina (vendas antigas).
        $cardReal = (float) Database::value("SELECT COALESCE(SUM(p.fee_amount),0) FROM sale_payments p JOIN sales s ON s.id = p.sale_id WHERE p.method = 'cartao' AND p.machine_id IS NOT NULL AND " . self::dateRange('s.created_at'), $a);
        $cardBase = (float) Database::value("SELECT COALESCE(SUM(p.amount),0) FROM sale_payments p JOIN sales s ON s.id = p.sale_id WHERE p.method = 'cartao' AND p.machine_id IS NULL AND " . self::dateRange('s.created_at'), $a);
        $cardFees = round($cardReal + $cardBase * (float) $cfg['card_fee_rate'] / 100, 2);
        $weekly = (float) Database::value('SELECT COALESCE(SUM(weekly_pay),0) FROM sellers WHERE active = 1');
        $payrollEst = Reports::payroll($weekly, $days); // previsao pelo cadastro dos vendedores
        $payroll = (float) Database::value('SELECT COALESCE(SUM(amount),0) FROM payroll_payments WHERE paid_on >= ? AND paid_on <= ?', $a); // pagamentos semanais lancados
        $paid = fn (string $kind) => (float) Database::value('SELECT COALESCE(SUM(amount),0) FROM accounts_payable WHERE kind = ? AND ' . self::dateRange('paid_at'), array_merge([$kind], $a));
        $expenses = $paid('despesa');
        $assets = $paid('ativo'); // boletos de mercadoria (pneus/rodas): informativo, fora das despesas operacionais
        $taxes = $paid('imposto');
        $creditor = (float) Database::value('SELECT COALESCE(SUM(amount),0) FROM creditor_payments WHERE paid_on >= ? AND paid_on <= ?', $a);
        $gross = round($revenue - $cmv - $cardFees - $taxes, 2); // custo cadastrado dos itens vendidos; boletos de ativos sao so informativos
        $profit = round($gross - $expenses - $payroll, 2);

        // Ponto de equilibrio: custos fixos / margem de contribuicao.
        $contribution = $revenue > 0 ? ($revenue - $cmv - $cardFees) / $revenue : 0.0;
        $fixed = $expenses + $taxes + $payroll;
        $breakeven = $contribution > 0 ? round($fixed / $contribution, 2) : null;

        return [
            'sales' => (int) $r['n'], 'revenue' => $revenue, 'cmv' => $cmv, 'assets' => $assets, 'after_assets' => round($revenue - $assets, 2), 'card_fees' => $cardFees, 'payroll' => $payroll, 'payroll_est' => $payrollEst, 'weekly_total' => $weekly,
            'expenses' => $expenses, 'taxes' => $taxes, 'creditor' => $creditor, 'gross' => $gross, 'profit' => $profit,
            'margin' => $revenue > 0 ? round($profit / $revenue * 100, 1) : 0.0,
            'ticket' => $r['n'] ? round($revenue / (int) $r['n'], 2) : 0.0,
            'breakeven' => $breakeven, 'tax_estimated' => round($revenue * (float) $cfg['tax_rate'] / 100, 2),
        ];
    }

    /**
     * "Para onde vai cada R$ da receita".
     * @param array<string,mixed> $s
     * @return list<array{label:string,value:float,color:string,hint:string}>
     */
    public static function breakdown(array $s): array
    {
        $rows = [
            ['Custo das mercadorias', $s['cmv'], '#2f6fd6', 'custo cadastrado nos itens vendidos'],
            ['Impostos pagos', $s['taxes'], '#f97316', 'DAS, ICMS etc. lançados como pagos'],
            ['Taxas do cartão', $s['card_fees'], '#14b8a6', 'taxa de cada máquina (amarela/azul) por parcela'],
            ['Folha dos vendedores', $s['payroll'], '#f59e0b', 'pagamentos semanais lançados no período'],
            ['Despesas operacionais', $s['expenses'], '#e879a6', 'despesas pagas no período'],
        ];
        $out = [];
        foreach ($rows as [$l, $v, $c, $h]) {
            $out[] = ['label' => $l, 'value' => (float) $v, 'color' => $c, 'hint' => $h];
        }
        $out[] = ['label' => 'Lucro estimado final', 'value' => (float) $s['profit'], 'color' => '#15803d', 'hint' => 'o que sobra'];
        return $out;
    }

    /** @return list<array{cat:string,total:float}> */
    public static function expensesByCategory(string $from, string $to): array
    {
        return array_map(fn ($r) => ['cat' => $r['cat'], 'total' => (float) $r['total']], Database::all(
            "SELECT COALESCE(NULLIF(category,''), 'SEM CATEGORIA') cat, SUM(amount) total FROM accounts_payable
             WHERE kind = 'despesa' AND " . self::dateRange('paid_at') . ' GROUP BY cat ORDER BY total DESC',
            [$from, $to]
        ));
    }

    // ----------------------------- credor -----------------------------

    /** @return array<string,mixed> */
    public static function creditor(string $month): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
        $cfg = Settings::all();
        $goal = (float) $cfg['creditor_monthly_goal'];
        $received = (float) Database::value('SELECT COALESCE(SUM(amount),0) FROM creditor_payments WHERE paid_on BETWEEN ? AND ?', [$start, $end]);
        $bySeller = Database::all(
            "SELECT COALESCE(s.name, 'SEM VENDEDOR') name, COUNT(*) n, SUM(c.amount) total FROM creditor_payments c LEFT JOIN sellers s ON s.id = c.seller_id
             WHERE c.paid_on BETWEEN ? AND ? GROUP BY c.seller_id, s.name ORDER BY total DESC",
            [$start, $end]
        );
        $ledger = Database::all(
            "SELECT c.*, s.name seller FROM creditor_payments c LEFT JOIN sellers s ON s.id = c.seller_id
             WHERE c.paid_on BETWEEN ? AND ? ORDER BY c.paid_on DESC, c.id DESC LIMIT 200",
            [$start, $end]
        );
        $allTime = (float) Database::value('SELECT COALESCE(SUM(amount),0) FROM creditor_payments');
        return [
            'month' => $month, 'name' => $cfg['creditor_name'], 'goal' => $goal, 'received' => $received,
            'remaining' => max(0.0, round($goal - $received, 2)), 'pct' => $goal > 0 ? min(100.0, round($received / $goal * 100, 1)) : null,
            'by_seller' => $bySeller, 'ledger' => $ledger, 'all_time' => $allTime,
        ];
    }

    /**
     * Repasse manual do vendedor ao credor (nao ligado a uma venda). Bloqueia duplicidade:
     * mesmo vendedor + valor + data ja lancado exige confirmacao.
     */
    public static function addCreditorPayment(?int $sellerId, float $amount, string $date, string $note, bool $confirmDuplicate, ?string $user): void
    {
        if (!$sellerId || !Database::value('SELECT id FROM sellers WHERE id = ?', [$sellerId])) {
            throw new \DomainException('Selecione o vendedor que enviou o pagamento.');
        }
        if ($amount <= 0) {
            throw new \DomainException('Informe o valor enviado ao credor.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        $dup = Database::one(
            'SELECT id, source, sale_id FROM creditor_payments WHERE seller_id = ? AND amount = ? AND paid_on = ? LIMIT 1',
            [$sellerId, $amount, $date]
        );
        if ($dup && !$confirmDuplicate) {
            throw new \DomainException('Ja existe um repasse igual (mesmo vendedor, valor e data)'
                . ($dup['sale_id'] ? ' registrado automaticamente pela venda #' . $dup['sale_id'] : '')
                . '. Se for outro pagamento, marque "lançar mesmo assim".');
        }
        Database::insert(
            "INSERT INTO creditor_payments (seller_id, amount, paid_on, note, source, created_by) VALUES (?,?,?,?, 'manual', ?)",
            [$sellerId, $amount, $date, $note !== '' ? mb_substr($note, 0, 200) : null, $user]
        );
    }

    // ----------------------------- contador virtual -----------------------------

    /**
     * Alertas em linguagem simples. level: red (urgente), orange (atencao), blue (informativo), green (ok).
     * @param array<string,mixed> $s resumo do periodo selecionado
     * @return list<array{level:string,title:string,text:string}>
     */
    public static function alerts(array $s, ?\DateTimeImmutable $today = null): array
    {
        $today ??= new \DateTimeImmutable('today');
        $now = $today->format('Y-m-d');
        $cfg = Settings::all();
        $out = [];
        $add = function (string $level, string $title, string $text) use (&$out): void {
            $out[] = ['level' => $level, 'title' => $title, 'text' => $text];
        };

        if ($s['revenue'] > 0 && $s['profit'] < 0) {
            $add('red', 'Resultado negativo no período', 'Depois de todos os custos e despesas, o resultado é ' . money($s['profit']) . '. Revise despesas e preços.');
        } elseif ($s['revenue'] > 0 && $s['margin'] < 10) {
            $add('orange', 'Margem baixa', 'A margem líquida está em ' . number_format($s['margin'], 1, ',', '.') . '%. Abaixo de 10% sobra pouco para imprevistos.');
        } elseif ($s['revenue'] > 0) {
            $add('green', 'Resultado positivo', 'Lucro estimado de ' . money($s['profit']) . ' (margem ' . number_format($s['margin'], 1, ',', '.') . '%).');
        }

        $late = Database::one('SELECT COUNT(*) n, COALESCE(SUM(amount),0) v FROM accounts_payable WHERE paid_at IS NULL AND due_date < ?', [$now]);
        if ((int) $late['n'] > 0) {
            $add('red', 'Contas a pagar vencidas', $late['n'] . ' conta(s) vencida(s) somam ' . money($late['v']) . '. Pague ou renegocie para evitar juros.');
        }
        $soon = Database::one('SELECT COUNT(*) n, COALESCE(SUM(amount),0) v FROM accounts_payable WHERE paid_at IS NULL AND due_date BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)', [$now, $now]);
        if ((int) $soon['n'] > 0) {
            $add('orange', 'Vencendo nos próximos 7 dias', $soon['n'] . ' conta(s) somam ' . money($soon['v']) . '.');
        }
        $lateAr = Database::one('SELECT COUNT(*) n, COALESCE(SUM(amount),0) v FROM accounts_receivable WHERE paid_at IS NULL AND due_date < ?', [$now]);
        if ((int) $lateAr['n'] > 0) {
            $add('orange', 'Clientes em atraso', $lateAr['n'] . ' recebimento(s) atrasado(s) somam ' . money($lateAr['v']) . '. Cobre pelo WhatsApp.');
        }
        $openAr = (float) Database::value('SELECT COALESCE(SUM(amount),0) FROM accounts_receivable WHERE paid_at IS NULL');
        if ($openAr > 0) {
            $add('blue', 'A receber', 'Há ' . money($openAr) . ' de vendas a prazo aguardando pagamento.');
        }

        // Impostos do mes corrente
        $m1 = $today->format('Y-m-01');
        $monthRev = (float) Database::value('SELECT COALESCE(SUM(total),0) FROM sales WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)', [$m1, $now]);
        $taxPaid = (float) Database::value("SELECT COALESCE(SUM(amount),0) FROM accounts_payable WHERE kind = 'imposto' AND paid_at >= ? AND paid_at < DATE_ADD(?, INTERVAL 1 DAY)", [$m1, $now]);
        $rate = (float) $cfg['tax_rate'];
        if ($rate <= 0 && $monthRev > 0) {
            $add('blue', 'Defina a alíquota de impostos', 'Informe o percentual em "Custos e taxas" para o sistema estimar quanto reservar de imposto.');
        } elseif ($rate > 0 && $monthRev > 0) {
            $est = round($monthRev * $rate / 100, 2);
            if ($taxPaid + 0.01 < $est) {
                $add('orange', 'Reserve o imposto do mês', 'Com ' . number_format($rate, 2, ',', '.') . '% sobre ' . money($monthRev) . ' vendidos, o imposto estimado é ' . money($est) . '. Pago até agora: ' . money($taxPaid) . '.');
            }
        }
        $day = (int) $today->format('j');
        if ($day >= 10 && $day <= 20 && $taxPaid <= 0 && $monthRev > 0) {
            $add('orange', 'Guia de imposto do mês', 'Impostos como o DAS costumam vencer até o dia 20 e nenhum pagamento de imposto foi lançado neste mês.');
        }

        // Custo ausente = lucro otimista
        $noCost = (int) Database::value('SELECT COUNT(*) FROM sale_items i JOIN sales s ON s.id = i.sale_id WHERE i.product_id IS NOT NULL AND i.unit_cost <= 0 AND ' . self::dateRange('s.created_at'), [$m1, $now]);
        if ($noCost > 0) {
            $add('orange', 'Itens vendidos sem custo', $noCost . ' item(ns) vendido(s) neste mês estão com custo R$ 0,00, então o lucro mostrado está otimista. Informe o custo no Estoque.');
        }

        // Estoque
        $stock = Database::one("SELECT COUNT(*) total, SUM(stock <= 0) empty_n, COALESCE(SUM(stock*cost_price),0) capital FROM products WHERE active = 1 AND condition_type IN ('novo','atacado')");
        if ((int) $stock['empty_n'] > 0 && (int) $stock['total'] > 0) {
            $add('orange', 'Produtos sem estoque', $stock['empty_n'] . ' produto(s) novo(s) está(ão) com estoque zerado. Reponha para não perder venda.');
        }

        // Credor
        $c = self::creditor($today->format('Y-m'));
        if ($c['goal'] > 0) {
            if ($c['received'] >= $c['goal']) {
                $add('green', $c['name'] . ' já recebeu a meta do mês', 'Repasses de ' . money($c['received']) . ' (meta ' . money($c['goal']) . ').');
            } else {
                $add('blue', $c['name'] . ' no mês', 'Recebeu ' . money($c['received']) . ' via vendedores; faltam ' . money($c['remaining']) . ' para a meta de ' . money($c['goal']) . '.');
            }
        } elseif ($c['received'] > 0) {
            $add('blue', $c['name'] . ' no mês', 'Os vendedores já repassaram ' . money($c['received']) . ' neste mês.');
        }

        // Vendas paradas
        $last = Database::value('SELECT MAX(created_at) FROM sales');
        if ($last && (new \DateTimeImmutable((string) $last))->diff($today)->days >= 3 && $today > new \DateTimeImmutable((string) $last)) {
            $add('orange', 'Sem vendas há alguns dias', 'A última venda foi em ' . fmt_date((string) $last) . '. Vale acionar a carteira de clientes.');
        }

        $order = ['red' => 0, 'orange' => 1, 'blue' => 2, 'green' => 3];
        usort($out, fn ($a, $b) => $order[$a['level']] <=> $order[$b['level']]);
        return $out;
    }

    /** Linha do tempo de movimentacoes da empresa (vendas, despesas, repasses, ajustes, edicoes). @return list<array{ts:string,kind:string,text:string,amount:?float}> */
    public static function movements(int $limit = 12): array
    {
        $rows = [];
        foreach (Database::all("SELECT s.id, s.total, s.created_at, CONCAT_WS(' ', c.first_name, c.last_name) cust FROM sales s LEFT JOIN customers c ON c.id = s.customer_id ORDER BY s.id DESC LIMIT $limit") as $r) {
            $rows[] = ['ts' => $r['created_at'], 'kind' => 'venda', 'text' => 'Venda #' . $r['id'] . ' - ' . ($r['cust'] ?: 'sem cliente'), 'amount' => (float) $r['total']];
        }
        foreach (Database::all("SELECT description, amount, kind, paid_at FROM accounts_payable WHERE paid_at IS NOT NULL ORDER BY paid_at DESC LIMIT $limit") as $r) {
            $rows[] = ['ts' => $r['paid_at'], 'kind' => $r['kind'] === 'imposto' ? 'imposto' : ($r['kind'] === 'ativo' ? 'ativo' : 'despesa'), 'text' => ($r['kind'] === 'imposto' ? 'Imposto pago: ' : ($r['kind'] === 'ativo' ? 'Mercadoria paga: ' : 'Despesa paga: ')) . $r['description'], 'amount' => -(float) $r['amount']];
        }
        foreach (Database::all("SELECT c.amount, c.created_at, c.source, c.sale_id, s.name FROM creditor_payments c LEFT JOIN sellers s ON s.id = c.seller_id ORDER BY c.id DESC LIMIT $limit") as $r) {
            $rows[] = ['ts' => $r['created_at'], 'kind' => 'credor', 'text' => 'Repasse ao credor por ' . ($r['name'] ?: 'vendedor') . ($r['sale_id'] ? ' (venda #' . $r['sale_id'] . ')' : ''), 'amount' => (float) $r['amount']];
        }
        foreach (Database::all("SELECT m.quantity, m.created_at, m.reason, p.name FROM stock_movements m JOIN products p ON p.id = m.product_id WHERE m.type = 'adjust' AND m.sale_id IS NULL ORDER BY m.id DESC LIMIT $limit") as $r) {
            $rows[] = ['ts' => $r['created_at'], 'kind' => 'estoque', 'text' => 'Ajuste de estoque: ' . $r['name'] . ' (' . ((int) $r['quantity'] > 0 ? '+' : '') . (int) $r['quantity'] . ')', 'amount' => null];
        }
        foreach (Database::all("SELECT sale_id, username, summary, created_at FROM sale_audit ORDER BY id DESC LIMIT $limit") as $r) {
            $rows[] = ['ts' => $r['created_at'], 'kind' => 'edicao', 'text' => 'Venda #' . $r['sale_id'] . ' editada por ' . ($r['username'] ?: 'admin'), 'amount' => null];
        }
        usort($rows, fn ($a, $b) => strcmp((string) $b['ts'], (string) $a['ts']));
        return array_slice($rows, 0, $limit);
    }
}
