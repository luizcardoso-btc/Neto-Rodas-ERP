<?php
declare(strict_types=1);

use App\Reports;
use PHPUnit\Framework\TestCase;

final class ReportsTest extends TestCase
{
    public function testPayrollIsProportionalToElapsedDays(): void
    {
        // 3 vendedores x R$ 800/semana = R$ 2.400; 7 dias = uma semana.
        $this->assertSame(2400.0, Reports::payroll(2400.0, 7));
        $this->assertSame(1028.57, Reports::payroll(2400.0, 3));
        $this->assertSame(10285.71, Reports::payroll(2400.0, 30));
    }

    public function testPeriodStopsAtTodayInCurrentMonth(): void
    {
        $p = Reports::period('2026-10', new DateTimeImmutable('2026-10-12'));
        $this->assertSame(31, $p['days']);
        $this->assertSame(12, $p['elapsed']);
        $past = Reports::period('2026-02', new DateTimeImmutable('2026-10-12'));
        $this->assertSame(28, $past['elapsed']);
    }
}
