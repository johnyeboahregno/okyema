<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'workspace_context_id',
        'direction',
        'channel',
        'subject',
        'snippet',
        'sender_person_id',
        'provider',
        'provider_message_id',
        'received_at',
        'needs_reply',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'received_at' => 'datetime',
            'needs_reply' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'sender_person_id');
    }
}
