<?php
declare(strict_types=1);

use App\Database;
use App\Lookup;
use App\SaleService;
use PHPUnit\Framework\TestCase;

final class MeasureSearchTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['stock_movements', 'accounts_receivable', 'sale_payments', 'sale_items', 'sales', 'products', 'customers', 'sellers'] as $t) {
            $pdo->exec("TRUNCATE TABLE $t");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $sql = file_get_contents(__DIR__ . '/../migrations/002_reference_tires.sql');
        $pdo->exec($sql);
        Database::run("INSERT INTO products (name, brand, width, profile, rim, price, stock) VALUES ('Pneu Pirelli sem medida', 'Pirelli', NULL, NULL, NULL, 10, 5)");
    }

    public function testSeedHasExactlyTheFifteenReferenceMeasures(): void
    {
        $this->assertSame(16, (int) Database::value('SELECT COUNT(*) FROM products')); // 15 + 1 de teste
        Database::pdo()->exec(file_get_contents(__DIR__ . '/../migrations/002_reference_tires.sql')); // idempotente
        $this->assertSame(16, (int) Database::value('SELECT COUNT(*) FROM products'));
    }

    public function testSearchShowsAndFiltersByBrand(): void
    {
        Database::run("INSERT INTO products (name, brand, width, profile, rim, price, stock) VALUES ('Pneu Dunlop', 'Dunlop', '195', '60', '16', 400, 3), ('Pneu Goodyear', 'Goodyear', '195', '60', '16', 420, 2)");
        $both = Lookup::products('195/60 R16');
        $this->assertSame(['DUNLOP', 'GOODYEAR'], array_column($both, 'brand'));
        $this->assertCount(1, Lookup::products('195/60 R16 goodyear'));
        $this->assertSame('GOODYEAR', Lookup::products('goodyear')[0]['brand']);
        $this->assertCount(1, Lookup::products('dunlop 195'));
        $this->assertSame([], Lookup::products('michelin'));
    }

    public function testWholesaleStockOnlyShowsInWholesaleMode(): void
    {
        Database::run("INSERT INTO products (name, brand, condition_type, width, profile, rim, price, stock) VALUES ('Pneu Atacado', 'Dunlop', 'atacado', '195', '60', '16', 300, 20), ('Pneu Varejo', 'Dunlop', 'novo', '195', '60', '16', 400, 3)");
        try {
            Lookup::$mode = 'varejo';
            $this->assertSame(['novo'], array_column(Lookup::products('195/60 R16'), 'condition_type'));
            Lookup::$mode = 'atacado';
            $this->assertSame(['atacado', 'novo'], array_column(Lookup::products('195/60 R16'), 'condition_type'));
        } finally {
            Lookup::$mode = 'varejo';
        }
    }

    public function testWholesaleSearchByWordNameAndEmptyQuery(): void
    {
        Database::run("INSERT INTO products (name, brand, description, condition_type, category, width, profile, rim, price, stock) VALUES
            ('PNEU 175/70 R14 XBRI', 'XBRI', 'LOTE FECHADO 20 UN', 'atacado', 'pneu', '175', '70', '14', 250, 20),
            ('PNEU REMOLD LOTE', NULL, NULL, 'atacado', 'pneu', NULL, NULL, NULL, 100, 50),
            ('PNEU NOVO', 'DUNLOP', NULL, 'novo', 'pneu', '175', '70', '14', 400, 3),
            ('JOGO DE RODA ATACADO KR R15', 'KR', NULL, 'atacado', 'roda', NULL, NULL, '15', 900, 4)");
        try {
            Lookup::$mode = 'atacado';
            $this->assertCount(2, Lookup::products('atacado'));
            $this->assertCount(2, Lookup::products(''));
            $this->assertSame('PNEU REMOLD LOTE', Lookup::products('remold')[0]['label']);
            $this->assertSame('LOTE FECHADO 20 UN', Lookup::products('175/70 atacado')[0]['description']);
            $this->assertSame(['atacado', 'novo'], array_column(Lookup::products('175/70 R14'), 'condition_type'));
            $this->assertCount(1, Lookup::products('atacado', 'roda'));
            Lookup::$mode = 'varejo';
            $this->assertSame([], Lookup::products('remold'));
            $this->assertSame(['novo'], array_column(Lookup::products('175/70 R14'), 'condition_type'));
        } finally {
            Lookup::$mode = 'varejo';
        }
    }

    public function testSearchByDifferentMeasureFormats(): void
    {
        foreach (['175/65 R14', '175 65 14', '1756514', '175/65r14'] as $q) {
            $r = Lookup::products($q);
            $this->assertCount(1, $r, $q);
            $this->assertSame('175/65 R14', $r[0]['label']);
        }
        $this->assertGreaterThanOrEqual(2, count(Lookup::products('R18')));
        $this->assertCount(1, Lookup::products('275'));
    }

    public function testFreeTextAndProductsWithoutMeasureAreNotReturned(): void
    {
        $this->assertSame([], Lookup::products('pirelli'));
        $this->assertSame([], Lookup::products('pneu'));
        $this->assertSame([], Lookup::products('7'));
        $this->assertCount(2, Lookup::products('17')); // so o aro (R17) e busca valida
    }

    public function testTradeInBecomesUsedTireSearchableByMeasure(): void
    {
        $pid = (int) Database::value("SELECT id FROM products WHERE width='175' AND profile='65'");
        Database::run('UPDATE products SET stock = 4, price = 300 WHERE id = ?', [$pid]);
        SaleService::create([
            'request_token' => uuid4(),
            'items' => [['product_id' => $pid, 'quantity' => 1, 'unit_price' => 300.0]],
            'payments' => [['method' => 'dinheiro', 'amount' => 200.0], ['method' => 'troca', 'amount' => 100.0, 'note' => '2 pneus 185/65 R15 usados', 'trade_qty' => 2]],
        ]);
        $used = Lookup::products('185/65 R15');
        $usedRow = array_values(array_filter($used, fn ($r) => $r['condition_type'] === 'usado'));
        $this->assertCount(1, $usedRow);
        $this->assertSame(2, $usedRow[0]['stock']);
        $this->assertSame('50.00', Database::value("SELECT cost_price FROM products WHERE condition_type='usado'"));
    }
}
