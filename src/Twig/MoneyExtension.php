<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MoneyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', [$this, 'money']),
        ];
    }

    public function money(float|int|string|null $amount, string $currency = '€'): string
    {
        if ($amount === null || $amount === '') {
            return '0,00 '.$currency;
        }

        $value = (float) $amount;

        return number_format($value, 2, ',', ' ').' '.$currency;
    }
}