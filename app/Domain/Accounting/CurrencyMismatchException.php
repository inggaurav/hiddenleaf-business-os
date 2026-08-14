<?php

namespace App\Domain\Accounting;

use DomainException;

final class CurrencyMismatchException extends DomainException
{
    public static function between(?string $left, ?string $right): self
    {
        return new self(sprintf(
            'Cannot perform money arithmetic across currencies (%s vs %s). Convert explicitly before arithmetic.',
            $left ?? 'currency-neutral',
            $right ?? 'currency-neutral'
        ));
    }
}
