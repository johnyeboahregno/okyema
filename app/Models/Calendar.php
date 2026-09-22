<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConnectorProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Calendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_context_id',
        'connector_account_id',
        'name',
        'provider',
        'provider_calendar_id',
        'timezone',
        'colour',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'provider' => ConnectorProvider::class,
            'is_primary' => 'boolean',
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

    public function connectorAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectorAccount::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
