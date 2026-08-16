<?php

namespace App\Domain\MrFox\Validation;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use Illuminate\Support\Facades\Validator;

class ToolInputValidator
{
    /**
     * Forbidden authority keys that LLMs / callers must never pass as trusted parameters.
     */
    private const FORBIDDEN_KEYS = [
        'organization_id',
        'workspace_id',
        'tenant_id',
        'user_id',
        'is_admin',
        'is_super_admin',
        'permissions',
    ];

    /**
     * Validate and sanitize tool inputs against the tool's JSON schema and security rules.
     *
     * @return array{valid: bool, sanitized: array, errors: array<string, string>}
     */
    public function validate(MrFoxToolContract $tool, array $rawInput): array
    {
        // 1. Strip or reject forbidden authority keys
        $cleanInput = array_diff_key($rawInput, array_flip(self::FORBIDDEN_KEYS));

        $schema = $tool->inputSchema();
        $requiredFields = $schema['required'] ?? [];
        $properties = $schema['properties'] ?? [];

        $rules = [];
        foreach ($properties as $field => $def) {
            $fieldRules = [];
            $isRequired = in_array($field, $requiredFields, true);

            $fieldRules[] = $isRequired ? 'required' : 'nullable';

            $type = $def['type'] ?? 'string';
            match ($type) {
                'integer' => $fieldRules = array_merge($fieldRules, ['integer', 'min:0', 'max:2147483647']),
                'number' => $fieldRules = array_merge($fieldRules, ['numeric', 'min:0']),
                'boolean' => $fieldRules[] = 'boolean',
                'array' => $fieldRules = array_merge($fieldRules, ['array', 'max:50']),
                'object' => $fieldRules[] = 'array',
                default => $fieldRules = array_merge($fieldRules, ['string', 'max:2000']),
            };

            // Custom field safety
            if ($field === 'limit') {
                $fieldRules = ['nullable', 'integer', 'min:1', 'max:100'];
            }
            if ($field === 'email') {
                $fieldRules[] = 'email';
            }
            if ($field === 'due_date' || $field === 'issue_date') {
                $fieldRules[] = 'date';
            }

            $rules[$field] = $fieldRules;
        }

        $validator = Validator::make($cleanInput, $rules);

        if ($validator->fails()) {
            return [
                'valid' => false,
                'sanitized' => [],
                'errors' => $validator->errors()->toArray(),
            ];
        }

        // Only allow recognized properties if properties schema is defined
        $sanitized = [];
        if (! empty($properties)) {
            foreach ($properties as $field => $def) {
                if (array_key_exists($field, $cleanInput)) {
                    $sanitized[$field] = $cleanInput[$field];
                }
            }
        } else {
            $sanitized = $cleanInput;
        }

        return [
            'valid' => true,
            'sanitized' => $sanitized,
            'errors' => [],
        ];
    }
}
