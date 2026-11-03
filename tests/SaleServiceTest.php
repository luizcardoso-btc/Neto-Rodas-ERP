<?php
declare(strict_types=1);

use App\Database;
use App\SaleService;
use PHPUnit\Framework\TestCase;

final class SaleServiceTest extends TestCase
{
    private int $pid;

    protected function setUp(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['stock_movements', 'accounts_receivable', 'sale_payments', 'sale_items', 'sales', 'products', 'customers', 'sellers', 'creditor_payments', 'accounts_payable', 'sale_audit'] as $t) {
            $pdo->exec("TRUNCATE TABLE $t");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $this->pid = Database::insert("INSERT INTO products (name, cost_price, price, stock) VALUES ('Pneu 175/70 R14', 200, 300, 10)");
    }

    private function sale(array $over = []): array
    {
        return array_replace([
            'request_token' => uuid4(),
            'items' => [['product_id' => $this->pid, 'quantity' => 2, 'unit_price' => 300.0]],
            'payments' => [['method' => 'pix', 'amount' => 600.0]],
        ], $over);
    }

    public function testSaleDecrementsStockAndRecordsCostSnapshot(): void
    {
        $r = SaleService::create($this->sale());
        $this->assertFalse($r['duplicate']);
        $this->assertSame(8, (int) Database::value('SELECT stock FROM products WHERE id = ?', [$this->pid]));
        $this->assertSame(200.0, (float) Database::value('SELECT unit_cost FROM sale_items WHERE sale_id = ?', [$r['id']]));
        $this->assertSame(-2, (int) Database::value("SELECT quantity FROM stock_movements WHERE sale_id = ? AND type = 'out'", [$r['id']]));
    }

    public function testSameTokenIsIdempotent(): void
    {
        $data = $this->sale();
        $a = SaleService::create($data);
        $b = SaleService::create($data);
        $this->assertTrue($b['duplicate']);
        $this->assertSame($a['id'], $b['id']);
        $this->assertSame(8, (int) Database::value('SELECT stock FROM products WHERE id = ?', [$this->pid]));
        $this->assertSame(1, (int) Database::value('SELECT COUNT(*) FROM sales'));
    }

    public function testPaymentsMustMatchTotal(): void
    {
        $this->expectException(DomainException::class);
        SaleService::create($this->sale(['payments' => [['method' => 'pix', 'amount' => 500.0]]]));
    }

    public function testInsufficientStockRollsBackEverything(): void
    {
        try {
            SaleService::create($this->sale(['items' => [['product_id' => $this->pid, 'quantity' => 11, 'unit_price' => 300.0]], 'payments' => [['method' => 'pix', 'amount' => 3300.0]]]));
            $this->fail('Deveria recusar estoque insuficiente');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Estoque insuficiente', $e->getMessage());
        }
        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM sales'));
        $this->assertSame(10, (int) Database::value('SELECT stock FROM products WHERE id = ?', [$this->pid]));
    }

    public function testSplitPaymentWithTradeInCreatesUsedStockAndReceivable(): void
    {
        $r = SaleService::create($this->sale([
            'due_date' => '2026-12-01',
            'payments' => [
                ['method' => 'pix', 'amount' => 200.0],
                ['method' => 'troca', 'amount' => 200.0, 'note' => '2 pneus 175/70 usados', 'trade_qty' => 2],
                ['method' => 'prazo', 'amount' => 200.0],
            ],
        ]));
        $used = Database::one("SELECT * FROM products WHERE condition_type = 'usado'");
        $this->assertSame(2, (int) $used['stock']);
        $this->assertSame(100.0, (float) $used['cost_price']);
        $ar = Database::one('SELECT * FROM accounts_receivable WHERE sale_id = ?', [$r['id']]);
        $this->assertSame(200.0, (float) $ar['amount']);
        $this->assertSame('2026-12-01', $ar['due_date']);
        $this->assertSame('pending', Database::value('SELECT payment_status FROM sales WHERE id = ?', [$r['id']]));
    }

    public function testNfeRequiresAddressAndTaxId(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/CPF\/CNPJ.*logradouro/');
        SaleService::create($this->sale(['wants_nfe' => true, 'customer' => ['first_name' => 'Ana']]));
    }

