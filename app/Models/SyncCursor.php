<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncCursor extends Model
{
    use HasFactory;

    protected $fillable = [
        'connector_account_id',
        'resource_type',
        'cursor',
    ];

    public function connectorAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectorAccount::class);
    }
}
