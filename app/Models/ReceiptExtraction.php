<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptExtraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_id',
        'merchant',
        'expense_date',
        'total_minor',
        'currency',
        'tax_minor',
        'payment_method',
        'line_items',
        'confidence',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'total_minor' => 'integer',
            'tax_minor' => 'integer',
            'line_items' => 'array',
            'confidence' => 'float',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
