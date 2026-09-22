<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectorAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'external_account_id',
        'scopes',
        'access_token',
        'refresh_token',
        'expires_at',
        'status',
        'revoked_at',
        'last_synced_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'provider' => ConnectorProvider::class,
            'status' => ConnectorStatus::class,
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
