<?php
declare(strict_types=1);

namespace App;

/** Busca rapida (JSON) de produtos e clientes para o PDV. */
final class Lookup
{
    /** 'varejo' (padrao) esconde o estoque ATACADO; 'atacado' lista o atacado primeiro e tambem o restante. */
    public static string $mode = 'varejo';

    private static function condSql(): string
    {
        return self::$mode === 'atacado' ? '' : " AND condition_type <> 'atacado'";
    }

    private static function condOrder(): string
    {
        return self::$mode === 'atacado' ? "(condition_type = 'atacado') DESC, " : '';
    }

    /**
     * Busca de pneus SOMENTE por medida. Aceita "175/65 R14", "175 65 14", "1756514",
     * so a largura ("175") ou so o aro ("R15" / "15"). Texto livre (marca, nome) nao retorna nada.
     *
     * @return list<array<string,mixed>>
     */
    public static function products(string $q, string $category = 'pneu'): array
    {
        if ($category === 'roda') {
            return self::wheels($q);
        }
        if ($category === 'parafuso') {
            return self::bolts($q);
        }
        $hasMeasure = "COALESCE(width,'') <> '' AND COALESCE(profile,'') <> '' AND COALESCE(rim,'') <> ''";
        // No atacado, pneus cadastrados so pelo nome (sem medida) tambem aparecem.
        $where = "active = 1 AND category = 'pneu'" . (self::$mode === 'atacado' ? " AND ($hasMeasure OR condition_type = 'atacado')" : " AND $hasMeasure");
        // Palavras (marca/modelo) podem vir junto da medida: "175/65 R14 dunlop" ou so "dunlop".
        preg_match_all('/[\p{L}][\p{L}\p{N}\-]+/u', $q, $w);
        $condWord = null;
        foreach ($w[0] as $x) {
            $lx = mb_strtolower($x);
            if (in_array($lx, ['atacado', 'usado', 'usados', 'novo', 'novos'], true)) {
                $condWord = rtrim($lx, 's');
            }
        }
        if ($condWord === null && self::$mode === 'atacado' && trim($q) === '') {
            $condWord = 'atacado'; // aba Atacado sem digitar nada: lista o estoque de atacado
        }
        if ($condWord !== null) {
            $where .= ' AND condition_type = ' . Database::pdo()->quote($condWord);
        }
        $words = array_values(array_filter($w[0], fn ($x) => mb_strlen($x) >= 2 && !in_array(mb_strtolower($x), ['pneu', 'pneus', 'usado', 'usados', 'novo', 'novos', 'atacado'], true)));
        $measure = trim(preg_replace('/[\p{L}][\p{L}\p{N}\-]+/u', ' ', $q) ?? '');
        if (preg_match('/\br\s*(\d{2})\b/i', $q, $rm)) {
            $measure = trim($measure . ' R' . $rm[1]);
        }
        $args = [];
        if (preg_match('/^\s*r?\s*(\d{2})\s*$/i', $q, $m)) {
            $where .= ' AND rim = ?';
            $args = [$m[1]];
        } else {
            $d = digits($measure);
            if (strlen($d) >= 3) {
                $where .= ' AND CONCAT(width, profile, rim) LIKE ?';
                $args = ['%' . $d . '%'];
            } elseif (!$words && $condWord === null && !(self::$mode === 'atacado' && trim($q) === '')) {
                return [];
            }
        }
        foreach ($words as $word) {
            if (preg_match('/^r\d{2}$/i', $word)) {
                continue;
            }
            $where .= " AND CONCAT_WS(' ', brand, model, name, description) LIKE ?";
            $args[] = '%' . addcslashes($word, '%_\\') . '%';
        }
        $rows = Database::all(
            "SELECT id, name, brand, model, description, price, wholesale_price, stock, condition_type, location, width, profile, rim FROM products
             WHERE $where" . self::condSql() . " ORDER BY " . self::condOrder() . "(stock > 0) DESC, width, profile, rim, brand, condition_type LIMIT 40",
            $args
        );
        foreach ($rows as &$r) {
            $r['label'] = $r['width'] !== null && $r['width'] !== '' ? $r['width'] . '/' . $r['profile'] . ' R' . $r['rim'] : upper((string) $r['name']);
            $r['brand'] = upper(trim((string) ($r['brand'] ?? '')));
            $r['model'] = upper(trim((string) ($r['model'] ?? '')));
            $r['stock'] = (int) $r['stock'];
        }
        return $rows;
    }

