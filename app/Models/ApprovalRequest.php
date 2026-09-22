<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_context_id',
        'kind',
        'title',
        'details',
        'status',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'status' => ApprovalStatus::class,
            'decided_at' => 'datetime',
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

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
