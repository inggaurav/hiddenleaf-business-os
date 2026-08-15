<?php

namespace App\Domain\Automation\Conditions;

use Carbon\Carbon;

class ConditionEvaluator
{
    /**
     * Evaluate condition tree against payload data.
     */
    public function evaluate(?array $conditionTree, array $payload): bool
    {
        if (empty($conditionTree)) {
            return true; // No conditions = always match
        }

        // Logical group (AND / OR)
        if (isset($conditionTree['operator']) && isset($conditionTree['conditions'])) {
            $op = strtoupper($conditionTree['operator']);
            $conditions = $conditionTree['conditions'];

            if ($op === 'OR') {
                foreach ($conditions as $cond) {
                    if ($this->evaluate($cond, $payload)) {
                        return true;
                    }
                }

                return empty($conditions);
            }

            // Default: AND
            foreach ($conditions as $cond) {
                if (! $this->evaluate($cond, $payload)) {
                    return false;
                }
            }

            return true;
        }

        // Single leaf condition: field, operator, value
        $field = $conditionTree['field'] ?? null;
        $operator = $conditionTree['operator'] ?? 'equals';
        $targetValue = $conditionTree['value'] ?? null;

        if (! $field) {
            return true;
        }

        $actualValue = data_get($payload, $field);

        return $this->compare($actualValue, $operator, $targetValue);
    }

    private function compare(mixed $actual, string $operator, mixed $target): bool
    {
        return match (strtolower($operator)) {
            'equals', '==' => $actual == $target,
            'not_equals', '!=' => $actual != $target,
            'greater_than', '>' => (float) $actual > (float) $target,
            'greater_than_or_equal', '>=' => (float) $actual >= (float) $target,
            'less_than', '<' => (float) $actual < (float) $target,
            'less_than_or_equal', '<=' => (float) $actual <= (float) $target,
            'contains' => is_string($actual) && str_contains(strtolower($actual), strtolower((string) $target)),
            'not_contains' => is_string($actual) && ! str_contains(strtolower($actual), strtolower((string) $target)),
            'in' => is_array($target) && in_array($actual, $target),
            'not_in' => is_array($target) && ! in_array($actual, $target),
            'older_than_hours' => $actual ? Carbon::parse($actual)->diffInHours(now()) >= (int) $target : false,
            'older_than_days' => $actual ? Carbon::parse($actual)->diffInDays(now()) >= (int) $target : false,
            default => false,
        };
    }
}