    /** Rodas (vendidas por JOGO). Sem texto, lista todos: novos primeiro, com estoque primeiro. Busca por marca, aro ou acabamento. @return list<array<string,mixed>> */
    public static function wheels(string $q): array
    {
        $q = trim($q);
        $where = "active = 1 AND category = 'roda'";
        $args = [];
        if (in_array(mb_strtolower(rtrim($q, 'sS')), ['atacado', 'usado', 'novo', 'usada', 'nova'], true)) {
            $c = ['usada' => 'usado', 'nova' => 'novo'][mb_strtolower(rtrim($q, 'sS'))] ?? mb_strtolower(rtrim($q, 'sS'));
            return self::catalog($where . ' AND condition_type = ?', [$c]);
        }
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $rim = preg_match('/^\s*(?:aro\s*|r\s*)?(\d{2})\s*$/i', $q, $m) ? $m[1] : '';
            $where .= " AND (CONCAT_WS(' ', name, brand, model, mpn, description) LIKE ?" . ($rim !== '' ? ' OR rim = ?' : '') . ')';
            $args = $rim !== '' ? [$like, $rim] : [$like];
        }
        return self::catalog($where, $args);
    }

    /** Jogo de parafusos (16 unidades). Sem texto, lista todos. @return list<array<string,mixed>> */
    public static function bolts(string $q): array
    {
        $q = trim($q);
        $where = "active = 1 AND category = 'parafuso'";
        $args = [];
        if (in_array(mb_strtolower($q), ['atacado', 'usado', 'novo'], true)) {
            return self::catalog($where . ' AND condition_type = ?', [mb_strtolower($q)]);
        }
        if ($q !== '') {
            $where .= " AND CONCAT_WS(' ', name, brand, model, description) LIKE ?";
            $args = ['%' . addcslashes($q, '%_\\') . '%'];
        }
        return self::catalog($where, $args);
    }

    /** @return list<array<string,mixed>> */
    private static function catalog(string $where, array $args): array
    {
        $rows = Database::all(
            "SELECT id, name, description, price, wholesale_price, stock, condition_type, location FROM products
             WHERE $where" . self::condSql() . " ORDER BY " . self::condOrder() . "(condition_type = 'novo') DESC, (stock > 0) DESC, name LIMIT 30",
            $args
        );
        foreach ($rows as &$r) {
            $r['label'] = $r['name'];
            $r['stock'] = (int) $r['stock'];
        }
        return $rows;
    }

    public static function respond(): void
    {
        $kind = (string) ($_GET['lookup'] ?? '');
        $q = trim((string) ($_GET['q'] ?? ''));
        header('Content-Type: application/json; charset=utf-8');
        $listAll = $kind === 'product' && (in_array((string) ($_GET['cat'] ?? ''), ['roda', 'parafuso'], true) || ($_GET['mode'] ?? '') === 'atacado'); // lista completa sem digitar
        if (mb_strlen($q) < 2 && !$listAll) {
            echo '[]';
            exit;
        }
        $like = '%' . addcslashes($q, '%_\\') . '%';
        self::$mode = ($_GET['mode'] ?? '') === 'atacado' ? 'atacado' : 'varejo';
        if ($kind === 'product') {
            $rows = self::products($q, (string) ($_GET['cat'] ?? 'pneu'));
        } elseif ($kind === 'customer') {
            $d = digits($q);
            $rows = Database::all(
                "SELECT id, first_name, last_name, email, phone, tax_id, car, street, number, district, city, state, zip, country, birth_date
                 FROM customers
                 WHERE CONCAT_WS(' ', first_name, last_name) LIKE ? OR car LIKE ? OR email LIKE ?
                    " . ($d !== '' ? 'OR REPLACE(REPLACE(REPLACE(phone,\' \',\'\'),\'-\',\'\'),\'(\',\'\') LIKE ? OR tax_id LIKE ?' : '') . '
                 ORDER BY first_name LIMIT 8',
                $d !== '' ? [$like, $like, $like, '%' . $d . '%', '%' . $d . '%'] : [$like, $like, $like]
            );
        } else {
            $rows = [];
        }
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
