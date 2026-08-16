<?php

namespace App\Domain\MrFox\Knowledge;

use App\Domain\MrFox\DTO\ToolContext;
use App\Domain\MrFox\Evidence\UnifiedEvidenceItem;
use App\Models\MrFoxKnowledgeChunk;

class KnowledgeSearchService
{
    /**
     * Search workspace knowledge base with multi-tenant filtering, keyword tokenization, and citation generation.
     *
     * @return array{results: array, evidence: UnifiedEvidenceItem[]}
     */
    public function search(ToolContext $context, string $query, int $limit = 5): array
    {
        $wsId = $context->getWorkspaceId();
        $limit = min(max($limit, 1), 20);

        if (empty(trim($query))) {
            return ['results' => [], 'evidence' => []];
        }

        // Tokenize query into meaningful search terms (length >= 3)
        $words = array_filter(
            preg_split('/[\s,\.\?!]+/', strtolower($query)),
            fn ($w) => strlen($w) >= 3 && ! in_array($w, ['the', 'how', 'much', 'what', 'where', 'when', 'with', 'from', 'this', 'that', 'have', 'does', 'for'])
        );

        $chunkQuery = MrFoxKnowledgeChunk::query()
            ->where('workspace_id', $wsId)
            ->whereHas('document', function ($docQuery) {
                $docQuery->whereIn('visibility', ['workspace', 'public']);
            })
            ->with('document');

        if (! empty($words)) {
            $chunkQuery->where(function ($q) use ($words, $query) {
                $q->where('content', 'like', "%{$query}%");
                foreach ($words as $word) {
                    $q->orWhere('content', 'like', "%{$word}%");
                }
            });
        } else {
            $chunkQuery->where('content', 'like', "%{$query}%");
        }

        $chunks = $chunkQuery->take($limit)->get();

        $results = [];
        $evidence = [];

        foreach ($chunks as $chunk) {
            $doc = $chunk->document;
            $title = $doc?->title ?? 'Untitled Document';
            $snippet = substr($chunk->content, 0, 300).'...';

            $results[] = [
                'chunk_id' => $chunk->id,
                'document_id' => $chunk->document_id,
                'document_title' => $title,
                'page_number' => $chunk->page_number,
                'snippet' => $snippet,
            ];

            $evidence[] = UnifiedEvidenceItem::knowledgeChunk(
                chunkId: $chunk->id,
                documentId: $chunk->document_id,
                documentTitle: $title,
                snippet: $snippet,
                pageNumber: $chunk->page_number,
                score: 0.95
            );
        }

        return [
            'results' => $results,
            'evidence' => $evidence,
        ];
    }
}
