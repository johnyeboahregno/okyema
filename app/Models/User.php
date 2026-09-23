<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MembershipRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Workspace ids this user owns — the scope of the merged "All contexts"
     * view. Memoised, because one instance serves a whole request.
     */
    private ?array $ownedWorkspaceIds = null;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'microsoft_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** Workspace contexts this user is a member of. */
    public function workspaceContexts(): BelongsToMany
    {
        return $this->belongsToMany(WorkspaceContext::class, 'memberships')
            ->withPivot(['role', 'is_active_context'])
            ->withTimestamps();
    }

    /**
     * Workspace ids this user owns. The merged "All contexts" view is limited
     * to these, so a delegated membership never merges someone else's data in.
     */
    public function ownedWorkspaceIds(): array
    {
        return $this->ownedWorkspaceIds ??= $this->memberships()
            ->where('role', MembershipRole::Owner->value)
            ->pluck('workspace_context_id')
            ->all();
    }

    /** Forget the memo after a context is created or removed. */
    public function forgetOwnedWorkspaceIds(): void
    {
        $this->ownedWorkspaceIds = null;
    }
}
