<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TravelSegmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'type',
        'details',
        'from_location',
        'to_location',
        'starts_at',
        'ends_at',
        'booking_reference',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'type' => TravelSegmentType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
