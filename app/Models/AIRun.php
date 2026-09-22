<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AIRun extends Model
{
    use HasFactory;

    // Eloquent would otherwise snake-case "AIRun" to "a_i_runs".
    protected $table = 'ai_runs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'run_type',
        'provider',
        'model',
        'input_summary',
        'output',
        'latency_ms',
        'status',
        'error_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'input_summary' => 'array',
            'output' => 'array',
            'latency_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
