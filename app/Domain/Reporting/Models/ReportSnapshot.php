<?php

namespace App\Domain\Reporting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSnapshot extends Model
{
    protected $fillable = [
        'report_key',
        'snapshot_date',
        'row_count',
        'total_quantity',
        'total_value',
        'payload',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'row_count' => 'integer',
            'total_quantity' => 'decimal:4',
            'total_value' => 'decimal:2',
            'payload' => 'array',
        ];
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
