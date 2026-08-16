<?php

namespace App\Domain\MrFox\Tools;

use App\Domain\MrFox\Contracts\MrFoxToolContract;
use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\DTO\ToolResult;
use App\Domain\MrFox\RiskLevel;
use App\Models\MrFoxKnowledgeDocument;

class KnowledgeGetDocumentTool implements MrFoxToolContract
{
    public function name(): string
    {
        return 'knowledge.get.document';
    }

    public function description(): string
    {
        return 'Retrieve metadata, version details, and table of contents for a specific workspace knowledge document.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['document_id'],
            'properties' => [
                'document_id' => [
                    'type' => 'integer',
                    'description' => 'The unique ID of the knowledge document',
                ],
            ],
        ];
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function requiredModule(): ?string
    {
        return null;
    }

    public function riskLevel(): RiskLevel
    {
        return RiskLevel::READ;
    }

    public function execute(ToolContext $context, array $input): ToolResult
    {
        $wsId = $context->getWorkspaceId();
        $docId = (int) ($input['document_id'] ?? 0);

        $doc = MrFoxKnowledgeDocument::where('workspace_id', $wsId)->where('id', $docId)->first();

        if (! $doc) {
            return ToolResult::error("Knowledge document #{$docId} not found in this workspace.");
        }

        $safeData = [
            'id' => $doc->id,
            'title' => $doc->title,
            'file_type' => $doc->file_type,
            'version' => $doc->version,
            'chunk_count' => $doc->chunk_count,
            'visibility' => $doc->visibility,
            'created_at' => optional($doc->created_at)->toIso8601String(),
        ];

        $summary = "Knowledge Document: '{$doc->title}' (v{$doc->version}, {$doc->file_type}).";

        return ToolResult::success($safeData, $summary, [
            ['type' => 'knowledge_document', 'id' => $doc->id, 'label' => "Document: {$doc->title}", 'route' => '/knowledge'],
        ]);
    }
}
