<?php
declare(strict_types=1);

namespace App;

use DomainException;

/** Orcamentos personalizados da loja: gravacao, calculo, PDF e link de WhatsApp. */
final class Quote
{
    public const CATEGORIES = ['pneu' => 'Pneus', 'roda' => 'Roda', 'parafuso' => 'Parafusos', 'servico' => 'Serviços'];

    /**
     * @param array<string,mixed> $d
     * @return int id do orcamento
     */
    public static function save(?int $id, array $d, ?string $username): int
    {
        $name = upper((string) ($d['customer_name'] ?? ''));
        if ($name === '') {
            throw new DomainException('Informe o nome do cliente.');
        }
        $email = upper((string) ($d['customer_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('E-mail invalido.');
        }
        $items = [];
        foreach ((array) ($d['items'] ?? []) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $desc = upper((string) ($r['description'] ?? ''));
            if ($desc === '') {
                continue;
            }
            $cat = isset(self::CATEGORIES[$r['category'] ?? '']) ? $r['category'] : 'pneu';
            $qty = max(1, (int) ($r['quantity'] ?? 1));
            $price = round((float) parse_money($r['unit_price'] ?? 0), 2);
            if ($price < 0) {
                throw new DomainException('Preco unitario nao pode ser negativo.');
            }
            $items[] = ['category' => $cat, 'description' => mb_substr($desc, 0, 200), 'quantity' => $qty, 'unit_price' => $price];
        }
        if (!$items) {
            throw new DomainException('Adicione ao menos um item ao orcamento.');
        }
        $subCents = array_sum(array_map(fn ($i) => (int) round($i['unit_price'] * 100) * $i['quantity'], $items));
        $discCents = (int) round(max(0.0, parse_money($d['discount'] ?? 0)) * 100);
        if ($discCents > $subCents) {
            throw new DomainException('O desconto nao pode ser maior que o total.');
        }
        $valid = (string) ($d['valid_until'] ?? '');
        $valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $valid) ? $valid : date('Y-m-d', strtotime('+' . (int) Settings::all()['quote_validity_days'] . ' days'));

        $payMethod = isset(self::PAY_METHODS[$d['pay_method'] ?? '']) ? (string) $d['pay_method'] : null;
        $installments = $payMethod === 'credito' ? max(1, min(12, (int) ($d['installments'] ?? 1))) : 1;
        $fields = [
            $name,
            self::nullable(format_phone((string) ($d['customer_phone'] ?? ''))),
            self::nullable($email),
            self::nullable(digits((string) ($d['customer_tax_id'] ?? ''))),
            self::nullable(upper((string) ($d['customer_car'] ?? ''))),
            ((int) ($d['seller_id'] ?? 0)) ?: null,
            $discCents / 100, ($subCents - $discCents) / 100, $valid,
            self::nullable(mb_substr(upper((string) ($d['payment_terms'] ?? '')), 0, 255)),
            self::nullable(upper((string) ($d['notes'] ?? ''))),
            $payMethod, $installments,
        ];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($id) {
                if (!Database::value('SELECT id FROM quotes WHERE id = ?', [$id])) {
                    throw new DomainException('Orçamento nao encontrado.');
                }
                Database::run(
                    'UPDATE quotes SET customer_name=?, customer_phone=?, customer_email=?, customer_tax_id=?, customer_car=?, seller_id=?, discount=?, total=?, valid_until=?, payment_terms=?, notes=?, pay_method=?, installments=? WHERE id=?',
                    array_merge($fields, [$id])
                );
                Database::run('DELETE FROM quote_items WHERE quote_id = ?', [$id]);
            } else {
                $id = Database::insert(
                    'INSERT INTO quotes (customer_name, customer_phone, customer_email, customer_tax_id, customer_car, seller_id, discount, total, valid_until, payment_terms, notes, pay_method, installments, token, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    array_merge($fields, [bin2hex(random_bytes(20)), $username])
                );
            }
            foreach ($items as $i) {
                Database::insert(
                    'INSERT INTO quote_items (quote_id, category, description, quantity, unit_price) VALUES (?,?,?,?,?)',
                    [$id, $i['category'], $i['description'], $i['quantity'], $i['unit_price']]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return $id;
    }

    private static function nullable(string $v): ?string
    {
        return $v === '' ? null : $v;
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return self::load('id = ?', $id);
    }

    /** @return array<string,mixed>|null */
    public static function findByToken(string $token): ?array
    {
        return preg_match('/^[0-9a-f]{40}$/', $token) ? self::load('token = ?', $token) : null;
    }

    /** @return array<string,mixed>|null */
    private static function load(string $where, int|string $arg): ?array
    {
        $q = Database::one("SELECT q.*, s.name seller FROM quotes q LEFT JOIN sellers s ON s.id = q.seller_id WHERE q.$where", [$arg]);
        if (!$q) {
            return null;
        }
        $q['items'] = Database::all('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id', [$q['id']]);
        $q['subtotal'] = array_sum(array_map(fn ($i) => round((float) $i['unit_price'] * (int) $i['quantity'], 2), $q['items']));
        return $q;
    }

    public const PAY_METHODS = ['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'debito' => 'Cartão de débito', 'credito' => 'Cartão de crédito'];

    /**
     * Parcelas do cartao: divide o total em N vezes (centavos que sobram vao na ultima parcela).
     * @return list<array{n:int,value:float}> grupos "n parcelas de value"
     */
    public static function installmentPlan(float $total, int $n): array
    {
        $n = max(1, $n);
        $cents = (int) round($total * 100);
        $base = intdiv($cents, $n);
        $rest = $cents - $base * $n;
        if ($rest === 0) {
            return [['n' => $n, 'value' => (float) $base / 100]];
        }
        return $n === 1 ? [['n' => 1, 'value' => $cents / 100.0]] : [['n' => $n - 1, 'value' => $base / 100.0], ['n' => 1, 'value' => ($base + $rest) / 100.0]];
    }

    /** Linha da forma de pagamento: "Cartão de crédito: 3 parcelas de R$ 300,00 (total R$ 900,00)". */
    public static function paymentLine(array $q): string
    {
        $m = (string) ($q['pay_method'] ?? '');
        $out = '';
        if ($m === 'credito') {
            $n = max(1, (int) ($q['installments'] ?? 1));
            if ($n === 1) {
                $out = 'Cartão de crédito à vista: ' . money($q['total']);
            } else {
                $parts = array_map(fn ($g) => $g['n'] . ($g['n'] === 1 ? ' parcela de ' : ' parcelas de ') . money($g['value']), self::installmentPlan((float) $q['total'], $n));
                $out = 'Cartão de crédito em ' . $n . 'x: ' . implode(' + ', $parts) . ' (total ' . money($q['total']) . ')';
            }
        } elseif (isset(self::PAY_METHODS[$m])) {
            $out = self::PAY_METHODS[$m] . ': ' . money($q['total']);
        }
        $extra = trim((string) ($q['payment_terms'] ?? ''));
        return trim($out . ($out !== '' && $extra !== '' ? ' · ' : '') . $extra);
    }

    public static function number(array $q): string
    {
        return str_pad((string) $q['id'], 4, '0', STR_PAD_LEFT);
    }

    public static function pdfUrl(array $q): string
    {
        $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost');
        return (is_https() ? 'https' : 'http') . '://' . $host . '/' . url('orcamento', ['pdf' => $q['token']]);
    }

    /** Texto que acompanha o PDF no WhatsApp (sem link do sistema: o PDF segue como anexo). */
    public static function whatsappText(array $q): string
    {
        $store = Settings::all()['store_name'];
        $pay = self::paymentLine($q);
        return sprintf(
            "Olá, %s! Segue o seu orçamento nº %s da %s no valor de %s.%s Válido até %s. Qualquer dúvida é só chamar!",
            $q['customer_name'], self::number($q), $store, money($q['total']), $pay !== '' ? ' Pagamento: ' . $pay . '.' : '', fmt_date($q['valid_until'])
        );
    }

    public static function whatsappLink(array $q): string
    {
        $d = digits($q['customer_phone'] ?? '');
        if ($d !== '' && strlen($d) <= 11) {
            $d = '55' . $d;
        }
        return 'https://wa.me/' . $d . '?text=' . rawurlencode(self::whatsappText($q));
    }

    /** Envia o PDF (publico, protegido pelo token aleatorio do orcamento) e encerra. */
    public static function servePdf(string $token, bool $download = false): never
    {
        $q = self::findByToken($token);
        if (!$q) {
            http_response_code(404);
            exit('Orçamento nao encontrado.');
        }
        $bin = self::pdf($q);
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="ORCAMENTO-' . self::number($q) . '.pdf"');
        header('Content-Length: ' . strlen($bin));
        header('Cache-Control: private, max-age=0');
        echo $bin;
        exit;
    }

    /** @param array<string,mixed> $q */
    public static function pdf(array $q): string
    {
        $st = Settings::all();
        $ink = [5, 5, 5];
        $green = [255, 210, 26]; // amarelo da marca
        $onAccent = [10, 10, 10];
        $muted = [107, 106, 100];
        $L = 40.0;
        $R = Pdf::W - 40.0;
        $pdf = new Pdf();
        $pdf->addPage();

        // Cabecalho: logo da loja sobre fundo preto + faixa amarela
        $pdf->rect(0, 0, Pdf::W, 112, $ink);
        $pdf->rect(0, 112, Pdf::W, 5, $green);
        $logo = dirname(__DIR__) . '/public/assets/logo.jpg';
        if ($pdf->image($logo, $L - 6, 6, 170) <= 0) {
            $pdf->text($L, 56, $st['store_name'], 24, true, [255, 255, 255]);
        }
        $pdf->text($R, 44, 'ORÇAMENTO', 11, true, $green, 'R');
        $pdf->text($R, 68, 'Nº ' . self::number($q), 24, true, [255, 255, 255], 'R');
        $pdf->text($R, 86, 'Emitido em ' . fmt_date($q['created_at']), 9, false, [203, 203, 197], 'R');
        $info = trim(implode('   |   ', array_filter([$st['store_name'], $st['store_doc'] !== '' ? 'CNPJ/CPF ' . $st['store_doc'] : '', $st['store_phone'], $st['store_address']])));
        $pdf->text($L, 134, $info, 8.5, false, $muted);

        // Cliente
        $y = 148.0;
        $pdf->rect($L, $y, $R - $L, 78, [244, 243, 238]);
        $pdf->rect($L, $y, 4, 78, $green);
        $pdf->text($L + 16, $y + 20, 'CLIENTE', 8.5, true, $muted);
        $pdf->text($L + 16, $y + 38, $q['customer_name'], 13, true, $ink);
        $cols = [
            ['TELEFONE', $q['customer_phone'] ?: '-'], ['CPF/CNPJ', self::fmtDoc($q['customer_tax_id'])],
            ['CARRO', $q['customer_car'] ?: '-'], ['E-MAIL', $q['customer_email'] ?: '-'],
        ];
        $cx = $L + 16;
        $cw = ($R - $L - 32) / 4;
        foreach ($cols as $c) {
            $pdf->text($cx, $y + 56, $c[0], 7.5, true, $muted);
            $line = $pdf->wrap((string) $c[1], 9, $cw - 8)[0];
            $pdf->text($cx, $y + 68, $line, 9, false, $ink);
            $cx += $cw;
        }

        // Tabela
        $y = 248.0;
        $xCat = $L + 8;
        $xDesc = $L + 78;
        $xQty = $R - 190;
        $xUnit = $R - 100;
        $xSub = $R - 8;
        $header = function (float $y) use ($pdf, $L, $R, $ink, $xCat, $xDesc, $xQty, $xUnit, $xSub): void {
            $pdf->rect($L, $y, $R - $L, 22, $ink);
            $pdf->text($xCat, $y + 15, 'CATEGORIA', 8.5, true, [255, 210, 26]);
            $pdf->text($xDesc, $y + 15, 'DESCRIÇÃO', 8.5, true, [255, 210, 26]);
            $pdf->text($xQty, $y + 15, 'QTD.', 8.5, true, [255, 210, 26], 'R');
            $pdf->text($xUnit, $y + 15, 'UNITÁRIO', 8.5, true, [255, 210, 26], 'R');
            $pdf->text($xSub, $y + 15, 'SUBTOTAL', 8.5, true, [255, 210, 26], 'R');
        };
        $header($y);
        $y += 22;
        $zebra = false;
        foreach ($q['items'] as $it) {
            $lines = $pdf->wrap((string) $it['description'], 9.5, $xQty - 40 - $xDesc);
            $h = max(24.0, count($lines) * 12.5 + 11);
            if ($y + $h > Pdf::H - 70) {
                $pdf->addPage();
                $y = 40.0;
                $header($y);
                $y += 22;
            }
            if ($zebra) {
                $pdf->rect($L, $y, $R - $L, $h, [249, 248, 244]);
            }
            $zebra = !$zebra;
            $pdf->text($xCat, $y + 15, self::CATEGORIES[$it['category']] ?? $it['category'], 8.5, true, $muted);
            foreach ($lines as $k => $ln) {
                $pdf->text($xDesc, $y + 15 + $k * 12.5, $ln, 9.5, false, $ink);
            }
            $pdf->text($xQty, $y + 15, (string) (int) $it['quantity'], 9.5, false, $ink, 'R');
            $pdf->text($xUnit, $y + 15, money($it['unit_price']), 9.5, false, $ink, 'R');
            $pdf->text($xSub, $y + 15, money($it['unit_price'] * $it['quantity']), 9.5, true, $ink, 'R');
            $pdf->line($L, $y + $h, $R, $y + $h, [231, 229, 223]);
            $y += $h;
        }

        // Totais
        if ($y + 120 > Pdf::H - 70) {
            $pdf->addPage();
            $y = 40.0;
        }
        $y += 16;
        $bx = $R - 230;
        $pdf->text($bx, $y + 8, 'Subtotal', 10, false, $muted);
        $pdf->text($R - 8, $y + 8, money($q['subtotal']), 10, false, $ink, 'R');
        if ((float) $q['discount'] > 0) {
            $y += 18;
            $pdf->text($bx, $y + 8, 'Desconto', 10, false, $muted);
            $pdf->text($R - 8, $y + 8, '- ' . money($q['discount']), 10, false, [190, 18, 60], 'R');
        }
        $y += 16;
        $pdf->rect($bx - 10, $y, 240, 34, $green);
        $pdf->text($bx, $y + 22, 'TOTAL', 11, true, $onAccent);
        $pdf->text($R - 8, $y + 23, money($q['total']), 15, true, $onAccent, 'R');
        $y += 52;

        // Condicoes
        $blocks = [];
        $blocks[] = ['VALIDADE', 'Este orçamento é válido até ' . fmt_date($q['valid_until']) . '.'];
        if (self::paymentLine($q) !== '') {
            $blocks[] = ['FORMA DE PAGAMENTO', self::paymentLine($q)];
        }
        if ($q['notes']) {
            $blocks[] = ['OBSERVAÇÕES', $q['notes']];
        }
        if ($q['seller']) {
            $blocks[] = ['VENDEDOR', $q['seller']];
        }
        foreach ($blocks as [$title, $body]) {
            $ls = $pdf->wrap((string) $body, 9.5, $R - $L);
            if ($y + 16 + count($ls) * 12.5 > Pdf::H - 60) {
                $pdf->addPage();
                $y = 40.0;
            }
            $pdf->text($L, $y, $title, 8.5, true, $muted);
            $y += 13;
            foreach ($ls as $ln) {
                $pdf->text($L, $y, $ln, 9.5, false, $ink);
                $y += 12.5;
            }
            $y += 8;
        }

        // Rodape
        $pdf->line($L, Pdf::H - 52, $R, Pdf::H - 52, [203, 213, 225]);
        foreach (array_slice($pdf->wrap($st['quote_terms'], 8, $R - $L), 0, 2) as $k => $ln) {
            $pdf->text(Pdf::W / 2, Pdf::H - 38 + $k * 11, $ln, 8, false, $muted, 'C');
        }
        return $pdf->output();
    }

    private static function fmtDoc(?string $d): string
    {
        $d = digits($d);
        if (strlen($d) === 11) {
            return sprintf('%s.%s.%s-%s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 3), substr($d, 9));
        }
        if (strlen($d) === 14) {
            return sprintf('%s.%s.%s/%s-%s', substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 3), substr($d, 8, 4), substr($d, 12));
        }
        return $d !== '' ? $d : '-';
    }
}
