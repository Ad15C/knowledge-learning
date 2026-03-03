<?php

namespace App\Tests\Twig;

use App\Twig\MoneyExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFilter;

class MoneyExtensionTest extends TestCase
{
    public function testGetFiltersRegistersMoney(): void
    {
        $ext = new MoneyExtension();
        $filters = $ext->getFilters();

        self::assertNotEmpty($filters);

        $filter = null;
        foreach ($filters as $f) {
            if ($f->getName() === 'money') {
                $filter = $f;
                break;
            }
        }

        self::assertInstanceOf(TwigFilter::class, $filter);
        self::assertSame('money', $filter->getName());
        self::assertSame([$ext, 'money'], $filter->getCallable());
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

    public function testMoneyRoundsToTwoDecimals(): void
    {
        $ext = new MoneyExtension();
        self::assertSame('10,01 €', $ext->money(10.005));
    }

    public function testMoneyFormatsNegativeValues(): void
    {
        $ext = new MoneyExtension();
        self::assertSame('-1 234,50 €', $ext->money(-1234.5));
    }

    public function testMoneyCustomCurrency(): void
    {
        $ext = new MoneyExtension();
        self::assertSame('10,00 $', $ext->money(10, '$'));
    }

    public function testTwigFilterCallableExecutesMoney(): void
    {
        $ext = new MoneyExtension();

        $filter = null;
        foreach ($ext->getFilters() as $f) {
            if ($f->getName() === 'money') {
                $filter = $f;
                break;
            }
        }

        self::assertNotNull($filter);

        $callable = $filter->getCallable();
        self::assertSame('1 000,00 €', $callable(1000));
    }
}