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
 * Supports ISO-4217 currency-aware minor unit scale (0, 2, 3 decimals).
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

        if (is_float($value)) {
            $value = number_format($value, $scale, '.', '');
        }

        $value = (string) $value;

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Invalid money value: {$value}");
        }

        return new self(bcadd($value, '0', $scale), $scale, $currency);
    }

    /**
     * Create Money specifically for a given currency code.
     */
    public static function forCurrency(string|int|float $value, string $currency): self
    {
        return self::of($value, $currency);
    }

    /**
     * Determine minor unit scale for a currency code.
     */
    public static function scaleForCurrency(?string $currency): int
    {
        if (! $currency) {
            return self::DEFAULT_SCALE;
        }

        return self::CURRENCY_SCALES[strtoupper($currency)] ?? self::DEFAULT_SCALE;
    }

    /**
     * Create a zero Money instance.
     */
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

    /**
     * Add another Money value.
     */
    public function add(self $other): self
    {
        $scale = max($this->scale, $other->scale);

        return new self(bcadd($this->amount, $other->amount, $scale), $scale, $this->currency ?? $other->currency);
    }

    /**
     * Subtract another Money value.
     */
    public function subtract(self $other): self
    {
        $scale = max($this->scale, $other->scale);

        return new self(bcsub($this->amount, $other->amount, $scale), $scale, $this->currency ?? $other->currency);
    }

    /**
     * Multiply by a factor (e.g., quantity, tax rate).
     */
    public function multiply(string|int|float $factor): self
    {
        $factor = is_float($factor) ? number_format($factor, 10, '.', '') : (string) $factor;

        return new self(bcmul($this->amount, $factor, $this->scale), $this->scale, $this->currency);
    }

    /**
     * Divide by a divisor.
     */
    public function divide(string|int|float $divisor): self
    {
        $divisor = is_float($divisor) ? number_format($divisor, 10, '.', '') : (string) $divisor;

        if (bccomp($divisor, '0', $this->scale) === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return new self(bcdiv($this->amount, $divisor, $this->scale), $this->scale, $this->currency);
    }

    /**
     * Return the absolute value.
     */
    public function abs(): self
    {
        if ($this->isNegative()) {
            return new self(bcmul($this->amount, '-1', $this->scale), $this->scale, $this->currency);
        }

        return new self($this->amount, $this->scale, $this->currency);
    }

    /**
     * Is this amount greater than the other?
     */
    public function isGreaterThan(self $other): bool
    {
        $scale = max($this->scale, $other->scale);

        return bccomp($this->amount, $other->amount, $scale) > 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->isGreaterThan($other);
    }

    /**
     * Is this amount greater than or equal to the other?
     */
    public function isGreaterThanOrEqual(self $other): bool
    {
        $scale = max($this->scale, $other->scale);

        return bccomp($this->amount, $other->amount, $scale) >= 0;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        return $this->isGreaterThanOrEqual($other);
    }

    /**
     * Is this amount less than the other?
     */
    public function isLessThan(self $other): bool
    {
        $scale = max($this->scale, $other->scale);

        return bccomp($this->amount, $other->amount, $scale) < 0;
    }

    public function lessThan(self $other): bool
    {
        return $this->isLessThan($other);
    }

    public function isLessThanOrEqual(self $other): bool
    {
        $scale = max($this->scale, $other->scale);

        return bccomp($this->amount, $other->amount, $scale) <= 0;
    }

    public function lessThanOrEqual(self $other): bool
    {
        return $this->isLessThanOrEqual($other);
    }

    /**
     * Is this amount exactly zero?
     */
    public function isZero(): bool
    {
        return bccomp($this->amount, '0', $this->scale) === 0;
    }

    /**
     * Is this amount negative?
     */
    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', $this->scale) < 0;
    }

    /**
     * Is this amount positive?
     */
    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', $this->scale) > 0;
    }

    /**
     * Exact equality comparison.
     */
    public function equals(self $other): bool
    {
        $scale = max($this->scale, $other->scale);

        return bccomp($this->amount, $other->amount, $scale) === 0;
    }

    /**
     * Return the maximum of this and another Money.
     */
    public function max(self $other): self
    {
        return $this->isGreaterThan($other) ? new self($this->amount, $this->scale, $this->currency) : new self($other->amount, $other->scale, $other->currency);
    }

    /**
     * Return the minimum of this and another Money.
     */
    public function min(self $other): self
    {
        return $this->isLessThan($other) ? new self($this->amount, $this->scale, $this->currency) : new self($other->amount, $other->scale, $other->currency);
    }

    /**
     * Get the storage-safe string representation (for DB columns).
     */
    public function toStorageString(): string
    {
        return $this->amount;
    }

    public function toString(): string
    {
        return $this->amount;
    }

    /**
     * Get as a float for legacy display/API compatibility only.
     * WARNING: Do NOT use the returned float for further arithmetic.
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
}
