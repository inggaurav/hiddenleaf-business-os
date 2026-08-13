<?php

namespace App\Notifications;

use App\Notifications\Channels\TenantDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BusinessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $event,
        private string $title,
        private string $message,
        private ?int $organizationId = null,
        private ?int $workspaceId = null,
        private array $metadata = [],
    ) {}

    public function via(object $notifiable): array
    {
        return [TenantDatabaseChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'title' => $this->title,
            'message' => $this->message,
            'organization_id' => $this->organizationId,
            'workspace_id' => $this->workspaceId,
            'metadata' => $this->metadata,
        ];
    }

    public function organizationId(): ?int
    {
        return $this->organizationId;
    }

    public function workspaceId(): ?int
    {
        return $this->workspaceId;
    }
}
