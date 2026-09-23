<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageChannel;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_context_id',
        'person_id',
        'channel',
        'subject',
        'provider',
        'provider_conversation_id',
        'is_vip',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'is_vip' => 'boolean',
            'last_message_at' => 'datetime',
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

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessageReference::class);
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
