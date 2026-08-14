<?php

namespace App\Domain\Inventory;

use InvalidArgumentException;

class InventoryQuantity
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = static::normalize($value);
    }

    public static function of(string|int|float $value): self
    {
        return new self((string) $value);
    }

    public static function normalize(string $value): string
    {
        return bcadd($value, '0', 4);
    }

    public function add(self $other): self
    {
        return new self(bcadd($this->value, $other->value, 4));
    }

    public function subtract(self $other): self
    {
        return new self(bcsub($this->value, $other->value, 4));
    }

    public function compare(self $other): int
    {
        return bccomp($this->value, $other->value, 4);
    }

    public function greaterThan(self $other): bool
    {
        return $this->compare($other) === 1;
    }
    
    public function isLessThan(self $other): bool
    {
        return $this->compare($other) === -1;
    }

    public function isLessThanOrEqual(self $other): bool
    {
        return $this->compare($other) <= 0;
    }

    public function isZero(): bool
    {
        return $this->compare(self::of(0)) === 0;
    }

    public function isNegative(): bool
    {
        return $this->compare(self::of(0)) === -1;
    }

    public function absolute(): self
    {
        return $this->isNegative() ? new self(bcsub('0', $this->value, 4)) : new self($this->value);
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
