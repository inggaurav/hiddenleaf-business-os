<?php

namespace App\Domain\Communications\Matching;

use App\Models\CrmLead;
use App\Models\Customer;
use App\Models\User;

class IdentityMatcher
{
    /**
     * Match a participant to existing CRM entities within the same workspace.
     *
     * @return array{matched: bool, entity_type: ?string, entity_id: ?int, entity_name: ?string, confidence: string}
     */
    public function match(int $organizationId, int $workspaceId, ?string $email = null, ?string $phone = null): array
    {
        // 1. Match by email
        if (! empty($email)) {
            $email = strtolower(trim($email));

            // Customer
            $customer = Customer::where('workspace_id', $workspaceId)->where('email', $email)->first();
            if ($customer) {
                return [
                    'matched' => true,
                    'entity_type' => 'customer',
                    'entity_id' => $customer->id,
                    'entity_name' => $customer->name,
                    'confidence' => 'high_email_verified',
                ];
            }

            // Lead
            $lead = CrmLead::where('workspace_id', $workspaceId)->where('email', $email)->first();
            if ($lead) {
                return [
                    'matched' => true,
                    'entity_type' => 'lead',
                    'entity_id' => $lead->id,
                    'entity_name' => $lead->name,
                    'confidence' => 'high_email_verified',
                ];
            }

            // Team User
            $user = User::where('email', $email)->first();
            if ($user) {
                return [
                    'matched' => true,
                    'entity_type' => 'user',
                    'entity_id' => $user->id,
                    'entity_name' => $user->name,
                    'confidence' => 'high_team_member',
                ];
            }
        }

        // 2. Match by normalized phone
        if (! empty($phone)) {
            $normalizedPhone = preg_replace('/[^\d+]/', '', $phone);

            $customer = Customer::where('workspace_id', $workspaceId)
                ->where(function ($q) use ($normalizedPhone, $phone) {
                    $q->where('contact', $phone)->orWhere('contact', $normalizedPhone);
                })->first();

            if ($customer) {
                return [
                    'matched' => true,
                    'entity_type' => 'customer',
                    'entity_id' => $customer->id,
                    'entity_name' => $customer->name,
                    'confidence' => 'medium_phone_matched',
                ];
            }

            $lead = CrmLead::where('workspace_id', $workspaceId)
                ->where(function ($q) use ($normalizedPhone, $phone) {
                    $q->where('phone_number', $phone)->orWhere('phone_number', $normalizedPhone);
                })->first();

            if ($lead) {
                return [
                    'matched' => true,
                    'entity_type' => 'lead',
                    'entity_id' => $lead->id,
                    'entity_name' => $lead->name,
                    'confidence' => 'medium_phone_matched',
                ];
            }
        }

        return [
            'matched' => false,
            'entity_type' => null,
            'entity_id' => null,
            'entity_name' => null,
            'confidence' => 'none',
        ];
    }
}
