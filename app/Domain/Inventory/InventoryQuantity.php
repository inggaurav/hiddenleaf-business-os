<?php

namespace App\Domain\Inventory;

use InvalidArgumentException;
use Stringable;

/**
 * Immutable fixed-scale inventory quantity.
 *
 * Inventory quantities are authoritative decimal values and must never pass
 * through PHP floating point arithmetic. Four decimal places supports common
 * fractional units while keeping all stock services deterministic.
 */
final class InventoryQuantity implements Stringable
{
    public const SCALE = 4;

    private string $value;

    private function __construct(string $value)
    {
        $this->value = self::normalize($value);
    }

    public static function of(self|string|int|float $value): self
    {
        return $value instanceof self ? $value : new self((string) $value);
    }

    public static function zero(): self
    {
        return new self('0');
    }

    public static function normalize(string $value): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Invalid inventory quantity: {$value}");
        }

        return bcadd($value, '0', self::SCALE);
    }

    public function add(self|string|int $other): self
    {
        $other = self::of($other);

        return new self(bcadd($this->value, $other->value, self::SCALE));
    }

    public function subtract(self|string|int $other): self
    {
        $other = self::of($other);

        return new self(bcsub($this->value, $other->value, self::SCALE));
    }

    public function negate(): self
    {
        return new self(bcmul($this->value, '-1', self::SCALE));
    }

    public function compare(self|string|int $other): int
    {
        $other = self::of($other);

        return bccomp($this->value, $other->value, self::SCALE);
    }

    public function greaterThan(self|string|int $other): bool
    {
        return $this->compare($other) > 0;
    }

    public function isLessThan(self|string|int $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function isLessThanOrEqual(self|string|int $other): bool
    {
        return $this->compare($other) <= 0;
    }

    public function isZero(): bool
    {
        return $this->compare(self::zero()) === 0;
    }

    public function isNegative(): bool
    {
        return $this->compare(self::zero()) < 0;
    }

    public function isPositive(): bool
    {
        return $this->compare(self::zero()) > 0;
    }

    public function absolute(): self
    {
        return $this->isNegative() ? $this->negate() : new self($this->value);
    }

    public function toStorageString(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
