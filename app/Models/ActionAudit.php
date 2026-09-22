<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionAudit extends Model
{
    use HasFactory;

    // The table stores a single created_at; Eloquent timestamps are redundant.
    public $timestamps = false;

    protected $fillable = [
        'action_item_id',
        'user_id',
        'from_status',
        'to_status',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function actionItem(): BelongsTo
    {
        return $this->belongsTo(ActionItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
