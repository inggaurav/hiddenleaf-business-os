<?php

namespace Tests\Feature\Account;

use App\Domain\Accounting\CurrencyMismatchException;
use App\Domain\Accounting\Money;
use PHPUnit\Framework\TestCase;

class MoneyPrecisionTest extends TestCase
{
    public function test_addition_point_one_plus_point_two_equals_point_three(): void
    {
        $this->assertTrue(Money::of(0.10)->add(Money::of(0.20))->equals(Money::of(0.30)));
    }

    public function test_invoice_partial_payment_exact_sum(): void
    {
        $this->assertTrue(Money::of(50.05)->add(Money::of(50.05))->equals(Money::of(100.10)));
    }

    public function test_overpayment_detected(): void
    {
        $this->assertTrue(Money::of(100.01)->greaterThan(Money::of(100.00)));
    }

    public function test_three_way_split_sums_correctly(): void
    {
        $sum = Money::of(33.33)->add(Money::of(33.33))->add(Money::of(33.34));
        $this->assertTrue($sum->equals(Money::of(100.00)));
    }

    public function test_subtract_yields_correct_result(): void
    {
        $this->assertTrue(Money::of(100.00)->subtract(Money::of(33.33))->equals(Money::of(66.67)));
    }

    public function test_multiply_tax_calculation(): void
    {
        $this->assertTrue(Money::of(100.00)->multiply(0.18)->equals(Money::of(18.00)));
    }

    public function test_zero_detection(): void
    {
        $this->assertTrue(Money::of(0.00)->isZero());
        $this->assertFalse(Money::of(0.01)->isZero());
    }

    public function test_negative_detection(): void
    {
        $this->assertTrue(Money::of(-1.00)->isNegative());
        $this->assertFalse(Money::of(0.00)->isNegative());
    }

    public function test_comparison_methods(): void
    {
        $this->assertTrue(Money::of(10)->lessThan(Money::of(20)));
        $this->assertTrue(Money::of(20)->greaterThanOrEqual(Money::of(20)));
        $this->assertTrue(Money::of(20)->lessThanOrEqual(Money::of(20)));
    }

    public function test_storage_string_format(): void
    {
        $this->assertEquals('10.50', Money::of(10.5)->toString());
        $this->assertEquals('10.00', Money::of(10)->toString());
    }

    public function test_same_currency_arithmetic_is_allowed(): void
    {
        $total = Money::forCurrency('10.25', 'USD')->add(Money::forCurrency('1.75', 'USD'));

        $this->assertSame('USD', $total->getCurrency());
        $this->assertSame('12.00', $total->toStorageString());
    }

    public function test_cross_currency_arithmetic_is_rejected(): void
    {
        $this->expectException(CurrencyMismatchException::class);

        Money::forCurrency('10.00', 'USD')->add(Money::forCurrency('10', 'JPY'));
    }

    public function test_currency_aware_precision_scales(): void
    {
        $this->assertSame('100', Money::forCurrency('100.49', 'JPY')->toStorageString());
        $this->assertSame('1.234', Money::forCurrency('1.2344', 'KWD')->toStorageString());
        $this->assertSame('1.2345', Money::forCurrency('1.23459', 'CLF')->toStorageString());
    }
}
