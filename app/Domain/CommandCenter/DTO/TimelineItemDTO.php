<?php

namespace App\Domain\CommandCenter\DTO;

class TimelineItemDTO
{
    public function __construct(
        public string $id,
        public string $domain, // sales, finance, comms, crm, taskly, hrm, operations, approvals
        public string $eventType,
        public string $title,
        public string $description,
        public string $timestamp,
        public ?string $actorName = null,
        public ?string $route = null,
        public string $severity = 'info', // info, success, warning, critical
        public array $evidence = []
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'event_type' => $this->eventType,
            'title' => $this->title,
            'description' => $this->description,
            'timestamp' => $this->timestamp,
            'actor_name' => $this->actorName,
            'route' => $this->route,
            'severity' => $this->severity,
            'evidence' => $this->evidence,
        ];
    }
}
