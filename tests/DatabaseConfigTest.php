<?php
declare(strict_types=1);

use App\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('MYSQL_URL=');
    }

    public function testMysqlUrlWinsOverStaleDbVariables(): void
    {
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_PASS=troque-esta-senha');
        putenv('MYSQL_URL=mysql://root:p%40ss%2Fword@mysql.railway.internal:3306/railway');
        $c = Database::config();
        $this->assertSame('mysql.railway.internal', $c['host']);
        $this->assertSame('p@ss/word', $c['pass']);
        $this->assertSame('railway', $c['name']);
    }

    public function testFallsBackToDbVariables(): void
    {
        putenv('MYSQL_URL=');
        putenv('DB_HOST=db.example');
        $this->assertSame('db.example', Database::config()['host']);
    }
}
