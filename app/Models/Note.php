<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NoteSourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'user_id',
        'workspace_context_id',
        'body',
        'source_type',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => NoteSourceType::class,
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
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
