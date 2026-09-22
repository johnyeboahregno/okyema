<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRun extends Model
{
    use HasFactory;

    // The table records started_at / finished_at; Eloquent timestamps are
    // redundant here.
    public $timestamps = false;

    protected $fillable = [
        'connector_account_id',
        'resource_type',
        'status',
        'items_synced',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'items_synced' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function connectorAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectorAccount::class);
    }
}
