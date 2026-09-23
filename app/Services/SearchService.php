<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActionItem;
use App\Models\Conversation;
use App\Models\DocumentReference;
use App\Models\Meeting;
use App\Models\Person;
use App\Models\Trip;
use App\Models\User;
use App\Models\WorkspaceContext;

/**
 * Source-grounded global search, scoped to the active workspace. Every result
 * carries its source type and record id so the UI can deep-link to it.
 */
final class SearchService
{
    /**
     * @return array{meetings: list<array<string, mixed>>, actions: list<array<string, mixed>>, documents: list<array<string, mixed>>, people: list<array<string, mixed>>, conversations: list<array<string, mixed>>, trips: list<array<string, mixed>>}
     */
    public function search(User $user, ?WorkspaceContext $context, string $query): array
    {
        $like = '%'.$query.'%';

        return [
            'meetings' => Meeting::query()->forContext($user, $context)
                ->where('title', 'like', $like)->limit(10)->get(['id', 'title', 'starts_at'])->toArray(),
            'actions' => ActionItem::query()->forContext($user, $context)
                ->where('title', 'like', $like)->limit(10)->get(['id', 'title', 'status', 'due_date'])->toArray(),
            'documents' => DocumentReference::query()->forContext($user, $context)
                ->where('title', 'like', $like)->limit(10)->get(['id', 'title', 'provider', 'deep_link'])->toArray(),
            'people' => Person::query()->forContext($user, $context)
                ->where('name', 'like', $like)->limit(10)->get(['id', 'name'])->toArray(),
            'conversations' => Conversation::query()->forContext($user, $context)
                ->where('subject', 'like', $like)->limit(10)->get(['id', 'subject', 'channel'])->toArray(),
            'trips' => Trip::query()->forContext($user, $context)
                ->where('title', 'like', $like)->limit(10)->get(['id', 'title', 'starts_on'])->toArray(),
        ];
    }
}