    public function testNewCustomerAndNewProductAreCreatedInline(): void
    {
        $r = SaleService::create($this->sale([
            'customer' => ['first_name' => 'Rogerio', 'phone' => '75999922002', 'car' => 'Onix'],
            'items' => [['name' => 'Alinhamento', 'quantity' => 1, 'unit_price' => 80.0]],
            'payments' => [['method' => 'dinheiro', 'amount' => 80.0]],
        ]));
        $this->assertSame('Rogerio', Database::value('SELECT c.first_name FROM sales s JOIN customers c ON c.id = s.customer_id WHERE s.id = ?', [$r['id']]));
        // Servico/taxa nao cria produto nem mexe em estoque: vira linha sem produto, categoria servico.
        $this->assertSame(0, (int) Database::value("SELECT COUNT(*) FROM products WHERE name = 'Alinhamento'"));
        $this->assertSame('servico', Database::value('SELECT category FROM sale_items WHERE sale_id = ?', [$r['id']]));
        // Mesmo telefone reaproveita o cliente em vez de duplicar.
        SaleService::create($this->sale(['customer' => ['first_name' => 'Rogerio', 'phone' => '75 99992-2002']]));
        $this->assertSame(1, (int) Database::value('SELECT COUNT(*) FROM customers'));
    }

    public function testAdminEditAdjustsStockTradeAndServiceFee(): void
    {
        $r = SaleService::create($this->sale([
            'items' => [['product_id' => $this->pid, 'quantity' => 2, 'unit_price' => 300.0]],
            'payments' => [['method' => 'pix', 'amount' => 400.0], ['method' => 'troca', 'amount' => 200.0, 'note' => '175/65 R14 usado', 'trade_qty' => 2]],
        ]));
        $id = $r['id'];
        $item = Database::one('SELECT id FROM sale_items WHERE sale_id = ?', [$id]);
        $pays = Database::all('SELECT id, method FROM sale_payments WHERE sale_id = ? ORDER BY id', [$id]);
        $usedPid = (int) Database::value("SELECT product_id FROM sale_payments WHERE id = ?", [$pays[1]['id']]);
        $this->assertSame(2, (int) Database::value('SELECT stock FROM products WHERE id = ?', [$usedPid]));

        // 3 pneus a 300, taxa de servico 50, troca passa a valer 300 (1 pneu), pix cobre o resto.
        SaleService::update($id, [
            'items' => [
                ['id' => $item['id'], 'product_id' => $this->pid, 'quantity' => 3, 'unit_price' => 300.0],
                ['product_id' => null, 'description' => 'Taxa de servico', 'quantity' => 1, 'unit_price' => 50.0],
            ],
            'payments' => [
                ['id' => $pays[0]['id'], 'method' => 'pix', 'amount' => 650.0],
                ['id' => $pays[1]['id'], 'method' => 'troca', 'amount' => 300.0, 'note' => '175/65 R14 usado', 'trade_qty' => 1],
            ],
        ], ['id' => null, 'username' => 'admin']);

        $this->assertSame(7, (int) Database::value('SELECT stock FROM products WHERE id = ?', [$this->pid]));
        $this->assertSame(1, (int) Database::value('SELECT stock FROM products WHERE id = ?', [$usedPid]));
        $this->assertSame(300.0, (float) Database::value('SELECT price FROM products WHERE id = ?', [$usedPid]));
        $this->assertSame(950.0, (float) Database::value('SELECT total FROM sales WHERE id = ?', [$id]));
        $this->assertSame(1, (int) Database::value('SELECT COUNT(*) FROM sale_items WHERE sale_id = ? AND product_id IS NULL', [$id]));
        $this->assertSame(1, (int) Database::value('SELECT COUNT(*) FROM sale_audit WHERE sale_id = ?', [$id]));
    }

    public function testEditRejectsPaymentsThatDoNotMatchTotal(): void
    {
        $r = SaleService::create($this->sale());
        $item = Database::one('SELECT id FROM sale_items WHERE sale_id = ?', [$r['id']]);
        $this->expectException(DomainException::class);
        SaleService::update($r['id'], ['items' => [['id' => $item['id'], 'product_id' => $this->pid, 'quantity' => 1, 'unit_price' => 300.0]],
            'payments' => [['method' => 'pix', 'amount' => 100.0]]]);
    }

    public function testWheelTradeInGoesToUsedWheelStock(): void
    {
        $r = SaleService::create($this->sale([
            'items' => [['product_id' => $this->pid, 'quantity' => 1, 'unit_price' => 300.0]],
            'payments' => [['method' => 'pix', 'amount' => 200.0], ['method' => 'troca', 'amount' => 100.0, 'note' => 'ARO 15 LIGA LEVE', 'trade_qty' => 4, 'trade_category' => 'roda']],
        ]));
        $row = Database::one("SELECT category, condition_type, stock, name FROM products WHERE condition_type = 'usado'");
        $this->assertSame('roda', $row['category']);
        $this->assertSame(4, (int) $row['stock']);
        $this->assertSame('roda', Database::value("SELECT trade_category FROM sale_payments WHERE sale_id = ? AND method = 'troca'", [$r['id']]));
        $this->assertCount(1, \App\Lookup::products('liga', 'roda'));
    }

    public function testPdvCustomerRequiredFields(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/CPF.*TELEFONE.*CEP.*CARRO/s');
        SaleService::assertPdvCustomer(['first_name' => 'ANA', 'email' => 'ANA@X.COM']);
    }

