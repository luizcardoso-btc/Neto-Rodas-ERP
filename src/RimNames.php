<?php
declare(strict_types=1);

namespace App;

use DomainException;

/** Liga as rodas do estoque as rodas do catalogo do provador e padroniza o nome do produto. */
final class RimNames
{
    private const FINISH = ['PRATA', 'PRETA', 'PRETO', 'GRAFITE', 'DIAMANTADA', 'DIAMANTE', 'FUME', 'COMUM', 'BRANCA'];
    private const STOP = ['RODA', 'RODAS', 'ARO', 'JOGO', 'DE', 'DO', 'DA', 'COM', 'EMBLEMA', 'VW', 'VOLKSWAGEN', 'FIAT', 'CHEVROLET', 'GM', 'X', '-'];

    /** Nome no estoque: "JOGO DE RODA <nome do catalogo sem RODA> R<aro>" (+ USADA/ATACADO). */
    public static function stockName(string $catalogName, int $rim, string $cond = 'novo'): string
    {
        $base = trim((string) preg_replace('/^RODAS?\s+/i', '', upper($catalogName)));
        $base = trim((string) preg_replace('/\s*\bR?\d{2}\b\s*$/', '', $base));
        $tag = $cond === 'usado' ? ' USADA' : ($cond === 'atacado' ? ' ATACADO' : '');
        return 'JOGO DE RODA' . $tag . ' ' . $base . ($rim ? ' R' . $rim : '');
    }

    /** @return string[] */
    private static function tokens(string $s): array
    {
        $u = (string) preg_replace('/\b([A-Z]{2,})\s+(\d{2})\b(?!\s*\d)/', '$1$2', upper($s)); // HB 20 -> HB20
        $u = str_replace(['DIAMANTADA', 'PRETO'], ['DIAMANTE', 'PRETA'], $u);
        $t = preg_split('/[^A-Z0-9]+/', $u) ?: [];
        return array_values(array_filter($t, fn ($w) => $w !== '' && !in_array($w, self::STOP, true) && !preg_match('/^R?1\d$|^2\d$/', $w)));
    }

    /** Roda do catalogo mais parecida com o produto (ou null se nada bate). */
    public static function suggest(array $product, array $catalog): ?array
    {
        $pt = self::tokens((string) $product['name'] . ' ' . (string) ($product['model'] ?? ''));
        $best = null;
        $bestScore = 0.0;
        foreach ($catalog as $c) {
            $ct = self::tokens((string) $c['name']);
            if (!$ct) {
                continue;
            }
            $model = array_diff($ct, self::FINISH);
            $hit = count(array_intersect($model, $pt));
            if (!$hit) { // so cor/acabamento igual nao basta: precisa bater o modelo (ARGO, ONIX, TARANTULA...)
                continue;
            }
            $fin = count(array_intersect(array_intersect($ct, self::FINISH), $pt));
            $score = $hit / max(1, count($model)) + 0.15 * $fin + ((int) $c['rim'] === (int) digits((string) ($product['rim'] ?? '')) ? 0.3 : 0);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $c;
            }
        }
        return $bestScore >= 0.5 ? $best : null;
    }

    /**
     * Aplica os vinculos: [product_id => catalog_id|0]. Muda so o nome (e o vinculo); preco e estoque ficam iguais.
     * @param array<int|string,int|string> $map
     */
    public static function apply(array $map): int
    {
        $n = 0;
        foreach ($map as $pid => $cid) {
            $p = Database::one("SELECT id, rim, condition_type, rim_catalog_id FROM products WHERE id = ? AND category = 'roda'", [(int) $pid]);
            if (!$p) {
                continue;
            }
            $cid = (int) $cid;
            if ($cid === 0) {
                if ($p['rim_catalog_id'] !== null) {
                    Database::run('UPDATE products SET rim_catalog_id = NULL WHERE id = ?', [$p['id']]);
                    $n++;
                }
                continue;
            }
            $c = Database::one('SELECT id, name, rim FROM rim_catalog WHERE id = ?', [$cid]);
            if (!$c) {
                throw new DomainException('Roda do catalogo nao encontrada.');
            }
            $rim = (int) digits((string) $p['rim']) ?: (int) $c['rim'];
            $name = self::stockName($c['name'], $rim, (string) $p['condition_type']);
            Database::run('UPDATE products SET name = ?, rim_catalog_id = ? WHERE id = ?', [$name, $cid, $p['id']]);
            $n++;
        }
        return $n;
    }
}
