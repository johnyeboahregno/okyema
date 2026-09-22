<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkspaceContextType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceContext extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkspaceContextType::class,
            'is_default' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
            ->withPivot(['role', 'is_active_context'])
            ->withTimestamps();
    }
}
