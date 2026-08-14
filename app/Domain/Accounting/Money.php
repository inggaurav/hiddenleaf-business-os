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
 * Precision: 2 decimal places (suitable for USD, EUR, GBP, INR, etc.).
 */
final class Money implements JsonSerializable, Stringable
{
    private const SCALE = 2;

    private string $amount;

    private function __construct(string $amount)
    {
        $this->amount = $amount;
    }

    /**
     * Create a Money instance from any numeric input.
     *
     * Accepts: string, int, float (converted safely to string first).
     * Rejects: non-numeric values.
     */
    public static function of(string|int|float $value): self
    {
        if (is_float($value)) {
            // Convert float to string with sufficient precision to avoid loss
            $value = number_format($value, self::SCALE, '.', '');
        }

        $value = (string) $value;

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Invalid money value: {$value}");
        }

        return new self(bcadd($value, '0', self::SCALE));
    }

    /**
     * Create a zero Money instance.
     */
    public static function zero(): self
    {
        return new self('0.00');
    }

    /**
     * Add another Money value.
     */
    public function add(self $other): self
    {
        return new self(bcadd($this->amount, $other->amount, self::SCALE));
    }

    /**
     * Subtract another Money value.
     */
    public function subtract(self $other): self
    {
        return new self(bcsub($this->amount, $other->amount, self::SCALE));
    }

    /**
     * Multiply by a factor (e.g., quantity, tax rate).
     */
    public function multiply(string|int|float $factor): self
    {
        $factor = is_float($factor) ? number_format($factor, 10, '.', '') : (string) $factor;

        return new self(bcmul($this->amount, $factor, self::SCALE));
    }

    /**
     * Divide by a divisor.
     */
    public function divide(string|int|float $divisor): self
    {
        $divisor = is_float($divisor) ? number_format($divisor, 10, '.', '') : (string) $divisor;

        if (bccomp($divisor, '0', self::SCALE) === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return new self(bcdiv($this->amount, $divisor, self::SCALE));
    }

    /**
     * Return the absolute value.
     */
    public function abs(): self
    {
        if ($this->isNegative()) {
            return new self(bcmul($this->amount, '-1', self::SCALE));
        }

        return new self($this->amount);
    }

    /**
     * Is this amount greater than the other?
     */
    public function isGreaterThan(self $other): bool
    {
        return bccomp($this->amount, $other->amount, self::SCALE) > 0;
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
        return bccomp($this->amount, $other->amount, self::SCALE) >= 0;
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
        return bccomp($this->amount, $other->amount, self::SCALE) < 0;
    }

    public function lessThan(self $other): bool
    {
        return $this->isLessThan($other);
    }

    public function isLessThanOrEqual(self $other): bool
    {
        return bccomp($this->amount, $other->amount, self::SCALE) <= 0;
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
        return bccomp($this->amount, '0', self::SCALE) === 0;
    }

    /**
     * Is this amount negative?
     */
    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) < 0;
    }

    /**
     * Is this amount positive?
     */
    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) > 0;
    }

    /**
     * Exact equality comparison.
     */
    public function equals(self $other): bool
    {
        return bccomp($this->amount, $other->amount, self::SCALE) === 0;
    }

    /**
     * Return the maximum of this and another Money.
     */
    public function max(self $other): self
    {
        return $this->isGreaterThan($other) ? new self($this->amount) : new self($other->amount);
    }

    /**
     * Return the minimum of this and another Money.
     */
    public function min(self $other): self
    {
        return $this->isLessThan($other) ? new self($this->amount) : new self($other->amount);
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
