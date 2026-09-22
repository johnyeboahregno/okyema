<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MembershipRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Membership extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_context_id',
        'role',
        'is_active_context',
    ];

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'is_active_context' => 'boolean',
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
}
