<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'user_id',
        'workspace_context_id',
        'merchant',
        'category',
        'project',
        'expense_date',
        'total_minor',
        'currency',
        'tax_minor',
        'payment_method',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'total_minor' => 'integer',
            'tax_minor' => 'integer',
            'status' => ExpenseStatus::class,
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

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function belongsToUser(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
