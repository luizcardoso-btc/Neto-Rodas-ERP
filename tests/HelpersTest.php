<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testParseMoneyAcceptsBrazilianAndPlainFormats(): void
    {
        $this->assertSame(1234.56, parse_money('1.234,56'));
        $this->assertSame(1234.56, parse_money('1234.56'));
        $this->assertSame(2100.0, parse_money('R$ 2.100,00'));
        $this->assertSame(0.0, parse_money(''));
    }

    public function testPhoneAndWhatsapp(): void
    {
        $this->assertSame('75 98222-6215', format_phone('75982226215'));
        $this->assertSame('https://wa.me/5575982226215', whatsapp_link('75 98222-6215'));
        $this->assertSame('', whatsapp_link(''));
    }

    public function testEscapesHtml(): void
    {
        $this->assertSame('&lt;b&gt;&quot;x&quot;', e('<b>"x"'));
    }
}
