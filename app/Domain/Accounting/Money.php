<?php

namespace App\Domain\Accounting;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable decimal-safe money value object using BCMath.
 *
 * All authoritative financial calculations (payments, outstanding balances,
 * journal entries) MUST use this instead of PHP floats.
 *
 * Supports ISO-4217 currency-aware minor unit scale (0, 2, 3, 4 decimals).
 */
final class Money implements JsonSerializable, Stringable
{
    public const DEFAULT_SCALE = 2;

    private const CURRENCY_SCALES = [
        'JPY' => 0, 'KRW' => 0, 'VND' => 0, 'BIF' => 0, 'DJF' => 0,
        'GNF' => 0, 'ISK' => 0, 'KMF' => 0, 'PYG' => 0, 'RWF' => 0,
        'UGX' => 0, 'VUV' => 0, 'XAF' => 0, 'XOF' => 0, 'XPF' => 0,
        'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3,
        'OMR' => 3, 'TND' => 3,
        'CLF' => 4,
    ];

    private string $amount;

    private int $scale;

    private ?string $currency;

    private function __construct(string $amount, int $scale = self::DEFAULT_SCALE, ?string $currency = null)
    {
        $this->amount = $amount;
        $this->scale = $scale;
        $this->currency = $currency ? strtoupper($currency) : null;
    }

    /**
     * Create a Money instance from any numeric input with an explicit scale or currency.
     */
    public static function of(string|int|float $value, int|string|null $scaleOrCurrency = null): self
    {
        $scale = self::DEFAULT_SCALE;
        $currency = null;

        if (is_string($scaleOrCurrency) && ! is_numeric($scaleOrCurrency)) {
            $currency = strtoupper($scaleOrCurrency);
            $scale = self::scaleForCurrency($currency);
        } elseif (is_numeric($scaleOrCurrency)) {
            $scale = (int) $scaleOrCurrency;
        }

        if ($scale < 0 || $scale > 8) {
            throw new InvalidArgumentException('Money scale must be between 0 and 8 decimal places.');
        }

        if (is_float($value)) {
            $value = number_format($value, $scale, '.', '');
        }

        $value = (string) $value;

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Invalid money value: {$value}");
        }

        return new self(bcadd($value, '0', $scale), $scale, $currency);
    }

    public static function forCurrency(string|int|float $value, string $currency): self
    {
        return self::of($value, $currency);
    }

    public static function scaleForCurrency(?string $currency): int
    {
        if (! $currency) {
            return self::DEFAULT_SCALE;
        }

        return self::CURRENCY_SCALES[strtoupper($currency)] ?? self::DEFAULT_SCALE;
    }

    public static function zero(int|string|null $scaleOrCurrency = null): self
    {
        return self::of('0', $scaleOrCurrency);
    }

    public function getScale(): int
    {
        return $this->scale;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        $this->assertCompatibleCurrency($other);
        $scale = max($this->scale, $other->scale);

        return new self(bcadd($this->amount, $other->amount, $scale), $scale, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertCompatibleCurrency($other);
        $scale = max($this->scale, $other->scale);

        return new self(bcsub($this->amount, $other->amount, $scale), $scale, $this->currency);
    }

    public function multiply(string|int|float $factor): self
    {
        $factor = is_float($factor) ? number_format($factor, 10, '.', '') : (string) $factor;

        if (! is_numeric($factor)) {
            throw new InvalidArgumentException("Invalid multiplication factor: {$factor}");
        }

        return new self(bcmul($this->amount, $factor, $this->scale), $this->scale, $this->currency);
    }

    public function multiplyByDecimal(string $decimalQty): self
    {
        return $this->multiply($decimalQty);
    }

    public function divide(string|int|float $divisor): self
    {
        $divisor = is_float($divisor) ? number_format($divisor, 10, '.', '') : (string) $divisor;

        if (! is_numeric($divisor)) {
            throw new InvalidArgumentException("Invalid divisor: {$divisor}");
        }

        if (bccomp($divisor, '0', $this->scale) === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return new self(bcdiv($this->amount, $divisor, $this->scale), $this->scale, $this->currency);
    }

    public function abs(): self
    {
        if ($this->isNegative()) {
            return new self(bcmul($this->amount, '-1', $this->scale), $this->scale, $this->currency);
        }

        return new self($this->amount, $this->scale, $this->currency);
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertCompatibleCurrency($other);
        $scale = max($this->scale, $other->scale);

        return bccomp($this->amount, $other->amount, $scale) > 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->isGreaterThan($other);
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        $this->assertCompatibleCurrency($other);
        $scale = max($this->scale, $other->scale);

        return bccomp($this->amount, $other->amount, $scale) >= 0;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return $this->isGreaterThanOrEqual($other);
    }

    public function isLessThan(self $other): bool
    {
        $this->assertCompatibleCurrency($other);
        $scale = max($this->scale, $other->scale);

        return bccomp($this->amount, $other->amount, $scale) < 0;
    }

    public function lessThan(self $other): bool
    {
        return $this->isLessThan($other);
    }

    public function isLessThanOrEqual(self $other): bool
    {
        $this->assertCompatibleCurrency($other);
        $scale = max($this->scale, $other->scale);

        return bccomp($this->amount, $other->amount, $scale) <= 0;
    }

    public function lessThanOrEqual(self $other): bool
    {
        return $this->isLessThanOrEqual($other);
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', $this->scale) === 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', $this->scale) < 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', $this->scale) > 0;
    }

    public function equals(self $other): bool
    {
        $this->assertCompatibleCurrency($other);
        $scale = max($this->scale, $other->scale);

        return bccomp($this->amount, $other->amount, $scale) === 0;
    }

    public function max(self $other): self
    {
        $this->assertCompatibleCurrency($other);

        return $this->isGreaterThan($other)
            ? new self($this->amount, $this->scale, $this->currency)
            : new self($other->amount, $other->scale, $other->currency);
    }

    public function min(self $other): self
    {
        $this->assertCompatibleCurrency($other);

        return $this->isLessThan($other)
            ? new self($this->amount, $this->scale, $this->currency)
            : new self($other->amount, $other->scale, $other->currency);
    }

    public function toStorageString(): string
    {
        return $this->amount;
    }

    public function toString(): string
    {
        return $this->amount;
    }

    /**
     * Display/API compatibility only. Never use this float for authoritative arithmetic.
     */
    public function toFloat(): float
    {
        return (float) $this->amount;
    }

    public function jsonSerialize(): string
    {
        return $this->amount;
    }

    public function __toString(): string
    {
        return $this->amount;
    }

    private function assertCompatibleCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatchException::between($this->currency, $other->currency);
        }
    }
}
