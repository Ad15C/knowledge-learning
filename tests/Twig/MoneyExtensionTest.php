<?php

namespace App\Tests\Twig;

use App\Twig\MoneyExtension;
use PHPUnit\Framework\TestCase;

class MoneyExtensionTest extends TestCase
{
    public function testGetFiltersRegistersMoney(): void
    {
        $ext = new MoneyExtension();
        $filters = $ext->getFilters();

        self::assertCount(1, $filters);
        self::assertSame('money', $filters[0]->getName());
        self::assertSame([$ext, 'money'], $filters[0]->getCallable());
    }

    public function testMoneyFormatsNullAsZero(): void
    {
        $ext = new MoneyExtension();

        self::assertSame('0,00 €', $ext->money(null));
    }

    public function testMoneyFormatsEmptyStringAsZero(): void
    {
        $ext = new MoneyExtension();

        self::assertSame('0,00 €', $ext->money(''));
    }

    public function testMoneyFormatsNumberWithFrenchSeparators(): void
    {
        $ext = new MoneyExtension();

        self::assertSame('1 234,50 €', $ext->money(1234.5));
    }

    public function testMoneyAcceptsStringNumber(): void
    {
        $ext = new MoneyExtension();

        self::assertSame('10,00 €', $ext->money('10'));
    }

    public function testMoneyCustomCurrency(): void
    {
        $ext = new MoneyExtension();

        self::assertSame('10,00 $', $ext->money(10, '$'));
    }
}