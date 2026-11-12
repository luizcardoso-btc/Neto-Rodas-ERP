<?php
declare(strict_types=1);

use App\Database;
use App\Marketing;
use App\SaleService;
use PHPUnit\Framework\TestCase;

final class MarketingTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['offer_contacts', 'offers', 'stock_movements', 'accounts_receivable', 'sale_payments', 'sale_items', 'sales', 'products', 'customers'] as $t) {
            $pdo->exec("TRUNCATE TABLE $t");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function testRenderFillsNameCarAndLink(): void
    {
        $t = Marketing::render(Marketing::DEFAULT_REACTIVATE, ['first_name' => 'JOÃO PEDRO', 'car' => 'GOL'], 'https://chat.whatsapp.com/AbC');
        $this->assertStringContainsString('Olá João!', $t);
        $this->assertStringContainsString('com o seu GOL', $t);
        $this->assertStringContainsString('https://chat.whatsapp.com/AbC', $t);
        $this->assertStringNotContainsString('{', Marketing::render(Marketing::DEFAULT_REACTIVATE, ['first_name' => 'ANA'], ''));
        $this->assertStringStartsWith('https://wa.me/5575999998888?text=Ol%C3%A1', Marketing::waUrl('75 99999-8888', 'Olá'));
    }

    public function testImportCsvDetectsColumnsAndMergesDuplicates(): void
    {
        Database::run("INSERT INTO customers (first_name, phone) VALUES ('MARIA', '75 99999-1111')");
        $csv = "Nome;Celular;CPF;Veiculo;Cidade\nMaria Souza;(75) 99999-1111;;Onix;Feira de Santana\nJosé Lima;75988887777;123.456.789-09;Gol;Feira\n;;;;\nCarlos;;;;\n";
        $r = Marketing::importCsv($csv, 'sistema antigo');
        $this->assertSame(['inserted' => 2, 'updated' => 1, 'skipped' => 1], $r);
        $maria = Database::one("SELECT * FROM customers WHERE first_name = 'MARIA'");
        $this->assertSame('ONIX', $maria['car']);
        $this->assertNull($maria['source']); // ja existia: nao muda a origem
        $jose = Database::one("SELECT * FROM customers WHERE first_name = 'JOSÉ'");
        $this->assertSame(['LIMA', '75 98888-7777', '12345678909', 'SISTEMA ANTIGO'], [$jose['last_name'], $jose['phone'], $jose['tax_id'], $jose['source']]);
        // de novo: nada duplica
        $this->assertSame(0, Marketing::importCsv($csv, 'x')['inserted']);
    }

    public function testImportGoogleContactsFormat(): void
    {
        $csv = "First Name,Last Name,Phone 1 - Value,E-mail 1 - Value\nPaulo,Vieira,+55 75 99809-6701,paulo@ex.com\n";
        $this->assertSame(1, Marketing::importCsv($csv, 'whatsapp')['inserted']);
        $p = Database::one('SELECT * FROM customers');
        $this->assertSame(['PAULO', 'VIEIRA', 'paulo@ex.com'], [$p['first_name'], $p['last_name'], $p['email']]);
        $this->assertSame('75998096701', substr(digits($p['phone']), -11));
    }

    public function testReactivateListAndOfferTargets(): void
    {
        $old = Database::insert("INSERT INTO customers (first_name, phone, car) VALUES ('ANTIGO', '75 91111-1111', 'GOL')");
        $recent = Database::insert("INSERT INTO customers (first_name, phone, car) VALUES ('RECENTE', '75 92222-2222', 'ONIX')");
        Database::insert("INSERT INTO customers (first_name, phone, opt_out) VALUES ('NAOQUER', '75 93333-3333', 1)");
        Database::insert("INSERT INTO customers (first_name) VALUES ('SEMFONE')");
        $pid = Database::insert("INSERT INTO products (name, brand, width, profile, rim, price, stock) VALUES ('PNEU', 'DUNLOP', '175', '70', '14', 300, 10)");
        $s1 = Database::insert("INSERT INTO sales (request_token, customer_id, total, payment_status, created_at) VALUES (UUID(), ?, 300, 'paid', NOW() - INTERVAL 200 DAY)", [$old]);
        Database::run("INSERT INTO sale_items (sale_id, product_id, category, description, quantity, unit_price) VALUES (?, ?, 'pneu', '175/70 R14 DUNLOP', 1, 300)", [$s1, $pid]);
        Database::insert("INSERT INTO sales (request_token, customer_id, total, payment_status) VALUES (UUID(), ?, 100, 'paid')", [$recent]);

        $ids = array_column(Marketing::reactivateList(90), 'id');
        $this->assertSame([$old], array_map('intval', $ids));
        $this->assertSame('175/70 R14 DUNLOP', Marketing::reactivateList(90)[0]['last_items']);
        Database::run('UPDATE customers SET contacted_at = NOW() WHERE id = ?', [$old]);
        $this->assertSame([], Marketing::reactivateList(90));
        $this->assertCount(1, Marketing::reactivateList(90, false, false));

        $oid = Database::insert("INSERT INTO offers (title, message, measure) VALUES ('X', 'oi', '175/70 R14')");
        $this->assertSame([$old], array_map('intval', array_column(Marketing::offerTargets(Database::one('SELECT * FROM offers WHERE id = ?', [$oid])), 'id')));
        $oid2 = Database::insert("INSERT INTO offers (title, message, measure, car_filter) VALUES ('Y', 'oi', '14', 'ONIX')");
        $this->assertCount(2, Marketing::offerTargets(Database::one('SELECT * FROM offers WHERE id = ?', [$oid2])));
        $oid3 = Database::insert("INSERT INTO offers (title, message) VALUES ('Z', 'oi')");
        $this->assertCount(2, Marketing::offerTargets(Database::one('SELECT * FROM offers WHERE id = ?', [$oid3])));
    }
}
