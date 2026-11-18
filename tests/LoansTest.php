<?php
declare(strict_types=1);

use App\Database;
use App\Loans;
use PHPUnit\Framework\TestCase;

final class LoansTest extends TestCase
{
    private int $pid;

    protected function setUp(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['loan_settlements', 'loans', 'stock_movements', 'accounts_receivable', 'accounts_payable', 'sale_payments', 'sale_items', 'sales', 'products', 'customers'] as $t) {
            $pdo->exec("TRUNCATE TABLE $t");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $this->pid = Database::insert("INSERT INTO products (name, cost_price, price, stock) VALUES ('PNEU 175/70 R14', 200, 300, 10)");
    }

    private function stock(): int
    {
        return (int) Database::value('SELECT stock FROM products WHERE id = ?', [$this->pid]);
    }

    public function testLendThenPartialReturnThenPaymentBecomesSale(): void
    {
        $id = Loans::create(['direction' => 'out', 'partner' => 'pneus do joao', 'partner_phone' => '75999990000', 'product_id' => $this->pid, 'quantity' => 4, 'unit_value' => '250,00']);
        $this->assertSame(6, $this->stock());
        $this->assertSame(['owe' => 0, 'owed' => 4, 'late' => 0], Loans::totals());

        Loans::settle($id, ['kind' => 'devolucao', 'quantity' => 2]);
        $this->assertSame(8, $this->stock());
        $this->assertSame('aberto', Loans::find($id)['status']);

        Loans::settle($id, ['kind' => 'pagamento', 'quantity' => 2, 'amount' => '500', 'method' => 'pix']);
        $this->assertSame(8, $this->stock()); // nao baixa de novo
        $l = Loans::find($id);
        $this->assertSame('fechado', $l['status']);
        $sale = Database::one("SELECT * FROM sales WHERE source = 'emprestimo'");
        $this->assertSame(['atacado', 500.0, 'paid'], [$sale['sale_type'], (float) $sale['total'], $sale['payment_status']]);
        $this->assertSame(200.0, (float) Database::value('SELECT unit_cost FROM sale_items WHERE sale_id = ?', [$sale['id']]));
        $this->assertSame('PNEUS DO JOAO', Database::value('SELECT first_name FROM customers WHERE id = ?', [$sale['customer_id']]));
        $this->assertSame(['owe' => 0, 'owed' => 0, 'late' => 0], Loans::totals());

        // apagar a venda do pagamento reabre o emprestimo e o estoque continua certo
        App\SaleService::delete((int) $sale['id']);
        $this->assertSame(8, $this->stock());
        $this->assertSame('aberto', Loans::find($id)['status']);
        $this->assertSame(2, Loans::find($id)['pending']);
    }

    public function testBorrowIntoStockThenPayPartner(): void
    {
        $id = Loans::create(['direction' => 'in', 'partner' => 'Rodas X', 'product_id' => $this->pid, 'quantity' => 2, 'add_stock' => '1', 'due_date' => date('Y-m-d', strtotime('-1 day'))]);
        $this->assertSame(12, $this->stock());
        $this->assertSame(['owe' => 2, 'owed' => 0, 'late' => 1], Loans::totals());
        Loans::settle($id, ['kind' => 'pagamento', 'quantity' => 2, 'amount' => '420,00']);
        $this->assertSame(12, $this->stock()); // vendemos/ficamos com eles: pagamos
        $ap = Database::one('SELECT * FROM accounts_payable');
        $this->assertSame(['ativo', 420.0], [$ap['kind'], (float) $ap['amount']]);
        $this->assertNotNull($ap['paid_at']);
        $this->assertSame('fechado', Loans::find($id)['status']);
    }

    public function testBorrowWithoutStockAndReturn(): void
    {
        $id = Loans::create(['direction' => 'in', 'partner' => 'Rodas X', 'description' => '205/55 r16 michelin', 'quantity' => 1]);
        $this->assertSame(10, $this->stock());
        Loans::settle($id, ['kind' => 'devolucao', 'quantity' => 1]);
        $this->assertSame('fechado', Loans::find($id)['status']);
    }

    public function testValidationAndDelete(): void
    {
        try {
            Loans::create(['direction' => 'out', 'partner' => 'A', 'product_id' => $this->pid, 'quantity' => 11]);
            $this->fail('deveria barrar estoque insuficiente');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Estoque insuficiente', $e->getMessage());
        }
        $id = Loans::create(['direction' => 'out', 'partner' => 'A', 'product_id' => $this->pid, 'quantity' => 3]);
        $this->assertSame(7, $this->stock());
        $this->expectException(DomainException::class);
        try {
            Loans::settle($id, ['kind' => 'devolucao', 'quantity' => 4]);
        } finally {
            Loans::delete($id);
            $this->assertSame(10, $this->stock());
            $this->assertNull(Loans::find($id));
        }
    }
}
