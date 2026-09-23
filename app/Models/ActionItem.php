<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActionPriority;
use App\Enums\ActionStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ActionItem extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_context_id',
        'meeting_id',
        'title',
        'description',
        'owner',
        'status',
        'priority',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'status' => ActionStatus::class,
            'priority' => ActionPriority::class,
            'due_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspaceContext(): BelongsTo
    {
        return $this->belongsTo(WorkspaceContext::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ActionAudit::class);
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(SourceCitation::class, 'citeable');
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->due_date !== null
            && $this->due_date->isPast();
    }
}
