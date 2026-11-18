<?php
declare(strict_types=1);

use App\Database;
use App\Receivables;
use App\SaleService;
use PHPUnit\Framework\TestCase;

final class ReceivablesTest extends TestCase
{
    private int $sale;

    protected function setUp(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['stock_movements', 'accounts_receivable', 'sale_payments', 'sale_items', 'sales', 'products', 'customers'] as $t) {
            $pdo->exec("TRUNCATE TABLE $t");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $pid = Database::insert("INSERT INTO products (name, cost_price, price, stock) VALUES ('P', 100, 300, 10)");
        $this->sale = SaleService::create(['request_token' => uuid4(), 'items' => [['product_id' => $pid, 'quantity' => 2, 'unit_price' => 300.0]],
            'payments' => [['method' => 'pix', 'amount' => 100.0], ['method' => 'prazo', 'amount' => 500.0, 'due' => '2026-11-20']]])['id'];
    }

    private function ar(): array
    {
        return Database::one('SELECT * FROM accounts_receivable WHERE sale_id = ? AND paid_at IS NULL', [$this->sale]) ?? [];
    }

    private function status(): string
    {
        return (string) Database::value('SELECT payment_status FROM sales WHERE id = ?', [$this->sale]);
    }

    public function testEditDateKeepsAmountOfSaleReceivable(): void
    {
        Receivables::update((int) $this->ar()['id'], ['due_date' => '2026-12-05', 'amount' => '1', 'description' => 'paga dia 5']);
        $a = $this->ar();
        $this->assertSame(['2026-12-05', 500.0, 'PAGA DIA 5'], [$a['due_date'], (float) $a['amount'], $a['description']]);
        Receivables::update((int) $a['id'], ['due_date' => '']);
        $this->assertNull($this->ar()['due_date']);
    }

    public function testPartialThenFullReceiveAndReopen(): void
    {
        $id = (int) $this->ar()['id'];
        Receivables::receive($id, '200,00', '2026-12-10');
        $a = $this->ar();
        $this->assertSame([300.0, '2026-12-10'], [(float) $a['amount'], $a['due_date']]);
        $this->assertSame(200.0, (float) Database::value('SELECT amount FROM accounts_receivable WHERE paid_at IS NOT NULL'));
        $this->assertSame('pending', $this->status());
        Receivables::receive($id);
        $this->assertSame('paid', $this->status());
        Receivables::reopen($id);
        $this->assertSame('pending', $this->status());
    }

    public function testDelete(): void
    {
        Receivables::delete((int) $this->ar()['id']);
        $this->assertSame([], $this->ar());
        $this->assertSame('paid', $this->status());
    }
}
