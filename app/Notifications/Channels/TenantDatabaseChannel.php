<?php

namespace App\Notifications\Channels;

use App\Notifications\BusinessNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;

class TenantDatabaseChannel extends DatabaseChannel
{
    protected function buildPayload($notifiable, $notification): array
    {
        $payload = parent::buildPayload($notifiable, $notification);

        if ($notification instanceof BusinessNotification) {
            $payload['organization_id'] = $notification->organizationId();
            $payload['workspace_id'] = $notification->workspaceId();
        }

        return $payload;
    }
}
