<?php
declare(strict_types=1);

namespace App;

/** Reativacao de clientes, ofertas por WhatsApp e importacao de clientes (CSV). */
final class Marketing
{
    public const DEFAULT_REACTIVATE = 'Olá {nome}! Aqui é da Neto Pneus e Rodas. Faz um tempo que você não aparece por aqui{carro_txt}. Criamos um grupo no WhatsApp só com ofertas de pneus e rodas, entra lá: {link}';

    public const DEFAULT_BIRTHDAY = 'Feliz aniversário, {nome}! 🎉 A equipe da Neto Pneus e Rodas deseja um dia incrível e muitas conquistas. Como presente, você tem uma condição especial em pneus e rodas este mês: é só chamar aqui. Abraço!';

    /** Aceita AAAA-MM-DD, DD/MM/AAAA ou DD/MM/AA. Devolve AAAA-MM-DD ou null. */
    public static function parseBirth(mixed $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})$#', $v, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($y < 100) {
                $y += $y > (int) date('y') ? 1900 : 2000;
            }
        } else {
            return null;
        }
        if (!checkdate($mo, $d, $y) || $y < 1900 || sprintf('%04d-%02d-%02d', $y, $mo, $d) > date('Y-m-d')) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    /**
     * Aniversariantes de hoje ate $days dias para frente (ordenados pela proxima data).
     * @return list<array<string,mixed>>
     */
    public static function birthdays(int $days = 7): array
    {
        $rows = Database::all(
            "SELECT c.id, c.first_name, c.last_name, c.phone, c.car, c.birth_date, c.birthday_sent_year, c.opt_out,
                    MAX(s.created_at) last_purchase
             FROM customers c LEFT JOIN sales s ON s.customer_id = c.id
             WHERE c.birth_date IS NOT NULL GROUP BY c.id"
        );
        $today = new \DateTimeImmutable('today');
        $out = [];
        foreach ($rows as $r) {
            [$y, $m, $d] = array_map('intval', explode('-', $r['birth_date']));
            if ($m === 2 && $d === 29 && !checkdate(2, 29, (int) $today->format('Y'))) {
                $d = 28;
            }
            $next = $today->setDate((int) $today->format('Y'), $m, $d);
            if ($next < $today) {
                $next = $next->modify('+1 year');
            }
            $in = (int) $today->diff($next)->days;
            if ($in <= $days) {
                $r['in_days'] = $in;
                $r['age'] = (int) $next->format('Y') - $y;
                $r['sent'] = (int) $r['birthday_sent_year'] === (int) $next->format('Y');
                $out[] = $r;
            }
        }
        usort($out, fn ($a, $b) => [$a['in_days'], $a['first_name']] <=> [$b['in_days'], $b['first_name']]);
        return $out;
    }

    /** Texto final da mensagem com os dados do cliente. */
    public static function render(string $template, array $c, string $link = '', string $measure = ''): string
    {
        $first = trim((string) ($c['first_name'] ?? ''));
        $nome = $first === '' ? '' : mb_convert_case(mb_strtolower(explode(' ', $first)[0]), MB_CASE_TITLE);
        $car = trim((string) ($c['car'] ?? ''));
        $out = strtr($template, [
            '{nome}' => $nome,
            '{carro}' => $car,
            '{carro_txt}' => $car !== '' ? ' com o seu ' . $car : '',
            '{link}' => $link,
            '{medida}' => $measure,
        ]);
        return trim(preg_replace('/[ \t]+/', ' ', str_replace(' !', '!', $out)) ?? $out);
    }

    public static function waUrl(?string $phone, string $text): string
    {
        $base = whatsapp_link($phone);
        return $base === '' ? '' : $base . '?text=' . rawurlencode($text);
    }

    /**
     * Clientes para reativar: com telefone, que aceitam mensagem, sem comprar ha $days dias (ou nunca compraram).
     * @return list<array<string,mixed>>
     */
    public static function reactivateList(int $days, bool $neverOnly = false, bool $hideRecentContact = true, string $source = '', int $limit = 200): array
    {
        $where = ["c.opt_out = 0", "COALESCE(c.phone,'') <> ''"];
        $args = [];
        if ($hideRecentContact) {
            $where[] = '(c.contacted_at IS NULL OR c.contacted_at < NOW() - INTERVAL 30 DAY)';
        }
        if ($source !== '') {
            $where[] = 'c.source = ?';
            $args[] = $source;
        }
        $having = $neverOnly ? 'last_purchase IS NULL' : '(last_purchase IS NULL OR last_purchase < NOW() - INTERVAL ' . max(1, $days) . ' DAY)';
        return Database::all(
            "SELECT c.id, c.first_name, c.last_name, c.phone, c.car, c.source, c.contacted_at,
                    MAX(s.created_at) last_purchase, COUNT(s.id) purchases, COALESCE(SUM(s.total),0) ltv,
                    (SELECT GROUP_CONCAT(si.description SEPARATOR ', ') FROM sale_items si WHERE si.sale_id =
                        (SELECT s2.id FROM sales s2 WHERE s2.customer_id = c.id ORDER BY s2.created_at DESC LIMIT 1)) last_items
             FROM customers c LEFT JOIN sales s ON s.customer_id = c.id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY c.id HAVING $having
             ORDER BY (last_purchase IS NULL), ltv DESC, last_purchase DESC LIMIT " . max(1, $limit),
            $args
        );
    }

    /**
     * Publico de uma oferta: quem comprou a medida/aro OU tem o carro informado. Sem filtro: todos com telefone.
     * @return list<array<string,mixed>>
     */
    public static function offerTargets(array $offer): array
    {
        $conds = [];
        $args = [(int) $offer['id']];
        $m = trim((string) ($offer['measure'] ?? ''));
        if ($m !== '') {
            $d = digits($m);
            if (strlen($d) <= 2) { // so o aro
                $conds[] = "EXISTS (SELECT 1 FROM sales s JOIN sale_items si ON si.sale_id = s.id LEFT JOIN products p ON p.id = si.product_id
                            WHERE s.customer_id = c.id AND (p.rim = ? OR si.description LIKE ?))";
                array_push($args, $d, '%R' . $d . '%');
            } else {
                $conds[] = "EXISTS (SELECT 1 FROM sales s JOIN sale_items si ON si.sale_id = s.id LEFT JOIN products p ON p.id = si.product_id
                            WHERE s.customer_id = c.id AND (CONCAT(COALESCE(p.width,''), COALESCE(p.profile,''), COALESCE(p.rim,'')) LIKE ?
                            OR REPLACE(REPLACE(REPLACE(si.description,'/',''),' ',''),'R','') LIKE ?))";
                array_push($args, '%' . $d . '%', '%' . $d . '%');
            }
        }
        $car = trim((string) ($offer['car_filter'] ?? ''));
        if ($car !== '') {
            $conds[] = 'c.car LIKE ?';
            $args[] = '%' . addcslashes($car, '%_\\') . '%';
        }
        $filter = $conds ? ' AND (' . implode(' OR ', $conds) . ')' : '';
        return Database::all(
            "SELECT c.id, c.first_name, c.last_name, c.phone, c.car, oc.sent_at
             FROM customers c LEFT JOIN offer_contacts oc ON oc.customer_id = c.id AND oc.offer_id = ?
             WHERE c.opt_out = 0 AND COALESCE(c.phone,'') <> ''$filter
             ORDER BY (oc.sent_at IS NOT NULL), c.first_name LIMIT 1000",
            $args
        );
    }

    /**
     * Importa clientes de um CSV (export do sistema antigo, Google Contatos, planilha).
     * Reconhece as colunas pelo nome. Junta repetidos pelo telefone ou CPF/CNPJ (so completa campos vazios).
     * @return array{inserted:int,updated:int,skipped:int}
     */
    public static function importCsv(string $content, string $source): array
    {
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $lines = preg_split('/\r\n|\n|\r/', trim($content)) ?: [];
        if (count($lines) < 2) {
            throw new \DomainException('Arquivo vazio ou sem linhas de clientes.');
        }
        $delim = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : (substr_count($lines[0], "\t") > substr_count($lines[0], ',') ? "\t" : ',');
        $head = array_map(fn ($h) => self::norm($h), str_getcsv($lines[0], $delim));
        $map = self::mapColumns($head);
        if (!isset($map['first_name']) && !isset($map['phone'])) {
            throw new \DomainException('Nao achei as colunas de NOME ou TELEFONE na primeira linha do arquivo.');
        }
        $source = upper($source) ?: 'IMPORTACAO';
        $res = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        // CSV pode ter quebra de linha dentro de aspas: junta de novo
        $rows = [];
        $buf = '';
        foreach (array_slice($lines, 1) as $ln) {
            $buf = $buf === '' ? $ln : $buf . "\n" . $ln;
            if (substr_count($buf, '"') % 2 === 0) {
                $rows[] = $buf;
                $buf = '';
            }
        }
        foreach ($rows as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line, $delim);
            $d = [];
            foreach ($map as $field => $idx) {
                $d[$field] = trim((string) ($cols[$idx] ?? ''));
            }
            $d['phone'] = format_phone($d['phone'] ?? '');
            $d['tax_id'] = digits($d['tax_id'] ?? '');
            if (($d['first_name'] ?? '') === '' && ($d['last_name'] ?? '') !== '') {
                [$d['first_name'], $d['last_name']] = [$d['last_name'], ''];
            }
            if (($d['first_name'] ?? '') === '' && $d['phone'] === '') {
                $res['skipped']++;
                continue;
            }
            if (($d['first_name'] ?? '') !== '' && ($d['last_name'] ?? '') === '' && str_contains($d['first_name'], ' ')) {
                [$d['first_name'], $d['last_name']] = explode(' ', $d['first_name'], 2);
            }
            $d['first_name'] = ($d['first_name'] ?? '') !== '' ? $d['first_name'] : 'CLIENTE';
            $d['state'] = isset($d['state']) ? strtoupper(substr($d['state'], 0, 2)) : '';
            if (isset($d['birth_date'])) {
                $d['birth_date'] = self::parseBirth($d['birth_date']) ?? '';
            }
            foreach ($d as $k => $v) {
                $d[$k] = $k === 'email' ? mb_strtolower($v) : upper($v);
            }
            if (($d['email'] ?? '') !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
                $d['email'] = '';
            }
            $existing = null;
            $pd = substr(digits($d['phone']), -11);
            if (strlen($pd) >= 10) {
                $existing = Database::one("SELECT * FROM customers WHERE RIGHT(REGEXP_REPLACE(COALESCE(phone,''), '[^0-9]', ''), 11) = ? LIMIT 1", [$pd]);
            }
            if (!$existing && strlen($d['tax_id']) >= 11) {
                $existing = Database::one('SELECT * FROM customers WHERE tax_id = ? LIMIT 1', [$d['tax_id']]);
            }
            if (!$existing && strlen($pd) < 10 && strlen($d['tax_id']) < 11) { // sem telefone e sem documento: junta pelo nome completo
                $existing = Database::one('SELECT * FROM customers WHERE first_name = ? AND last_name = ? LIMIT 1', [$d['first_name'], $d['last_name'] ?? '']);
            }
            if ($existing) {
                $set = [];
                $args = [];
                foreach ($d as $k => $v) {
                    if ($v !== '' && trim((string) ($existing[$k] ?? '')) === '') {
                        $set[] = "$k = ?";
                        $args[] = $v;
                    }
                }
                if ($set) {
                    $args[] = $existing['id'];
                    Database::run('UPDATE customers SET ' . implode(', ', $set) . ' WHERE id = ?', $args);
                    $res['updated']++;
                } else {
                    $res['skipped']++;
                }
                continue;
            }
            $d['source'] = $source;
            $d = array_map(fn ($v) => $v === '' ? null : $v, $d);
            $d['first_name'] ??= 'CLIENTE';
            $d['last_name'] ??= '';
            Database::insert('INSERT INTO customers (' . implode(',', array_keys($d)) . ') VALUES (' . implode(',', array_fill(0, count($d), '?')) . ')', array_values($d));
            $res['inserted']++;
        }
        return $res;
    }

    private static function norm(string $h): string
    {
        $h = mb_strtolower(trim($h, " \t\"'"));
        $h = strtr($h, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c']);
        return preg_replace('/\s+/', ' ', $h) ?? $h;
    }

    /** @param list<string> $head @return array<string,int> */
    private static function mapColumns(array $head): array
    {
        $rules = [
            'first_name' => ['/^(nome|name|cliente|nome completo|razao social|first name|given name|nome do cliente)$/', '/nome|name/'],
            'last_name' => ['/^(sobrenome|last name|family name)$/'],
            'phone' => ['/^(whatsapp|celular|telefone|fone|phone|mobile|phone 1 - value|telefone 1 - valor)$/', '/whats|celular|telefone|fone|phone/'],
            'tax_id' => ['/^(cpf|cnpj|cpf\/cnpj|cpf_cnpj|documento|doc)$/', '/cpf|cnpj/'],
            'car' => ['/^(carro|veiculo|modelo|automovel)$/', '/carro|veiculo/'],
            'email' => ['/^(e-?mail|e-mail 1 - value|e-mail 1 - valor)$/', '/mail/'],
            'street' => ['/^(endereco|logradouro|rua|address|street)$/'],
            'number' => ['/^(numero|n|nº|number)$/'],
            'district' => ['/^(bairro|district)$/'],
            'city' => ['/^(cidade|municipio|city)$/'],
            'state' => ['/^(uf|estado|state)$/'],
            'zip' => ['/^(cep|zip)$/'],
            'notes' => ['/^(obs|observacao|observacoes|notas|notes)$/'],
            'birth_date' => ['/^(nascimento|data de nascimento|data nascimento|dt nascimento|aniversario|birthday)$/', '/nasc|anivers|birthday/'],
        ];
        $map = [];
        $used = [];
        foreach ($rules as $field => $patterns) {
            foreach ($patterns as $p) {
                foreach ($head as $i => $h) {
                    if (!isset($used[$i]) && $h !== '' && preg_match($p, $h) && !($field === 'first_name' && preg_match('/sobrenome|last|family|usuario|vendedor/', $h))) {
                        $map[$field] = $i;
                        $used[$i] = true;
                        continue 3;
                    }
                }
            }
        }
        return $map;
    }
}
