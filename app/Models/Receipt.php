<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReceiptFileStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_context_id',
        'expense_id',
        'original_path',
        'content_hash',
        'mime_type',
        'size_bytes',
        'file_status',
        'merchant',
        'total_minor',
        'currency',
        'expense_date',
        'confidence',
        'drive_folder',
        'drive_filename',
        'drive_file_id',
        'drive_link',
        'filed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_minor' => 'integer',
            'size_bytes' => 'integer',
            'expense_date' => 'date',
            'confidence' => 'float',
            'file_status' => ReceiptFileStatus::class,
            'filed_at' => 'datetime',
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

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function extractions(): HasMany
    {
        return $this->hasMany(ReceiptExtraction::class);
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
