<?php

namespace App\Domain\Receiving\Models;

use App\Domain\Warehouse\Models\Warehouse;
use App\Enums\InspectionMethod;
use App\Enums\QualityInspectionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'sra_id',
        'delivery_verification_id',
        'warehouse_id',
        'inspector_id',
        'inspection_date',
        'status',
        'method',
        'sample_size',
        'summary',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QualityInspectionStatus::class,
            'method' => InspectionMethod::class,
            'inspection_date' => 'date',
            'completed_at' => 'datetime',
            'sample_size' => 'decimal:4',
        ];
    }

    public function sra(): BelongsTo
    {
        return $this->belongsTo(StoreReceiptAdvice::class, 'sra_id');
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(DeliveryVerification::class, 'delivery_verification_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QualityInspectionItem::class);
    }

    public function isComplete(): bool
    {
        return in_array($this->status, [
            QualityInspectionStatus::Passed,
            QualityInspectionStatus::Failed,
            QualityInspectionStatus::PartiallyPassed,
        ], true);
    }
}
