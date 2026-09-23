<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventState;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'calendar_id',
        'user_id',
        'workspace_context_id',
        'title',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'is_all_day',
        'state',
        'timezone',
        'provider',
        'provider_event_id',
        'provider_revision',
        'external_url',
        'recurrence_rule',
        'recurrence_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_all_day' => 'boolean',
            'state' => EventState::class,
        ];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspaceContext(): BelongsTo
    {
        return $this->belongsTo(WorkspaceContext::class);
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
