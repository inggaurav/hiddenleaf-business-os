<?php

namespace App\Domain\MrFox;

enum RiskLevel: string
{
    case READ = 'READ';
    case LOW = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH = 'HIGH';
    case CRITICAL = 'CRITICAL';

    public function requiresApproval(): bool
    {
        return in_array($this, [self::HIGH, self::CRITICAL], true);
    }
}
