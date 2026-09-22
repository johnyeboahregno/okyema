<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_context_id',
        'name',
        'organisation',
        'title',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspaceContext(): BelongsTo
    {
        return $this->belongsTo(WorkspaceContext::class);
    }

    public function providerIdentities(): HasMany
    {
        return $this->hasMany(ProviderIdentity::class);
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
