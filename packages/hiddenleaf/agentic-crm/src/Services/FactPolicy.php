<?php

namespace HiddenLeaf\AgenticCrm\Services;

use HiddenLeaf\AgenticCrm\Domain\ContactFact;
use HiddenLeaf\AgenticCrm\Domain\Evidence;

final class FactPolicy
{
    public function factStatus(Evidence $evidence): string
    {
        return match ($evidence->band) {
            'strong' => ContactFact::AUTO_APPLIED,
            'review' => ContactFact::PENDING_REVIEW,
            default => ContactFact::REJECTED,
        };
    }
}
