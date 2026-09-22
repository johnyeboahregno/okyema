<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_context_id',
        'name',
        'trigger',
        'conditions',
        'action',
        'approval_level',
        'is_active',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
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

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
