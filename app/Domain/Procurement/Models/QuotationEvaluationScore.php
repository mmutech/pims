<?php

namespace App\Domain\Procurement\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationEvaluationScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_evaluation_id',
        'supplier_quotation_id',
        'criterion',
        'weight',
        'score',
        'weighted_score',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'score' => 'decimal:2',
            'weighted_score' => 'decimal:2',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(QuotationEvaluation::class, 'quotation_evaluation_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(SupplierQuotation::class, 'supplier_quotation_id');
    }
}
