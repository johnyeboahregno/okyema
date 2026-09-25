<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordingJob extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_context_id',
        'title',
        'provider',
        'provider_job_id',
        'status',
        'transcript',
        'error',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspaceContext(): BelongsTo
    {
        return $this->belongsTo(WorkspaceContext::class);
    }
}
