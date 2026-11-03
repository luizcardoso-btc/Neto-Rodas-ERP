<?php
declare(strict_types=1);

namespace App;

/** Busca rapida (JSON) de produtos e clientes para o PDV. */
final class Lookup
{
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
        $where = "active = 1 AND category = 'pneu' AND COALESCE(width,'') <> '' AND COALESCE(profile,'') <> '' AND COALESCE(rim,'') <> ''";
        if (preg_match('/^\s*r?\s*(\d{2})\s*$/i', $q, $m)) {
            $where .= ' AND rim = ?';
            $args = [$m[1]];
        } else {
            $d = digits($q);
            if (strlen($d) < 3) {
                return [];
            }
            $where .= ' AND CONCAT(width, profile, rim) LIKE ?';
            $args = ['%' . $d . '%'];
        }
        $rows = Database::all(
            "SELECT id, price, stock, condition_type, location, width, profile, rim FROM products
             WHERE $where ORDER BY (stock > 0) DESC, width, profile, rim, condition_type LIMIT 15",
            $args
        );
        foreach ($rows as &$r) {
            $r['label'] = $r['width'] . '/' . $r['profile'] . ' R' . $r['rim'];
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
            "SELECT id, name, price, stock, condition_type, location FROM products
             WHERE $where ORDER BY (condition_type = 'novo') DESC, (stock > 0) DESC, name LIMIT 30",
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
        $listAll = $kind === 'product' && in_array((string) ($_GET['cat'] ?? ''), ['roda', 'parafuso'], true); // lista completa sem digitar
        if (mb_strlen($q) < 2 && !$listAll) {
            echo '[]';
            exit;
        }
        $like = '%' . addcslashes($q, '%_\\') . '%';
        if ($kind === 'product') {
            $rows = self::products($q, (string) ($_GET['cat'] ?? 'pneu'));
        } elseif ($kind === 'customer') {
            $d = digits($q);
            $rows = Database::all(
                "SELECT id, first_name, last_name, email, phone, tax_id, car, street, number, district, city, state, zip, country
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