    public function testPdvCustomerCompleteIsAccepted(): void
    {
        SaleService::assertPdvCustomer(['first_name' => 'ANA', 'email' => 'ANA@X.COM', 'tax_id' => '123.456.789-09', 'phone' => '75 99999-1111', 'zip' => '44020-385', 'car' => 'POLO']);
        $this->assertSame('ANA CAFÉ', upper(' ana café '));
    }

    public function testQuoteSaveAndPdf(): void
    {
        $id = \App\Quote::save(null, [
            'customer_name' => 'maria', 'customer_phone' => '75999991111',
            'discount' => '50,00',
            'items' => [['category' => 'pneu', 'description' => '175/65 r14', 'quantity' => 4, 'unit_price' => '300,00'], ['category' => 'servico', 'description' => 'alinhamento', 'quantity' => 1, 'unit_price' => '80']],
        ], 'admin');
        $q = \App\Quote::find($id);
        $this->assertSame('MARIA', $q['customer_name']);
        $this->assertSame(1230.0, (float) $q['total']);
        $pdf = \App\Quote::pdf($q);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertNotNull(\App\Quote::findByToken($q['token']));
        $this->assertNull(\App\Quote::findByToken('x'));
    }

    public function testPaymentSentToCreditorCreatesSingleLedgerEntryAndKeepsRevenue(): void
    {
        $seller = Database::insert("INSERT INTO sellers (name, access_token) VALUES ('JOAO', ?)", [bin2hex(random_bytes(20))]);
        $r = SaleService::create($this->sale([
            'seller_id' => $seller,
            'payments' => [['method' => 'pix', 'amount' => 400.0, 'to_creditor' => true], ['method' => 'dinheiro', 'amount' => 200.0]],
        ]));
        $this->assertSame(600.0, (float) Database::value('SELECT total FROM sales WHERE id = ?', [$r['id']]));
        $this->assertSame(1, (int) Database::value('SELECT COUNT(*) FROM creditor_payments'));
        $this->assertSame(400.0, (float) Database::value('SELECT amount FROM creditor_payments WHERE sale_id = ?', [$r['id']]));
        // Mesmo token = mesma venda: nada duplica.
        // Edicao do admin refaz o extrato sem duplicar e acompanha o valor.
        $item = Database::one('SELECT id FROM sale_items WHERE sale_id = ?', [$r['id']]);
        $pays = Database::all('SELECT id FROM sale_payments WHERE sale_id = ? ORDER BY id', [$r['id']]);
        SaleService::update($r['id'], ['seller_id' => $seller,
            'items' => [['id' => $item['id'], 'product_id' => $this->pid, 'quantity' => 2, 'unit_price' => 300.0]],
            'payments' => [['id' => $pays[0]['id'], 'method' => 'pix', 'amount' => 350.0, 'to_creditor' => true], ['id' => $pays[1]['id'], 'method' => 'dinheiro', 'amount' => 250.0]]]);
        $this->assertSame(1, (int) Database::value('SELECT COUNT(*) FROM creditor_payments'));
        $this->assertSame(350.0, (float) Database::value('SELECT amount FROM creditor_payments'));
        $c = \App\Finance::creditor(date('Y-m'));
        $this->assertSame(350.0, $c['received']);
        // Repasse avulso igual ao automatico exige confirmacao (evita duplicidade).
        $this->expectException(DomainException::class);
        \App\Finance::addCreditorPayment($seller, 350.0, date('Y-m-d'), '', false, 'admin');
    }

    public function testCreditorFlagRequiresSeller(): void
    {
        $this->expectException(DomainException::class);
        SaleService::create($this->sale(['payments' => [['method' => 'pix', 'amount' => 600.0, 'to_creditor' => true]]]));
    }

    public function testFinanceSummaryAndAlerts(): void
    {
        SaleService::create($this->sale());
        Database::insert("INSERT INTO accounts_payable (description, category, kind, amount, due_date, paid_at) VALUES ('ALUGUEL','ALUGUEL','despesa',100,CURDATE(),NOW())");
        Database::insert("INSERT INTO accounts_payable (description, category, kind, amount, due_date) VALUES ('ENERGIA','ENERGIA','despesa',50,'2020-01-01')");
        $r = \App\Finance::range('mes');
        $s = \App\Finance::summary($r['from'], $r['to'], $r['days']);
        $this->assertSame(600.0, $s['revenue']);
        $this->assertSame(400.0, $s['cmv']);
        $this->assertSame(100.0, $s['expenses']);
        $this->assertSame(100.0, $s['profit']);
        $titles = array_column(\App\Finance::alerts($s), 'title');
        $this->assertContains('Contas a pagar vencidas', $titles);
    }
}
