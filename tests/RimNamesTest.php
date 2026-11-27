<?php
declare(strict_types=1);

use App\Database;
use App\RimNames;
use PHPUnit\Framework\TestCase;

final class RimNamesTest extends TestCase
{
    public function testStockNameAndSuggestion(): void
    {
        $this->assertSame('JOGO DE RODA FIAT ARGO 2025 GRAFITE R15', RimNames::stockName('RODA FIAT ARGO 2025 GRAFITE', 15));
        $this->assertSame('JOGO DE RODA USADA TARANTULA R14', RimNames::stockName('RODA TARANTULA', 14, 'usado'));
        $cat = [['id' => 1, 'name' => 'RODA ONIX PREMIER 2024', 'rim' => 15], ['id' => 2, 'name' => 'RODA FIAT ARGO DIAMANTE PRETO', 'rim' => 14], ['id' => 3, 'name' => 'RODA VOLCANO PRATA', 'rim' => 15], ['id' => 4, 'name' => 'RODA HB20 2026 DIAMANTE PRETO', 'rim' => 15]];
        $this->assertSame(4, RimNames::suggest(['name' => 'JOGO DE RODA HB 20 R15 - DIAMANTADA PRATA', 'rim' => '15'], $cat)['id']);
        $this->assertSame(2, RimNames::suggest(['name' => 'JOGO DE RODA ARGO R14 - DIAMANTADA PRETA', 'rim' => '14'], $cat)['id']);
        $this->assertNull(RimNames::suggest(['name' => 'JOGO DE RODA KS R17 - PRATA', 'rim' => '17'], $cat));
    }

    public function testApplyChangesOnlyNameAndLink(): void
    {
        $cid = Database::insert("INSERT INTO rim_catalog (name, brand, rim, path) VALUES ('RODA TESTE XYZ', 'X', 15, 'rodas/teste-xyz-" . uniqid() . ".webp')");
        $pid = Database::insert("INSERT INTO products (name, category, condition_type, rim, price, cost_price, stock) VALUES ('JOGO DE RODA KR R15 - PRETA', 'roda', 'novo', '15', 2400, 1800, 3)");
        $this->assertSame(1, RimNames::apply([$pid => $cid]));
        $p = Database::one('SELECT name, price, stock, rim_catalog_id FROM products WHERE id = ?', [$pid]);
        $this->assertSame('JOGO DE RODA TESTE XYZ R15', $p['name']);
        $this->assertSame(2400.0, (float) $p['price']);
        $this->assertSame(3, (int) $p['stock']);
        $this->assertSame($cid, (int) $p['rim_catalog_id']);
        Database::run('DELETE FROM products WHERE id = ?', [$pid]);
        Database::run('DELETE FROM rim_catalog WHERE id = ?', [$cid]);
    }
}
