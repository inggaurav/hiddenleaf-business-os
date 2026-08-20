<?php

namespace App\Domain\MrFox\Evidence;

use JsonSerializable;

class UnifiedEvidenceItem implements JsonSerializable
{
    public function __construct(
        public string $type,
        public string|int $id,
        public string $label,
        public ?string $route = null,
        public ?string $snippet = null,
        public ?int $pageNumber = null,
        public ?string $documentId = null,
        public ?float $amount = null,
        public ?float $score = null,
        public ?string $authority = 'canonical',
        public ?string $freshness = null,
        public array $metadata = []
    ) {
        $this->freshness = $freshness ?: now()->toIso8601String();
    }

    public static function erpRecord(
        string $entityType,
        string|int $id,
        string $label,
        string $route,
        ?float $amount = null,
        array $metadata = []
    ): self {
        return new self(
            type: 'erp_record',
            id: $id,
            label: $label,
            route: $route,
            amount: $amount,
            authority: 'canonical_erp',
            metadata: array_merge($metadata, ['entity' => $entityType])
        );
    }

    public static function knowledgeChunk(
        string|int $chunkId,
        string|int $documentId,
        string $documentTitle,
        string $snippet,
        ?int $pageNumber = null,
        ?float $score = null,
        string $route = '/knowledge'
    ): self {
        return new self(
            type: 'knowledge_chunk',
            id: $chunkId,
            label: "{$documentTitle}".($pageNumber ? " (p. {$pageNumber})" : ''),
            route: $route,
            snippet: $snippet,
            pageNumber: $pageNumber,
            documentId: (string) $documentId,
            score: $score,
            authority: 'governed_knowledge'
        );
    }

    public static function brandProfile(
        string|int $profileId,
        string $brandName,
        string $industry,
        string $route = '/settings/brand'
    ): self {
        return new self(
            type: 'brand_profile',
            id: $profileId,
            label: "Brand: {$brandName} ({$industry})",
            route: $route,
            authority: 'brand_governance'
        );
    }

    public static function communication(
        string|int $id,
        string $platform,
        string $sender,
        string $snippet,
        string $route = '/inbox'
    ): self {
        return new self(
            type: 'communication_message',
            id: $id,
            label: "{$platform}: @{$sender}",
            route: $route,
            snippet: $snippet,
            authority: 'social_inbox'
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'id' => $this->id,
            'label' => $this->label,
            'route' => $this->route,
            'snippet' => $this->snippet,
            'page_number' => $this->pageNumber,
            'document_id' => $this->documentId,
            'amount' => $this->amount,
            'score' => $this->score,
            'authority' => $this->authority,
            'freshness' => $this->freshness,
            'metadata' => ! empty($this->metadata) ? $this->metadata : null,
        ], fn ($v) => $v !== null);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
