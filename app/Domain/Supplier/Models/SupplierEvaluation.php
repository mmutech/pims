<?php

namespace App\Domain\Supplier\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierEvaluation extends Model
{
    protected $fillable = [
        'supplier_id',
        'evaluation_date',
        'quality_score',
        'delivery_score',
        'pricing_score',
        'service_score',
        'overall_score',
        'evaluator_id',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'evaluation_date' => 'date',
            'quality_score' => 'decimal:2',
            'delivery_score' => 'decimal:2',
            'pricing_score' => 'decimal:2',
            'service_score' => 'decimal:2',
            'overall_score' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
