<?php
declare(strict_types=1);

namespace App;

/** Converte um orcamento aprovado nos dados iniciais do PDV (mesmo formato do formulario de venda). */
final class QuoteToSale
{
    /** @return array<string,mixed>|null */
    public static function prefill(int $quoteId): ?array
    {
        $q = Quote::find($quoteId);
        if (!$q || $q['sale_id']) {
            return null;
        }
        $parts = preg_split('/\s+/', trim((string) $q['customer_name']), 2) ?: [''];
        $cust = ['first_name' => $parts[0], 'last_name' => $parts[1] ?? '', 'email' => (string) $q['customer_email'],
            'phone' => (string) $q['customer_phone'], 'tax_id' => (string) $q['customer_tax_id'], 'car' => (string) $q['customer_car']];
        $tax = digits($cust['tax_id']);
        $phone = digits($cust['phone']);
        $row = null;
        if ($tax !== '') {
            $row = Database::one('SELECT * FROM customers WHERE tax_id = ? LIMIT 1', [$tax]);
        }
        if (!$row && $phone !== '') {
            $row = Database::one('SELECT * FROM customers WHERE phone = ? OR phone = ? LIMIT 1', [$phone, format_phone($phone)]);
        }
        $cid = null;
        if ($row) {
            $cid = (int) $row['id'];
            foreach (['first_name', 'last_name', 'email', 'phone', 'tax_id', 'car', 'street', 'number', 'district', 'city', 'state', 'zip'] as $f) {
                if (trim((string) ($row[$f] ?? '')) !== '') {
                    $cust[$f] = (string) $row[$f];
                }
            }
        }
        $cust['customer_id'] = $cid;

        $items = [];
        $unmatched = false;
        foreach ($q['items'] as $i) {
            $pid = null;
            if ($i['category'] !== 'servico') {
                $pid = self::match($i['category'], (string) $i['description']);
                $unmatched = $unmatched || !$pid;
            }
            $items[] = ['category' => $i['category'], 'name' => $i['description'], 'product_id' => $pid,
                'quantity' => $i['quantity'], 'unit_price' => number_format((float) $i['unit_price'], 2, ',', '')];
        }
        // desconto do orcamento vira um ajuste no preco? Mantem simples: desconto entra como linha de servico negativa nao e permitido,
        // entao distribui-se o desconto no ultimo item.
        $disc = (int) round((float) $q['discount'] * 100);
        if ($disc > 0 && $items) {
            $k = array_key_last($items);
            $qty = max(1, (int) $items[$k]['quantity']);
            $line = (int) round(parse_money($items[$k]['unit_price']) * 100) * $qty - $disc;
            $items[$k]['unit_price'] = number_format(max(0, $line) / 100 / $qty, 2, ',', '');
        }

        $method = (string) ($q['pay_method'] ?? '');
        $pay = $method === 'pix' ? 'pix' : ($method === 'dinheiro' ? 'dinheiro' : (in_array($method, ['debito', 'credito'], true) ? 'cartao' : 'pix'));
        $note = 'ORCAMENTO #' . Quote::number($q) . (Quote::paymentLine($q) !== '' ? ' - ' . upper(Quote::paymentLine($q)) : '');
        return [
            'quote_id' => $quoteId, 'customer' => $cust, 'seller_id' => $q['seller_id'], 'notes' => mb_substr($note, 0, 255),
            'items' => $items, 'payments' => [['method' => $pay, 'amount' => number_format((float) $q['total'], 2, ',', '')]],
            '_unmatched' => $unmatched,
        ];
    }

    /** Acha o produto do estoque pela descricao do orcamento (medida do pneu ou nome da roda), so se for inequivoco. */
    private static function match(string $category, string $desc): ?int
    {
        if ($category === 'pneu') {
            if (!preg_match('/(\d{3})\s*[\/ ]\s*(\d{2})\s*[-R ]*\s*(\d{2})/i', $desc, $m)) {
                return null;
            }
            $rows = Database::all("SELECT id, condition_type FROM products WHERE active = 1 AND category = 'pneu' AND width = ? AND profile = ? AND rim = ? ORDER BY (condition_type = ?) DESC, stock DESC", [$m[1], $m[2], $m[3], stripos($desc, 'USADO') !== false ? 'usado' : 'novo']);
            return $rows ? (int) $rows[0]['id'] : null;
        }
        $clean = trim(preg_replace('/\(USADO\)/i', '', $desc) ?? '');
        $rows = Database::all("SELECT id FROM products WHERE active = 1 AND category = 'roda' AND name = ?", [$clean]);
        return count($rows) === 1 ? (int) $rows[0]['id'] : null;
    }
}
