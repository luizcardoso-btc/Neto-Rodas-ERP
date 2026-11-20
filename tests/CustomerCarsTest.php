<?php
declare(strict_types=1);

use App\CustomerCars;
use App\Database;
use PHPUnit\Framework\TestCase;

final class CustomerCarsTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['customer_car_images', 'customer_cars', 'customers'] as $t) {
            $pdo->exec("TRUNCATE TABLE $t");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==') . str_repeat("\0", 200);
    }

    public function testSavesCarLinksExistingCustomerByPhoneAndKeepsImages(): void
    {
        $cust = Database::insert("INSERT INTO customers (first_name, last_name, phone) VALUES ('MARIA', 'SILVA', '75 99999-1234')");
        $id = CustomerCars::create(
            ['owner_name' => 'maria silva', 'phone' => '(75) 99999-1234', 'brand' => 'hyundai', 'model' => 'hb20', 'color' => 'branco', 'plate' => 'abc-1d23', 'tire' => '185/60r15'],
            [['data' => $this->png(), 'mime' => 'image/png', 'angle' => 'Lateral direita', 'wheels' => ['on' => true, 'list' => [['x' => .2, 'y' => .8, 'r' => .08]]]]]
        );
        $car = Database::one('SELECT * FROM customer_cars WHERE id = ?', [$id]);
        $this->assertSame($cust, (int) $car['customer_id']);
        $this->assertSame('HB20', $car['model']);
        $this->assertSame('ABC1D23', $car['plate']);
        $this->assertSame('185/60 R15', $car['tire']);
        $this->assertSame('HYUNDAI HB20', Database::value('SELECT car FROM customers WHERE id = ?', [$cust]));
        $list = CustomerCars::all();
        $this->assertCount(1, $list);
        $this->assertSame('k' . $id, $list[0]['id']);
        $this->assertCount(1, $list[0]['fotos']);
        $this->assertTrue($list[0]['fotos'][0]['rodas']['on']);
        $img = CustomerCars::image($list[0]['fotos'][0]['id']);
        $this->assertSame('image/png', $img['mime']);

        CustomerCars::update($id, ['color' => 'vermelho']);
        $this->assertSame('VERMELHO', Database::value('SELECT color FROM customer_cars WHERE id = ?', [$id]));
        CustomerCars::delete($id);
        $this->assertSame(0, (int) Database::value('SELECT COUNT(*) FROM customer_car_images'));
    }

    public function testCreatesCustomerWhenNewAndRequiresModelAndPhoto(): void
    {
        $id = CustomerCars::create(['owner_name' => 'joao pedro', 'phone' => '75988887777', 'brand' => 'fiat', 'model' => 'argo'], [['data' => $this->png(), 'mime' => 'image/png']]);
        $c = Database::one('SELECT c.* FROM customers c JOIN customer_cars k ON k.customer_id = c.id WHERE k.id = ?', [$id]);
        $this->assertSame('JOAO', $c['first_name']);
        $this->assertSame('75 98888-7777', $c['phone']);
        $this->expectException(DomainException::class);
        CustomerCars::create(['brand' => 'fiat', 'model' => ''], [['data' => $this->png(), 'mime' => 'image/png']]);
    }
}
