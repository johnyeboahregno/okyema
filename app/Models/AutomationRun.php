<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRun extends Model
{
    use HasFactory;

    // started_at / finished_at are the run's own timestamps.
    public $timestamps = false;

    protected $fillable = [
        'automation_rule_id',
        'status',
        'result',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function automationRule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class);
    }
}
