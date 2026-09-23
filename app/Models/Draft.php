<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DraftStatus;
use App\Enums\MessageChannel;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Draft extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'workspace_context_id',
        'approval_request_id',
        'channel',
        'to_recipients',
        'subject',
        'body',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'to_recipients' => 'array',
            'status' => DraftStatus::class,
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
