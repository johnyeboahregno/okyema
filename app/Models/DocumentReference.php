<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentReference extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'workspace_context_id',
        'source_type',
        'source_id',
        'title',
        'provider',
        'provider_document_id',
        'deep_link',
        'mime_type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspaceContext(): BelongsTo
    {
        return $this->belongsTo(WorkspaceContext::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
