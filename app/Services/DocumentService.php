<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentReference;
use App\Models\User;
use App\Models\WorkspaceContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * Permission-aware document references and knowledge retrieval. Only
 * documents in the user's own context are ever returned.
 */
final class DocumentService
{
    /**
     * @return Collection<int, DocumentReference>
     */
    public function index(User $user, WorkspaceContext $context, ?string $query = null): Collection
    {
        return DocumentReference::query()
            ->where('user_id', $user->id)
            ->where('workspace_context_id', $context->id)
            ->when($query, fn ($q) => $q->where('title', 'like', '%'.$query.'%'))
            ->orderBy('title')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(User $user, WorkspaceContext $context, array $data): DocumentReference
    {
        return DocumentReference::create([
            'user_id' => $user->id,
            'workspace_context_id' => $context->id,
            'title' => $data['title'],
            'provider' => $data['provider'] ?? 'drive',
            'provider_document_id' => $data['provider_document_id'] ?? null,
            'deep_link' => $data['deep_link'] ?? null,
            'mime_type' => $data['mime_type'] ?? null,
        ]);
    }
}
