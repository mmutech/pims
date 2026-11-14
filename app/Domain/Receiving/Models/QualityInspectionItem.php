<?php

namespace App\Domain\Receiving\Models;

use App\Enums\InspectionResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityInspectionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quality_inspection_id',
        'sra_item_id',
        'inspected_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'quarantined_quantity',
        'result',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'inspected_quantity' => 'decimal:4',
            'accepted_quantity' => 'decimal:4',
            'rejected_quantity' => 'decimal:4',
            'quarantined_quantity' => 'decimal:4',
            'result' => InspectionResult::class,
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(QualityInspection::class, 'quality_inspection_id');
    }

    public function sraItem(): BelongsTo
    {
        return $this->belongsTo(StoreReceiptAdviceItem::class, 'sra_item_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(QualityInspectionFinding::class);
    }
}
