<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SourceCitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'citeable_type',
        'citeable_id',
        'note_id',
        'quote',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function citeable(): MorphTo
    {
        return $this->morphTo();
    }
}
